<?php

namespace YusufGenc34\FilamentApiForge\Http\Controllers;

use YusufGenc34\FilamentApiForge\Http\Resources\ApiForgeJsonResource;
use YusufGenc34\FilamentApiForge\Services\FileUploadService;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use YusufGenc34\FilamentApiForge\Traits\ApiForgeHooks;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreating;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdating;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleting;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleted;
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
        $apiConfig = $resource['api_config'];

        $rules = $this->extractValidationRules($resourceClass, false, $apiConfig);
        $rules = $this->mergeUploadRules($rules, $apiConfig);

        $data = $request->validate($rules);

        // Strip upload fields from model data — they contain file objects
        $uploadFields = array_keys($apiConfig['uploads'] ?? []);
        $modelData = array_diff_key($data, array_flip($uploadFields));

        $eventsEnabled = config('filament-api-forge.events.enabled', true);

        // Before-create hooks and event
        $modelData = $this->executeBeforeHooks($resourceClass, 'beforeCreate', $modelData);

        if ($eventsEnabled) {
            ApiResourceCreating::dispatch($resourceClass, $modelData);
        }

        $record = new $modelClass();
        $record->fill($modelData);
        $record->save();

        // Handle file uploads if configured
        $uploadResults = $this->processFileUploads($record, $apiConfig, $request);

        // After-create hooks and event
        if ($eventsEnabled) {
            ApiResourceCreated::dispatch($resourceClass, $record, $data);
        }

        $this->executeAfterHooks($resourceClass, 'afterCreate', $record, $data);

        $fresh = $record->fresh();
        $resource = new ApiForgeJsonResource($fresh);

        if (! empty($uploadResults)) {
            $resource->additional(['_uploads' => $uploadResults]);
        }

        return $resource;
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
        $apiConfig = $resource['api_config'];

        $record = $modelClass::findOrFail($recordId);

        $rules = $this->extractValidationRules($resourceClass, true, $apiConfig);
        $rules = $this->mergeUploadRules($rules, $apiConfig);

        $data = $request->validate($rules);

        // Strip upload fields from model data — they contain file objects
        $uploadFields = array_keys($apiConfig['uploads'] ?? []);
        $modelData = array_diff_key($data, array_flip($uploadFields));

        $eventsEnabled = config('filament-api-forge.events.enabled', true);

        // Before-update hooks and event
        $modelData = $this->executeBeforeHooks($resourceClass, 'beforeUpdate', $record, $modelData);

        if ($eventsEnabled) {
            ApiResourceUpdating::dispatch($resourceClass, $record, $modelData);
        }

        $record->fill($modelData);
        $record->save();

        // Handle file uploads if configured
        $uploadResults = $this->processFileUploads($record, $apiConfig, $request);

        // After-update hooks and event
        if ($eventsEnabled) {
            ApiResourceUpdated::dispatch($resourceClass, $record, $data);
        }

        $this->executeAfterHooks($resourceClass, 'afterUpdate', $record, $data);

        $resource = new ApiForgeJsonResource($record->fresh());

        if (! empty($uploadResults)) {
            $resource->additional(['_uploads' => $uploadResults]);
        }

        return $resource;
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
        $resourceClass = $resource['resource_class'];
        $record = $modelClass::findOrFail($recordId);

        $eventsEnabled = config('filament-api-forge.events.enabled', true);

        // Before-delete hooks and event
        $this->executeVoidHooks($resourceClass, 'beforeDelete', $record);

        if ($eventsEnabled) {
            ApiResourceDeleting::dispatch($resourceClass, $record);
        }

        $record->delete();

        // After-delete hooks and event
        if ($eventsEnabled) {
            ApiResourceDeleted::dispatch($resourceClass, $record);
        }

        $this->executeVoidHooks($resourceClass, 'afterDelete', $record);

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
     * Execute "before" style hooks that transform data.
     */
    protected function executeBeforeHooks(string $resourceClass, string $hook, ...$args): array
    {
        $eventsEnabled = config('filament-api-forge.events.enabled', true);

        if (! $eventsEnabled || ! class_exists($resourceClass)) {
            return $args[count($args) - 1];
        }

        if ($this->usesApiForgeHooks($resourceClass) && ! $resourceClass::shouldSkipHooks()) {
            return $resourceClass::{$hook}(...$args);
        }

        return $args[count($args) - 1];
    }

    /**
     * Execute "after" style hooks that receive $record and $data.
     */
    protected function executeAfterHooks(string $resourceClass, string $hook, ...$args): void
    {
        if (! config('filament-api-forge.events.enabled', true) || ! class_exists($resourceClass)) {
            return;
        }

        if ($this->usesApiForgeHooks($resourceClass) && ! $resourceClass::shouldSkipHooks()) {
            $resourceClass::{$hook}(...$args);
        }
    }

    /**
     * Execute void hooks (beforeDelete/afterDelete).
     */
    protected function executeVoidHooks(string $resourceClass, string $hook, ...$args): void
    {
        if (! config('filament-api-forge.events.enabled', true) || ! class_exists($resourceClass)) {
            return;
        }

        if ($this->usesApiForgeHooks($resourceClass) && ! $resourceClass::shouldSkipHooks()) {
            $resourceClass::{$hook}(...$args);
        }
    }

    /**
     * Check if a resource class uses the ApiForgeHooks trait.
     */
    protected function usesApiForgeHooks(string $resourceClass): bool
    {
        if (! class_exists($resourceClass)) {
            return false;
        }

        return in_array(ApiForgeHooks::class, class_uses($resourceClass));
    }

    /**
     * Merge file upload validation rules from apiConfig into existing rules.
     */
    protected function mergeUploadRules(array $rules, array $apiConfig): array
    {
        $uploads = $apiConfig['uploads'] ?? [];

        if (empty($uploads)) {
            return $rules;
        }

        $uploadService = app(FileUploadService::class);
        $uploadRules = $uploadService->getValidationRules($uploads);

        // Upload fields need file-specific rules; override any generic defaults
        foreach ($uploadRules as $field => $fileRules) {
            $rules[$field] = $fileRules;
        }

        return $rules;
    }

    /**
     * Process file uploads for a record after save.
     */
    protected function processFileUploads(mixed $record, array $apiConfig, Request $request): array
    {
        $uploads = $apiConfig['uploads'] ?? [];

        if (empty($uploads)) {
            return [];
        }

        // Check if any configured upload field has a file
        $hasFiles = false;
        foreach (array_keys($uploads) as $field) {
            if ($request->hasFile($field)) {
                $hasFiles = true;
                break;
            }
        }

        if (! $hasFiles) {
            return [];
        }

        $uploadService = app(FileUploadService::class);

        return $uploadService->handleUploads($record, $uploads, $request);
    }

    /**
     * Extract validation rules for a resource.
     *
     * Priority order:
     *   1. apiConfig()['validation_rules'] — use if the developer has explicitly defined them
     *   2. apiConfig()['allowed_fields']   — generate basic rules from the allowed fields
     *   3. Model $fillable                  — last resort fallback
     */
    protected function extractValidationRules(string $resourceClass, bool $isUpdate = false, ?array $apiConfig = null): array
    {
        $apiConfig ??= $resourceClass::apiConfig();

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
