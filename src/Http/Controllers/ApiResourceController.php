<?php

namespace YusufGenc34\FilamentApiForge\Http\Controllers;

use YusufGenc34\FilamentApiForge\Http\Resources\ApiForgeJsonResource;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class ApiResourceController extends Controller
{
    public function __construct(
        protected ResourceDiscoveryService $discoveryService,
    ) {}

    /**
     * GET /{panelId}/{resourceSlug}
     *
     * List resources with Spatie Query Builder support for
     * filtering, sorting, includes and field selection.
     */
    public function index(Request $request, string $panelId, string $resourceSlug): AnonymousResourceCollection|JsonResponse
    {
        $resource = $this->resolveResource($panelId, $resourceSlug, 'index');

        if ($resource instanceof JsonResponse) {
            return $resource;
        }

        $modelClass = $resource['model_class'];
        $perPage = min(
            (int) $request->input('per_page', config('filament-api-forge.default_per_page', 15)),
            (int) config('filament-api-forge.max_per_page', 100)
        );

        $query = QueryBuilder::for($modelClass);

        // Apply allowed filters
        $allowedFilters = $this->discoveryService->getAllowedFilters($resource);
        if (! empty($allowedFilters)) {
            $query->allowedFilters(
                collect($allowedFilters)->map(fn (string $filter) => AllowedFilter::partial($filter))->toArray()
            );
        }

        // Apply allowed sorts
        $allowedSorts = $this->discoveryService->getAllowedSorts($resource);
        if (! empty($allowedSorts)) {
            $query->allowedSorts($allowedSorts);
        }

        // Apply allowed fields (must come before allowedIncludes per Spatie QB contract)
        $allowedFields = $this->discoveryService->getAllowedFields($resource);
        if (! empty($allowedFields)) {
            $query->allowedFields($allowedFields);
        }

        // Apply allowed includes (relations)
        $allowedIncludes = $this->discoveryService->getAllowedIncludes($resource);
        if (! empty($allowedIncludes)) {
            $query->allowedIncludes($allowedIncludes);
        }

        // Searchable
        $searchable = $resource['api_config']['searchable'] ?? [];
        if (! empty($searchable) && $request->has('search')) {
            // Escape LIKE metacharacters to prevent wildcard injection
            $searchTerm = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $request->input('search', ''));
            $query->where(function ($q) use ($searchable, $searchTerm) {
                foreach ($searchable as $i => $column) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->{$method}($column, 'LIKE', "%{$searchTerm}%");
                }
            });
        }

        $results = $query->paginate($perPage)->appends($request->query());

        return ApiForgeJsonResource::collection($results)->additional([
            'meta' => [
                'api_version' => config('filament-api-forge.api_version', 'v1'),
                'resource'    => $resource['plural_label'],
            ],
        ]);
    }

    /**
     * GET /{panelId}/{resourceSlug}/{recordId}
     *
     * Show a single resource.
     */
    public function show(Request $request, string $panelId, string $resourceSlug, string $recordId): ApiForgeJsonResource|JsonResponse
    {
        $resource = $this->resolveResource($panelId, $resourceSlug, 'show');

        if ($resource instanceof JsonResponse) {
            return $resource;
        }

        $modelClass = $resource['model_class'];

        $query = QueryBuilder::for($modelClass);

        // allowedFields must come before allowedIncludes (Spatie QB requirement)
        $allowedFields = $this->discoveryService->getAllowedFields($resource);
        if (! empty($allowedFields)) {
            $query->allowedFields($allowedFields);
        }

        $allowedIncludes = $this->discoveryService->getAllowedIncludes($resource);
        if (! empty($allowedIncludes)) {
            $query->allowedIncludes($allowedIncludes);
        }

        $record = $query->findOrFail($recordId);

        return new ApiForgeJsonResource($record);
    }

    /**
     * POST /{panelId}/{resourceSlug}
     *
     * Create a new resource. Validation rules are dynamically
     * extracted from the Filament Resource's form schema.
     */
    public function store(Request $request, string $panelId, string $resourceSlug): ApiForgeJsonResource|JsonResponse
    {
        $resource = $this->resolveResource($panelId, $resourceSlug, 'store');

        if ($resource instanceof JsonResponse) {
            return $resource;
        }

        $modelClass = $resource['model_class'];
        $resourceClass = $resource['resource_class'];

        // Extract validation rules from the Filament form schema
        $rules = $this->extractValidationRules($resourceClass);

        $validated = $request->validate($rules);

        $record = new $modelClass();
        $record->fill($validated);
        $record->save();

        return (new ApiForgeJsonResource($record))
            ->response()
            ->setStatusCode(201)
            ->getData(true)
            ? new ApiForgeJsonResource($record->fresh())
            : new ApiForgeJsonResource($record);
    }

    /**
     * PUT/PATCH /{panelId}/{resourceSlug}/{recordId}
     *
     * Update an existing resource.
     */
    public function update(Request $request, string $panelId, string $resourceSlug, string $recordId): ApiForgeJsonResource|JsonResponse
    {
        $resource = $this->resolveResource($panelId, $resourceSlug, 'update');

        if ($resource instanceof JsonResponse) {
            return $resource;
        }

        $modelClass = $resource['model_class'];
        $resourceClass = $resource['resource_class'];

        $record = $modelClass::findOrFail($recordId);

        // Extract validation rules, making them update-friendly
        $rules = $this->extractValidationRules($resourceClass, true);

        $validated = $request->validate($rules);

        $record->fill($validated);
        $record->save();

        return new ApiForgeJsonResource($record->fresh());
    }

    /**
     * DELETE /{panelId}/{resourceSlug}/{recordId}
     *
     * Delete a resource.
     */
    public function destroy(Request $request, string $panelId, string $resourceSlug, string $recordId): JsonResponse
    {
        $resource = $this->resolveResource($panelId, $resourceSlug, 'destroy');

        if ($resource instanceof JsonResponse) {
            return $resource;
        }

        $modelClass = $resource['model_class'];
        $record = $modelClass::findOrFail($recordId);
        $record->delete();

        return response()->json([
            'message' => 'Resource deleted successfully.',
            'deleted' => true,
        ]);
    }

    /**
     * HTTP method → required scope mapping.
     */
    protected const SCOPE_MAP = [
        'index'   => 'read',
        'show'    => 'read',
        'store'   => 'write',
        'update'  => 'write',
        'destroy' => 'delete',
    ];

    /**
     * Resolve a resource from the discovery service and validate method + scope access.
     */
    protected function resolveResource(string $panelId, string $resourceSlug, string $method): array|JsonResponse
    {
        $resource = $this->discoveryService->findResource($panelId, $resourceSlug);

        if (! $resource) {
            return response()->json([
                'message' => 'Resource not found.',
                'error'   => 'not_found',
            ], 404);
        }

        if (! $this->discoveryService->isMethodAllowed($resource, $method)) {
            return response()->json([
                'message' => "Method '{$method}' is not allowed for this resource.",
                'error'   => 'method_not_allowed',
            ], 405);
        }

        // Scope enforcement: does the token have the required scope for this method?
        $requiredScope = self::SCOPE_MAP[$method] ?? null;

        if ($requiredScope) {
            /** @var \YusufGenc34\FilamentApiForge\Models\ApiForgeToken|null $token */
            $token = request()->attributes->get('api_forge_token');

            if (! $token || ! $token->hasScope($requiredScope)) {
                return response()->json([
                    'message' => "This token does not have the required '{$requiredScope}' scope for this operation.",
                    'error'   => 'insufficient_scope',
                    'required_scope' => $requiredScope,
                ], 403);
            }

            // Resource-level access check: verify if the token is restricted to specific resources
            $allowedResources = $token->allowed_resources;

            if (! empty($allowedResources) && ! in_array($resourceSlug, $allowedResources)) {
                return response()->json([
                    'message' => "This token is not authorized to access the '{$resourceSlug}' resource.",
                    'error'   => 'resource_not_allowed',
                ], 403);
            }
        }

        return $resource;
    }

    /**
     * Extract validation rules for a resource.
     *
     * Priority order:
     *   1. apiConfig()['validation_rules'] — use if the developer has explicitly defined them
     *   2. apiConfig()['allowed_fields']   — generate basic rules from the allowed fields
     *   3. Model $fillable                  — last resort fallback
     */
    protected function extractValidationRules(string $resourceClass, bool $isUpdate = false): array
    {
        $apiConfig = $resourceClass::apiConfig();

        // 1. If the developer has explicitly defined validation_rules, use them directly
        if (! empty($apiConfig['validation_rules'])) {
            $rules = $apiConfig['validation_rules'];

            if ($isUpdate) {
                // Make all fields optional for updates
                return collect($rules)
                    ->mapWithKeys(function ($rule, $field) {
                        $ruleArray = is_array($rule) ? $rule : explode('|', $rule);
                        array_unshift($ruleArray, 'sometimes');
                        return [$field => $ruleArray];
                    })
                    ->toArray();
            }

            return $rules;
        }

        // 2. If allowed_fields is defined, use them as basic rules
        $allowedFields = $apiConfig['allowed_fields'] ?? [];

        if (! empty($allowedFields)) {
            return collect($allowedFields)
                ->mapWithKeys(fn (string $field) => [
                    $field => $isUpdate ? ['sometimes'] : ['nullable'],
                ])
                ->toArray();
        }

        // 3. Fallback from Model $fillable
        /** @var \Illuminate\Database\Eloquent\Model $modelClass */
        $modelClass = $resourceClass::getModel();
        $model      = new $modelClass();
        $fillable   = $model->getFillable();

        if (empty($fillable)) {
            return [];
        }

        return collect($fillable)
            ->mapWithKeys(fn (string $field) => [
                $field => $isUpdate ? ['sometimes'] : ['nullable'],
            ])
            ->toArray();
    }
}
