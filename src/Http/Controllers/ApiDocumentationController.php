<?php

namespace YusufGenc34\FilamentApiForge\Http\Controllers;

use YusufGenc34\FilamentApiForge\Attributes\ApiIgnore;
use YusufGenc34\FilamentApiForge\Attributes\ApiOperations;
use YusufGenc34\FilamentApiForge\Attributes\ApiTag;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ApiDocumentationController extends Controller
{
    public function __construct(
        protected ResourceDiscoveryService $discoveryService,
    ) {}

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

            $base = "/{$panelId}/{$slug}";
            $item = "/{$panelId}/{$slug}/{id}";
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
                $op = [
                    'tags'        => [$plural],
                    'summary'     => $ops?->getSummary('store') ?? "Create {$label}",
                    'operationId' => 'create' . Str::studly($label),
                    'requestBody' => [
                        'required' => true,
                        'content'  => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schemaName}"]]],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => "The created {$label}.",
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'data' => ['$ref' => "#/components/schemas/{$schemaName}"],
                                ],
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
                $op = [
                    'tags'        => [$plural],
                    'summary'     => $ops?->getSummary('update') ?? "Update {$label}",
                    'operationId' => 'update' . Str::studly($label),
                    'parameters'  => [$idParam],
                    'requestBody' => [
                        'required' => true,
                        'content'  => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$schemaName}"]]],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => "The updated {$label}.",
                            'content'     => ['application/json' => ['schema' => [
                                'type'       => 'object',
                                'properties' => [
                                    'data' => ['$ref' => "#/components/schemas/{$schemaName}"],
                                ],
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
}
