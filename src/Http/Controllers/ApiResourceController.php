<?php

namespace YusufGenc34\FilamentApiForge\Http\Controllers;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use YusufGenc34\FilamentApiForge\Concerns\BuildsResourceQuery;
use YusufGenc34\FilamentApiForge\Concerns\ExecutesApiHooks;
use YusufGenc34\FilamentApiForge\Concerns\ExtractsApiValidationRules;
use YusufGenc34\FilamentApiForge\Concerns\ResolvesApiResource;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreating;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleted;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleting;
use YusufGenc34\FilamentApiForge\Events\ApiResourceForceDeleted;
use YusufGenc34\FilamentApiForge\Events\ApiResourceForceDeleting;
use YusufGenc34\FilamentApiForge\Events\ApiResourceRestored;
use YusufGenc34\FilamentApiForge\Events\ApiResourceRestoring;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdating;
use YusufGenc34\FilamentApiForge\Http\Resources\ApiForgeJsonResource;
use YusufGenc34\FilamentApiForge\Services\FileUploadService;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;

class ApiResourceController extends Controller
{
    use BuildsResourceQuery;
    use ExecutesApiHooks;
    use ExtractsApiValidationRules;
    use ResolvesApiResource;

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

        $perPage = min(
            (int) $request->input('per_page', config('filament-api-forge.pagination.default_per_page', 15)),
            (int) config('filament-api-forge.pagination.max_per_page', 100)
        );

        $results = $this->buildListQuery($resource, $request)
            ->paginate($perPage)
            ->appends($request->query());

