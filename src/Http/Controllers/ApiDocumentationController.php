<?php

namespace YusufGenc34\FilamentApiForge\Http\Controllers;

use YusufGenc34\FilamentApiForge\Attributes\ApiIgnore;
use YusufGenc34\FilamentApiForge\Attributes\ApiOperations;
use YusufGenc34\FilamentApiForge\Attributes\ApiTag;
use YusufGenc34\FilamentApiForge\Models\ApiForgeGlobalSetting;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ApiDocumentationController extends Controller
{
    public function __construct(
        protected ResourceDiscoveryService $discoveryService,
    ) {}

    public function publicDocs(): Response
    {
        if (! ApiForgeGlobalSetting::get('docs_public', false)) {
            abort(403, 'API documentation is not publicly available.');
        }

        $openApiUrl = route('api-forge.docs.openapi');

        return response()->view('filament-api-forge::public-docs', compact('openApiUrl'));
    }

    public function openApiSpec(Request $request): JsonResponse
    {
        $resources = $this->discoveryService->discover();
        $apiPrefix = config('filament-api-forge.api_prefix', 'api/v1');
        $baseUrl   = url($apiPrefix);

        $paths = [];

        // Always-present shared schemas (pagination envelope, errors)
        $schemas = [
            'PaginationMeta'  => $this->paginationMetaSchema(),
            'PaginationLinks' => $this->paginationLinksSchema(),
        ];

        // Reusable response components (401 / 403 / 404 / 422)
        $responseComponents = $this->standardResponses();

        // ── 1. DYNAMIC FILAMENT RESOURCES ────────────────────────────────
        foreach ($resources as $res) {
            $resourceClass = $res['resource_class'];

            if ($this->hasAttribute($resourceClass, ApiIgnore::class)) {
                continue;
            }

            $panelId    = $res['panel_id'];
            $slug       = $res['slug'];
            $label      = $res['label'];
            $model      = $res['model_class'];
            $schemaName = class_basename($model);
            $allowed    = $res['api_config']['allowed_methods'] ?? ['index', 'show', 'store', 'update', 'destroy'];

            $plural = $this->readAttribute($resourceClass, ApiTag::class)?->name ?? $res['plural_label'];
            $ops    = $this->readAttribute($resourceClass, ApiOperations::class);

            $schemas[$schemaName] = $this->buildSchema($model);
            $hasUploads = ! empty($res['api_config']['uploads'] ?? []);
            $uploads    = $res['api_config']['uploads'] ?? [];

            // Use configured route segment if set, otherwise fall back to panel ID
            $routeSegment = ApiForgeGlobalSetting::get('route_segment') ?? $panelId;
            $base = "/{$routeSegment}/{$slug}";
            $item = "/{$routeSegment}/{$slug}/{id}";
            $sec  = [['sanctum' => []]];

            // ── Collection endpoints: GET /resource  POST /resource ───────
            $colOps = [];

            if (in_array('index', $allowed)) {
                $op = [
                    'tags'        => [$plural],
                    'summary'     => $ops?->getSummary('index') ?? "List {$plural}",
                    'operationId' => 'list' . Str::studly($plural),
                    'parameters'  => $this->listParams($res),
                    'responses'   => [
                        '200' => [
                            'description' => "Paginated list of {$plural}.",
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'data'  => [
                                        'type'  => 'array',
                                        'items' => ['$ref' => "#/components/schemas/{$schemaName}"],
                                    ],
                                    'meta'  => ['$ref' => '#/components/schemas/PaginationMeta'],
                                    'links' => ['$ref' => '#/components/schemas/PaginationLinks'],
                                ],
                            ]]],
                        ],
                        '401' => ['$ref' => '#/components/responses/Unauthenticated'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                    ],
                    'security' => $sec,
                ];
                if ($desc = $ops?->getDescription('index')) $op['description'] = $desc;
                $colOps['get'] = $op;
            }

            if (in_array('store', $allowed)) {
                // Build the response schema — add _uploads if resource has file fields
                $storeResponseProps = ['data' => ['$ref' => "#/components/schemas/{$schemaName}"]];
                if ($hasUploads) {
                    $storeResponseProps['_uploads'] = $this->uploadResponseSchema($uploads);
                }

                $op = [
                    'tags'        => [$plural],
                    'summary'     => $ops?->getSummary('store') ?? "Create {$label}",
                    'operationId' => 'create' . Str::studly($label),
                    'requestBody' => $hasUploads
                        ? $this->multipartRequestBody($schemaName, $model, $uploads)
                        : [
                            'required' => true,
                            'content'  => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schemaName}"]]],
                        ],
                    'responses' => [
                        '201' => [
                            'description' => "The created {$label}.",
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => $storeResponseProps,
                            ]]],
                        ],
                        '422' => ['$ref' => '#/components/responses/ValidationError'],
                        '401' => ['$ref' => '#/components/responses/Unauthenticated'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                    ],
                    'security' => $sec,
                ];
                if ($desc = $ops?->getDescription('store')) $op['description'] = $desc;
                $colOps['post'] = $op;
            }

            if (!empty($colOps)) {
                $paths[$base] = $colOps;
            }

            // ── Item endpoints: GET /resource/{id}  PUT  DELETE ───────────
            $idParam = [
                'name'        => 'id',
                'in'          => 'path',
                'required'    => true,
                'schema'      => ['type' => 'string'],
                'description' => "The {$label} ID.",
            ];
            $itemOps = [];

            if (in_array('show', $allowed)) {
                $op = [
                    'tags'        => [$plural],
                    'summary'     => $ops?->getSummary('show') ?? "Get {$label}",
                    'operationId' => 'get' . Str::studly($label),
                    'parameters'  => [$idParam],
                    'responses'   => [
                        '200' => [
                            'description' => "The {$label} resource.",
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'data' => ['$ref' => "#/components/schemas/{$schemaName}"],
                                ],
                            ]]],
                        ],
                        '404' => ['$ref' => '#/components/responses/NotFound'],
                        '401' => ['$ref' => '#/components/responses/Unauthenticated'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                    ],
                    'security' => $sec,
                ];
                if ($desc = $ops?->getDescription('show')) $op['description'] = $desc;
                $itemOps['get'] = $op;
            }

            if (in_array('update', $allowed)) {
                $updateResponseProps = ['data' => ['$ref' => "#/components/schemas/{$schemaName}"]];
                if ($hasUploads) {
                    $updateResponseProps['_uploads'] = $this->uploadResponseSchema($uploads);
                }

                $op = [
                    'tags'        => [$plural],
                    'summary'     => $ops?->getSummary('update') ?? "Update {$label}",
                    'operationId' => 'update' . Str::studly($label),
                    'parameters'  => [$idParam],
                    'requestBody' => $hasUploads
                        ? $this->multipartRequestBody($schemaName, $model, $uploads)
                        : [
                            'required' => true,
                            'content'  => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schemaName}"]]],
                        ],
                    'responses' => [
                        '200' => [
                            'description' => "The updated {$label}.",
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => $updateResponseProps,
                            ]]],
                        ],
                        '422' => ['$ref' => '#/components/responses/ValidationError'],
                        '404' => ['$ref' => '#/components/responses/NotFound'],
                        '401' => ['$ref' => '#/components/responses/Unauthenticated'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                    ],
                    'security' => $sec,
                ];
                if ($desc = $ops?->getDescription('update')) $op['description'] = $desc;
                $itemOps['put'] = $op;
            }

            if (in_array('destroy', $allowed)) {
                $op = [
                    'tags'        => [$plural],
                    'summary'     => $ops?->getSummary('destroy') ?? "Delete {$label}",
                    'operationId' => 'delete' . Str::studly($label),
                    'parameters'  => [$idParam],
                    'responses'   => [
                        '200' => [
                            'description' => "{$label} deleted successfully.",
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'message' => [
                                        'type'    => 'string',
                                        'example' => 'Resource deleted successfully.',
                                    ],
                                ],
                            ]]],
                        ],
                        '404' => ['$ref' => '#/components/responses/NotFound'],
                        '401' => ['$ref' => '#/components/responses/Unauthenticated'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                    ],
                    'security' => $sec,
                ];
                if ($desc = $ops?->getDescription('destroy')) $op['description'] = $desc;
                $itemOps['delete'] = $op;
            }

            if (!empty($itemOps)) {
                $paths[$item] = $itemOps;
            }

            // ── Custom action endpoints ───────────────────────────────────
            $actionsPrefix = config('filament-api-forge.actions.prefix', 'actions');

            foreach ($this->discoveryService->getActions($resourceClass) as $action) {
                $httpMethod = strtolower($action['method']);
                $isRecord   = $action['record'] ?? true;

                $actionPath = $isRecord
                    ? "{$item}/{$actionsPrefix}/{$action['name']}"
                    : "{$base}/{$actionsPrefix}/{$action['name']}";

                $paths[$actionPath][$httpMethod] = [
                    'tags'        => [$plural],
                    'summary'     => Str::headline($action['name']) . " — {$label}",
                    'operationId' => Str::camel($action['name']) . Str::studly($label) . 'Action',
                    'description' => "Custom action. Requires the **{$action['scope']}** scope.",
                    'parameters'  => $isRecord ? [$idParam] : [],
                    'responses'   => [
                        '200' => [
                            'description' => "Action '{$action['name']}' executed successfully.",
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'message' => ['type' => 'string'],
                                    'action'  => ['type' => 'string', 'example' => $action['name']],
                                    'result'  => ['description' => 'Value returned by the action method.'],
                                ],
                            ]]],
                        ],
                        '404' => ['$ref' => '#/components/responses/NotFound'],
                        '401' => ['$ref' => '#/components/responses/Unauthenticated'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                    ],
                    'security' => $sec,
                ];
            }

            // ── Soft delete endpoints ─────────────────────────────────────
            if (in_array('restore', $allowed)) {
                $paths["{$item}/restore"]['post'] = [
                    'tags'        => [$plural],
                    'summary'     => "Restore {$label}",
                    'operationId' => 'restore' . Str::studly($label),
                    'description' => 'Restore a soft-deleted record. Requires the **write** scope.',
                    'parameters'  => [$idParam],
                    'responses'   => [
                        '200' => [
                            'description' => "The restored {$label}.",
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => ['data' => ['$ref' => "#/components/schemas/{$schemaName}"]],
                            ]]],
                        ],
                        '404' => ['$ref' => '#/components/responses/NotFound'],
                        '401' => ['$ref' => '#/components/responses/Unauthenticated'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                    ],
                    'security' => $sec,
                ];
            }

            if (in_array('forceDelete', $allowed)) {
                $paths["{$item}/force"]['delete'] = [
                    'tags'        => [$plural],
                    'summary'     => "Permanently delete {$label}",
                    'operationId' => 'forceDelete' . Str::studly($label),
                    'description' => 'Permanently delete a (soft-deleted) record. Requires the **delete** scope.',
                    'parameters'  => [$idParam],
                    'responses'   => [
                        '200' => [
                            'description' => "{$label} permanently deleted.",
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'message' => ['type' => 'string', 'example' => 'Resource permanently deleted.'],
                                    'deleted' => ['type' => 'boolean'],
                                ],
                            ]]],
                        ],
                        '404' => ['$ref' => '#/components/responses/NotFound'],
                        '401' => ['$ref' => '#/components/responses/Unauthenticated'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                    ],
                    'security' => $sec,
                ];
            }

            // ── Export endpoint ───────────────────────────────────────────
            if (in_array('export', $allowed) && config('filament-api-forge.export.enabled', true)) {
                $paths["{$base}/export"]['get'] = [
                    'tags'        => [$plural],
                    'summary'     => "Export {$plural}",
                    'operationId' => 'export' . Str::studly($plural),
                    'description' => 'Export the filtered result set as CSV or JSON. Requires the **read** scope.',
                    'parameters'  => array_merge(
                        [[
                            'name'     => 'format',
                            'in'       => 'query',
                            'required' => false,
                            'schema'   => ['type' => 'string', 'enum' => config('filament-api-forge.export.formats', ['csv', 'json']), 'default' => 'csv'],
                        ]],
                        $this->listParams($res),
                    ),
                    'responses' => [
                        '200' => ['description' => 'The exported rows (CSV stream or JSON payload).'],
                        '422' => ['$ref' => '#/components/responses/ValidationError'],
                        '401' => ['$ref' => '#/components/responses/Unauthenticated'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                    ],
                    'security' => $sec,
                ];
            }

            // ── Batch endpoint ────────────────────────────────────────────
            $batchEnabled = $res['api_config']['batch']['enabled']
                ?? config('filament-api-forge.batch.enabled', true);

            if ($batchEnabled) {
                $rowRef = ['$ref' => "#/components/schemas/{$schemaName}"];

                $paths["{$base}/batch"]['post'] = [
                    'tags'        => [$plural],
                    'summary'     => "Batch operations — {$plural}",
                    'operationId' => 'batch' . Str::studly($plural),
                    'description' => 'Transaction-wrapped bulk create, update and delete.',
                    'requestBody' => [
                        'required' => true,
                        'content'  => ['application/json' => ['schema' => [
                            'type'       => 'object',
                            'properties' => [
                                'create' => ['type' => 'array', 'items' => $rowRef],
                                'update' => ['type' => 'array', 'items' => $rowRef],
                                'delete' => ['type' => 'array', 'items' => ['type' => 'integer']],
                            ],
                        ]]],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Batch operation completed.',
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'message' => ['type' => 'string', 'example' => 'Batch operation completed.'],
                                    'created' => ['type' => 'array', 'items' => ['type' => 'integer']],
                                    'updated' => ['type' => 'array', 'items' => ['type' => 'integer']],
                                    'deleted' => ['type' => 'array', 'items' => ['type' => 'integer']],
                                    'failed'  => ['type' => 'array', 'items' => ['type' => 'object']],
                                ],
                            ]]],
                        ],
                        '422' => ['$ref' => '#/components/responses/ValidationError'],
                        '401' => ['$ref' => '#/components/responses/Unauthenticated'],
                        '403' => ['$ref' => '#/components/responses/Forbidden'],
                    ],
                    'security' => $sec,
                ];
            }

            // ── Nested resource endpoints ─────────────────────────────────
            foreach ($res['api_config']['relations'] ?? [] as $childSlug => $relation) {
                $childAllowed = $relation['allowed_methods'] ?? ['index', 'show', 'store', 'update', 'destroy'];
                $childBase    = "{$item}/{$childSlug}";
                $childItem    = "{$childBase}/{childId}";
                $childLabel   = Str::headline(Str::singular($childSlug));
                $childIdParam = [
                    'name'        => 'childId',
                    'in'          => 'path',
                    'required'    => true,
                    'schema'      => ['type' => 'string'],
                    'description' => "The {$childLabel} ID.",
                ];
                $genericChild = ['type' => 'object', 'description' => "{$childLabel} attributes."];
                $childOpId    = Str::studly($label) . Str::studly($childSlug);

                $nestedResponses = [
                    '404' => ['$ref' => '#/components/responses/NotFound'],
                    '401' => ['$ref' => '#/components/responses/Unauthenticated'],
                    '403' => ['$ref' => '#/components/responses/Forbidden'],
                ];

                if (in_array('index', $childAllowed)) {
                    $paths[$childBase]['get'] = [
                        'tags'        => [$plural],
                        'summary'     => "List {$childSlug} of {$label}",
                        'operationId' => 'list' . $childOpId,
                        'parameters'  => [$idParam],
                        'responses'   => ['200' => [
                            'description' => "Paginated {$childSlug} of the {$label}.",
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'data'  => ['type' => 'array', 'items' => $genericChild],
                                    'meta'  => ['$ref' => '#/components/schemas/PaginationMeta'],
                                    'links' => ['$ref' => '#/components/schemas/PaginationLinks'],
                                ],
                            ]]],
                        ]] + $nestedResponses,
                        'security' => $sec,
                    ];
                }

                if (in_array('store', $childAllowed)) {
                    $paths[$childBase]['post'] = [
                        'tags'        => [$plural],
                        'summary'     => "Create {$childLabel} for {$label}",
                        'operationId' => 'create' . $childOpId,
                        'parameters'  => [$idParam],
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => $genericChild]]],
                        'responses'   => ['200' => [
                            'description' => "The created {$childLabel}.",
                            'content'     => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => $genericChild]]]],
                        ], '422' => ['$ref' => '#/components/responses/ValidationError']] + $nestedResponses,
                        'security' => $sec,
                    ];
                }

                if (in_array('show', $childAllowed)) {
                    $paths[$childItem]['get'] = [
                        'tags'        => [$plural],
                        'summary'     => "Get {$childLabel} of {$label}",
                        'operationId' => 'get' . $childOpId,
                        'parameters'  => [$idParam, $childIdParam],
                        'responses'   => ['200' => [
                            'description' => "The {$childLabel}.",
                            'content'     => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => $genericChild]]]],
                        ]] + $nestedResponses,
                        'security' => $sec,
                    ];
                }

                if (in_array('update', $childAllowed)) {
                    $paths[$childItem]['put'] = [
                        'tags'        => [$plural],
                        'summary'     => "Update {$childLabel} of {$label}",
                        'operationId' => 'update' . $childOpId,
                        'parameters'  => [$idParam, $childIdParam],
                        'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => $genericChild]]],
                        'responses'   => ['200' => [
                            'description' => "The updated {$childLabel}.",
                            'content'     => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => $genericChild]]]],
                        ], '422' => ['$ref' => '#/components/responses/ValidationError']] + $nestedResponses,
                        'security' => $sec,
                    ];
                }

                if (in_array('destroy', $childAllowed)) {
                    $paths[$childItem]['delete'] = [
                        'tags'        => [$plural],
                        'summary'     => "Delete {$childLabel} of {$label}",
                        'operationId' => 'delete' . $childOpId,
                        'parameters'  => [$idParam, $childIdParam],
                        'responses'   => ['200' => [
                            'description' => "{$childLabel} deleted successfully.",
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => ['message' => ['type' => 'string'], 'deleted' => ['type' => 'boolean']],
                            ]]],
                        ]] + $nestedResponses,
                        'security' => $sec,
                    ];
                }
            }
        }

        // ── 2. MANUAL APP API ROUTES ──────────────────────────────────────
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (Str::startsWith($uri, 'api/') && !Str::contains($uri, '{panelId}')) {
                $cleanUri = Str::after($uri, 'api/');
                if (empty($cleanUri)) continue;

                $docPath = '/' . ltrim($cleanUri, '/');
                if (isset($paths[$docPath])) continue;

                $methods = array_map('strtolower', array_filter($route->methods(), fn ($m) => $m !== 'HEAD'));

                foreach ($methods as $method) {
                    $paths[$docPath][$method] = [
                        'tags'        => ['General API'],
                        'summary'     => "External API: {$uri}",
                        'operationId' => 'external' . Str::studly(str_replace(['/', '{', '}', '-'], '', $uri)) . $method,
                        'responses'   => ['200' => ['description' => 'OK']],
                    ];

                    $mw = $route->gatherMiddleware();
                    if (in_array('auth:sanctum', $mw) || in_array('auth:api', $mw)) {
                        $paths[$docPath][$method]['security'] = [['sanctum' => []]];
                    }
                }
            }
        }

        return response()->json([
            'openapi' => '3.0.3',
            'info'    => [
                'title'       => config('filament-api-forge.docs.title', 'API Documentation'),
                'description' => config('filament-api-forge.docs.description', ''),
                'version'     => config('filament-api-forge.api_version', 'v1'),
            ],
            'servers'    => [['url' => $baseUrl, 'description' => 'Current Server']],
            'paths'      => $paths,
            'components' => [
                'schemas'         => $schemas,
                'responses'       => $responseComponents,
                'securitySchemes' => [
                    'sanctum' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'API Token'],
                ],
            ],
        ]);
    }

    // ── Shared schema helpers ─────────────────────────────────────────────

    protected function paginationMetaSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'current_page' => ['type' => 'integer', 'example' => 1],
                'from'         => ['type' => 'integer', 'nullable' => true, 'example' => 1],
                'last_page'    => ['type' => 'integer', 'example' => 1],
                'path'         => ['type' => 'string',  'example' => '/api/v1/admin/posts'],
                'per_page'     => ['type' => 'integer', 'example' => 15],
                'to'           => ['type' => 'integer', 'nullable' => true, 'example' => 1],
                'total'        => ['type' => 'integer', 'example' => 1],
            ],
        ];
    }

    protected function paginationLinksSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'first' => ['type' => 'string', 'nullable' => true, 'example' => '/api/v1/admin/posts?page=1'],
                'last'  => ['type' => 'string', 'nullable' => true, 'example' => '/api/v1/admin/posts?page=1'],
                'prev'  => ['type' => 'string', 'nullable' => true, 'example' => null],
                'next'  => ['type' => 'string', 'nullable' => true, 'example' => null],
            ],
        ];
    }

    /**
     * Standard reusable responses (401 / 403 / 404 / 422).
     * These are referenced via $ref in operation responses.
     */
    protected function standardResponses(): array
    {
        $simple = fn (string $msg) => [
            'type'       => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => $msg],
            ],
        ];

        return [
            'Unauthenticated' => [
                'description' => 'Unauthenticated — no token or invalid token provided.',
                'content'     => ['application/json' => ['schema' => $simple('Unauthenticated.')]],
            ],
            'Forbidden' => [
                'description' => 'Forbidden — token does not have the required scope.',
                'content'     => ['application/json' => ['schema' => $simple('This action is unauthorized.')]],
            ],
            'NotFound' => [
                'description' => 'Not Found — the requested resource does not exist.',
                'content'     => ['application/json' => ['schema' => $simple('No query results for model.')]],
            ],
            'ValidationError' => [
                'description' => 'Unprocessable Content — validation failed.',
                'content'     => ['application/json' => ['schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'message' => ['type' => 'string', 'example' => 'The given data was invalid.'],
                        'errors'  => [
                            'type'                 => 'object',
                            'additionalProperties' => [
                                'type'  => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'example' => ['field' => ['The field is required.']],
                        ],
                    ],
                ]]],
            ],
        ];
    }

    // ── Query parameter list ──────────────────────────────────────────────

    protected function listParams(array $res): array
    {
        $p = [
            ['name' => 'per_page', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 15]],
            ['name' => 'page',     'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 1]],
        ];
        foreach ($res['api_config']['allowed_filters'] ?? [] as $f) {
            $p[] = ['name' => "filter[{$f}]", 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => "Filter by {$f}"];
        }
        if (!empty($res['api_config']['allowed_sorts'] ?? [])) {
            $p[] = ['name' => 'sort', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Sort field (prefix - for desc). E.g. -created_at'];
        }
        if (!empty($res['api_config']['allowed_includes'] ?? [])) {
            $p[] = ['name' => 'include', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Comma-separated relations to include. E.g. author,comments'];
        }
        if (!empty($res['api_config']['allowed_fields'] ?? [])) {
            $p[] = ['name' => 'fields', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'Sparse fieldsets — comma-separated list of fields to return'];
        }
        return $p;
    }

    // ── Model → OpenAPI schema ────────────────────────────────────────────

    protected function buildSchema(string $modelClass): array
    {
        $props = ['id' => ['type' => 'integer', 'readOnly' => true, 'example' => 1]];
        try {
            $m = new $modelClass();
            foreach ($m->getFillable() as $field) {
                $props[$field] = ['type' => 'string'];
            }
            if ($m->usesTimestamps()) {
                $props['created_at'] = ['type' => 'string', 'format' => 'date-time', 'readOnly' => true];
                $props['updated_at'] = ['type' => 'string', 'format' => 'date-time', 'readOnly' => true];
            }
        } catch (\Throwable) {}
        return ['type' => 'object', 'properties' => $props];
    }

    // ── PHP 8 Attribute helpers ───────────────────────────────────────────

    /**
     * Read a PHP 8 attribute instance from a class.
     *
     * @template T
     * @param  class-string       $class
     * @param  class-string<T>    $attributeClass
     * @return T|null
     */
    protected function readAttribute(string $class, string $attributeClass): mixed
    {
        try {
            $ref   = new \ReflectionClass($class);
            $attrs = $ref->getAttributes($attributeClass);
            return $attrs ? $attrs[0]->newInstance() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function hasAttribute(string $class, string $attributeClass): bool
    {
        return $this->readAttribute($class, $attributeClass) !== null;
    }

    // ── Upload-aware schema helpers ──────────────────────────────────────────

    /**
     * Build a multipart/form-data request body schema for endpoints
     * that accept file uploads alongside regular fields.
     *
     * Flattens model schema properties into form fields so Swagger UI
     * renders each field as an actual form input.
     */
    protected function multipartRequestBody(string $schemaName, string $modelClass, array $uploads): array
    {
        $modelSchema  = $this->buildSchema($modelClass);
        $modelProps   = $modelSchema['properties'] ?? [];
        $uploadFields = array_keys($uploads);
        $properties   = [];

        // Flatten model properties as form-data fields, exclude upload fields + readOnly fields
        foreach ($modelProps as $name => $prop) {
            if (in_array($name, $uploadFields)) {
                continue;
            }
            if (isset($prop['readOnly']) && $prop['readOnly']) {
                continue;
            }

            $formField = [
                'type'        => $prop['type'] ?? 'string',
                'description' => $prop['description'] ?? "The {$name} value.",
            ];

            if (isset($prop['example'])) {
                $formField['example'] = $prop['example'];
            }

            $properties[$name] = $formField;
        }

        // Add file upload fields with format: binary
        foreach ($uploads as $field => $config) {
            $multiple    = $config['multiple'] ?? false;
            $description = $multiple ? "File upload. Multiple files accepted." : "File upload. Single file.";

            if (isset($config['rules'])) {
                $rules = is_array($config['rules']) ? implode(', ', $config['rules']) : $config['rules'];
                $description .= " Rules: {$rules}.";
            }

            $fileProp = [
                'type'        => $multiple ? 'array' : 'string',
                'format'      => 'binary',
                'description' => trim($description),
            ];

            if ($multiple) {
                $fileProp['items'] = ['type' => 'string', 'format' => 'binary'];
            }

            $properties[$field] = $fileProp;
        }

        return [
            'required' => true,
            'content'  => [
                'multipart/form-data' => [
                    'schema' => [
                        'type'       => 'object',
                        'properties' => $properties,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build the _uploads response schema showing file URLs.
     */
    protected function uploadResponseSchema(array $uploads): array
    {
        $properties = [];

        foreach ($uploads as $field => $config) {
            $multiple = $config['multiple'] ?? false;

            if ($multiple) {
                $properties[$field] = [
                    'type'        => 'object',
                    'description' => "Upload result for '{$field}' (multiple files).",
                    'properties'  => [
                        'urls'  => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string', 'format' => 'uri'],
                            'description' => 'Array of file URLs.',
                        ],
                        'uuids' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'Array of media UUIDs (Spatie Media Library only).',
                        ],
                    ],
                ];
            } else {
                $properties[$field] = [
                    'type'        => 'object',
                    'description' => "Upload result for '{$field}'.",
                    'properties'  => [
                        'url'  => ['type' => 'string', 'format' => 'uri', 'description' => 'Public URL of the uploaded file.'],
                        'uuid' => ['type' => 'string', 'description' => 'Media UUID (Spatie Media Library only).'],
                    ],
                ];
            }
        }

        return [
            'type'        => 'object',
            'description' => 'Upload results keyed by field name. Present only when the request included files.',
            'properties'  => $properties,
        ];
    }
}