        return $this->makeJsonResponse($resource, $results, true);
    }

    /**
     * GET /{panelId}/{resourceSlug}/{recordId}
     *
     * Show a single resource.
     */
    public function show(Request $request, string $panelId, string $resourceSlug, string $recordId): mixed
    {
        $resource = $this->resolveResource($panelId, $resourceSlug, 'show');

        if ($resource instanceof JsonResponse) {
            return $resource;
        }

        $query = QueryBuilder::for($this->baseQueryFor($resource, $request));

        // allowedFields must come before allowedIncludes (Spatie QB requirement)
        $allowedFields = $this->discoveryService->getAllowedFields($resource);
        if (! empty($allowedFields)) {
            $query->allowedFields(...$allowedFields);
        }

        $allowedIncludes = $this->discoveryService->getAllowedIncludes($resource);
        if (! empty($allowedIncludes)) {
            $query->allowedIncludes(...$allowedIncludes);
        }

        $record = $query->findOrFail($recordId);

        $policyError = $this->checkPolicy($resource, 'show', $record);
        if ($policyError) {
            return $policyError;
        }

        return $this->makeJsonResponse($resource, $record);
    }

    /**
     * POST /{panelId}/{resourceSlug}
     *
     * Create a new resource. Validation rules are dynamically
     * extracted from the Filament Resource's form schema.
     */
    public function store(Request $request, string $panelId, string $resourceSlug): mixed
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

        $eventsEnabled = $this->eventsEnabled();

        // Before-create hooks and event
        $modelData = $this->executeBeforeHooks($resourceClass, 'beforeCreate', $modelData);

        if ($eventsEnabled) {
            ApiResourceCreating::dispatch($resourceClass, $modelData);
        }

        $record = new $modelClass;
        $record->fill($modelData);
        $this->stampTenant($record, $resource);
        $record->save();
        $this->syncRelationships($record, $apiConfig, $data);

        // Handle file uploads if configured
        $uploadResults = $this->processFileUploads($record, $apiConfig, $request);

        // After-create hooks and event
        if ($eventsEnabled) {
            ApiResourceCreated::dispatch($resourceClass, $record, $data);
        }

        $this->executeAfterHooks($resourceClass, 'afterCreate', $record, $data);

        $responseObj = $this->makeJsonResponse($resource, $record->fresh());

        if (! empty($uploadResults) && method_exists($responseObj, 'additional')) {
            $responseObj->additional(['_uploads' => $uploadResults]);
        }

        return $responseObj;
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

        $record = $this->tenantScopedQuery($modelClass, $resource)->findOrFail($recordId);

        $policyError = $this->checkPolicy($resource, 'update', $record);
        if ($policyError) {
            return $policyError;
        }

        $rules = $this->extractValidationRules($resourceClass, true, $apiConfig);
        $rules = $this->mergeUploadRules($rules, $apiConfig);

        $data = $request->validate($rules);

        // Strip upload fields from model data — they contain file objects
        $uploadFields = array_keys($apiConfig['uploads'] ?? []);
        $modelData = array_diff_key($data, array_flip($uploadFields));

        $eventsEnabled = $this->eventsEnabled();

        // Before-update hooks and event
        $modelData = $this->executeBeforeHooks($resourceClass, 'beforeUpdate', $record, $modelData);

        if ($eventsEnabled) {
            ApiResourceUpdating::dispatch($resourceClass, $record, $modelData);
        }

        $record->fill($modelData);
        $record->save();
        $this->syncRelationships($record, $apiConfig, $data);

        // Handle file uploads if configured
        $uploadResults = $this->processFileUploads($record, $apiConfig, $request);

        // After-update hooks and event
        if ($eventsEnabled) {
            ApiResourceUpdated::dispatch($resourceClass, $record, $data);
        }

        $this->executeAfterHooks($resourceClass, 'afterUpdate', $record, $data);

        $responseObj = $this->makeJsonResponse($resource, $record->fresh());

        if (! empty($uploadResults) && method_exists($responseObj, 'additional')) {
            $responseObj->additional(['_uploads' => $uploadResults]);
        }

        return $responseObj;
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
        $record = $this->tenantScopedQuery($modelClass, $resource)->findOrFail($recordId);

        $policyError = $this->checkPolicy($resource, 'destroy', $record);
        if ($policyError) {
            return $policyError;
        }

        $eventsEnabled = $this->eventsEnabled();

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
     * POST /{panelId}/{resourceSlug}/{recordId}/restore
     *
     * Restore a soft-deleted resource.
     */
    public function restore(Request $request, string $panelId, string $resourceSlug, string $recordId): mixed
    {
        $resource = $this->resolveResource($panelId, $resourceSlug, 'restore');

        if ($resource instanceof JsonResponse) {
            return $resource;
        }

        $modelClass = $resource['model_class'];
        $resourceClass = $resource['resource_class'];

        if (! $this->modelUsesSoftDeletes($modelClass)) {
            return response()->json([
                'message' => 'This resource does not support soft deletes.',
                'error' => 'method_not_allowed',
            ], 405);
        }

        $record = $this->tenantScopedQuery($modelClass, $resource)->onlyTrashed()->findOrFail($recordId);

        $policyError = $this->checkPolicy($resource, 'restore', $record);
        if ($policyError) {
            return $policyError;
        }

        $eventsEnabled = $this->eventsEnabled();

        $this->executeVoidHooks($resourceClass, 'beforeRestore', $record);

        if ($eventsEnabled) {
            ApiResourceRestoring::dispatch($resourceClass, $record);
        }

        $record->restore();

        if ($eventsEnabled) {
            ApiResourceRestored::dispatch($resourceClass, $record);
        }

        $this->executeVoidHooks($resourceClass, 'afterRestore', $record);

        return $this->makeJsonResponse($resource, $record->fresh());
    }

    /**
     * Build the JSON response object using custom api_resource or default ApiForgeJsonResource.
     */
    protected function makeJsonResponse(array $resource, mixed $data, bool $isCollection = false): mixed
    {
        $customResourceClass = $resource['api_config']['api_resource'] ?? null;
        $jsonResourceClass = $customResourceClass ?? ApiForgeJsonResource::class;

        if ($isCollection) {
            return $jsonResourceClass::collection($data)->additional([
                'meta' => [
                    'api_version' => config('filament-api-forge.api_version', 'v1'),
                    'resource' => $resource['plural_label'],
                ],
            ]);
        }

        return new $jsonResourceClass($data);
    }

    /**
     * DELETE /{panelId}/{resourceSlug}/{recordId}/force
     *
     * Permanently delete a (possibly soft-deleted) resource.
     */
    public function forceDestroy(Request $request, string $panelId, string $resourceSlug, string $recordId): JsonResponse
    {
        $resource = $this->resolveResource($panelId, $resourceSlug, 'forceDelete');

        if ($resource instanceof JsonResponse) {
            return $resource;
        }

        $modelClass = $resource['model_class'];
        $resourceClass = $resource['resource_class'];

        if (! $this->modelUsesSoftDeletes($modelClass)) {
            return response()->json([
                'message' => 'This resource does not support soft deletes.',
                'error' => 'method_not_allowed',
            ], 405);
        }

        $record = $this->tenantScopedQuery($modelClass, $resource)->withTrashed()->findOrFail($recordId);

        $policyError = $this->checkPolicy($resource, 'forceDelete', $record);
        if ($policyError) {
            return $policyError;
        }

        $eventsEnabled = $this->eventsEnabled();

        $this->executeVoidHooks($resourceClass, 'beforeForceDelete', $record);

        if ($eventsEnabled) {
            ApiResourceForceDeleting::dispatch($resourceClass, $record);
        }

        $record->forceDelete();

        if ($eventsEnabled) {
            ApiResourceForceDeleted::dispatch($resourceClass, $record);
        }

        $this->executeVoidHooks($resourceClass, 'afterForceDelete', $record);

        return response()->json([
            'message' => 'Resource permanently deleted.',
            'deleted' => true,
        ]);
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
     * Sync Eloquent relationships (BelongsToMany, MorphToMany) if present in payload.
     */
    protected function syncRelationships(mixed $record, array $apiConfig, array $data): void
    {
        $relationsConfig = $apiConfig['sync_relations'] ?? true;
        if (! $relationsConfig) {
            return;
        }

        foreach ($data as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (method_exists($record, $key)) {
                try {
                    $relation = $record->{$key}();

                    if ($relation instanceof BelongsToMany
                        || $relation instanceof MorphToMany
                        || method_exists($relation, 'sync')) {
                        $relation->sync($value);
                    }
                } catch (\Throwable) {
                    // Ignore non-relationship methods or errors gracefully
                }
            }
        }
    }
}
