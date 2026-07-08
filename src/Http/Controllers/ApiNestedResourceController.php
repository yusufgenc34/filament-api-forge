<?php

namespace YusufGenc34\FilamentApiForge\Http\Controllers;

use YusufGenc34\FilamentApiForge\Concerns\ExecutesApiHooks;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreating;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleted;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleting;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdating;
use YusufGenc34\FilamentApiForge\Http\Resources\ApiForgeJsonResource;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class ApiNestedResourceController extends Controller
{
    use ExecutesApiHooks;

    public function __construct(
        protected ResourceDiscoveryService $discoveryService,
    ) {}

    public function index(Request $request, string $panelId, string $resourceSlug, string $recordId, string $childSlug): AnonymousResourceCollection|JsonResponse
    {
        $parent = $this->resolveParent($panelId, $resourceSlug, $recordId);
        if ($parent instanceof JsonResponse) return $parent;

        $relation = $this->resolveRelation($parent, $childSlug, 'index');
        if ($relation instanceof JsonResponse) return $relation;

        $parentModel = $parent['_record'];
        $relationName = $relation['relation_name'];

        $perPage = min(
            (int) $request->input('per_page', config('filament-api-forge.pagination.default_per_page', 15)),
            (int) config('filament-api-forge.pagination.max_per_page', 100)
        );

        $query = QueryBuilder::for($parentModel->{$relationName}());

        $allowedFilters = $relation['allowed_filters'] ?? [];
        if (! empty($allowedFilters)) {
            $query->allowedFilters(
                ...collect($allowedFilters)->map(fn (string $f) => AllowedFilter::partial($f))->all()
            );
        }

        $allowedSorts = $relation['allowed_sorts'] ?? [];
        if (! empty($allowedSorts)) {
            $query->allowedSorts(...$allowedSorts);
        }

        // allowedFields must come before allowedIncludes (Spatie QB requirement)
        $allowedFields = $relation['allowed_fields'] ?? [];
        if (! empty($allowedFields)) {
            $query->allowedFields(...$allowedFields);
        }

        $allowedIncludes = $relation['allowed_includes'] ?? [];
        if (! empty($allowedIncludes)) {
            $query->allowedIncludes(...$allowedIncludes);
        }

        $results = $query->paginate($perPage)->appends($request->query());

        return ApiForgeJsonResource::collection($results)->additional([
            'meta' => ['api_version' => config('filament-api-forge.api_version', 'v1')],
        ]);
    }

    public function show(Request $request, string $panelId, string $resourceSlug, string $recordId, string $childSlug, string $childId): ApiForgeJsonResource|JsonResponse
    {
        $parent = $this->resolveParent($panelId, $resourceSlug, $recordId);
        if ($parent instanceof JsonResponse) return $parent;

        $relation = $this->resolveRelation($parent, $childSlug, 'show');
        if ($relation instanceof JsonResponse) return $relation;

        $parentModel = $parent['_record'];

        $query = QueryBuilder::for($parentModel->{$relation['relation_name']}());

        // allowedFields must come before allowedIncludes (Spatie QB requirement)
        $allowedFields = $relation['allowed_fields'] ?? [];
        if (! empty($allowedFields)) {
            $query->allowedFields(...$allowedFields);
        }

        $allowedIncludes = $relation['allowed_includes'] ?? [];
        if (! empty($allowedIncludes)) {
            $query->allowedIncludes(...$allowedIncludes);
        }

        $child = $query->findOrFail($childId);

        return new ApiForgeJsonResource($child);
    }

    public function store(Request $request, string $panelId, string $resourceSlug, string $recordId, string $childSlug): ApiForgeJsonResource|JsonResponse
    {
        $parent = $this->resolveParent($panelId, $resourceSlug, $recordId);
        if ($parent instanceof JsonResponse) return $parent;

        $relation = $this->resolveRelation($parent, $childSlug, 'store');
        if ($relation instanceof JsonResponse) return $relation;

        $parentModel = $parent['_record'];
        $resourceClass = $parent['resource_class'];
        $data = $request->validate($relation['validation_rules'] ?? []);

        $eventsEnabled = $this->eventsEnabled();

        if ($eventsEnabled) {
            ApiResourceCreating::dispatch($resourceClass, $data);
        }

        $child = $parentModel->{$relation['relation_name']}()->create($data);

        if ($eventsEnabled) {
            ApiResourceCreated::dispatch($resourceClass, $child, $data);
        }

        return (new ApiForgeJsonResource($child));
    }

    public function update(Request $request, string $panelId, string $resourceSlug, string $recordId, string $childSlug, string $childId): ApiForgeJsonResource|JsonResponse
    {
        $parent = $this->resolveParent($panelId, $resourceSlug, $recordId);
        if ($parent instanceof JsonResponse) return $parent;

        $relation = $this->resolveRelation($parent, $childSlug, 'update');
        if ($relation instanceof JsonResponse) return $relation;

        $parentModel = $parent['_record'];
        $resourceClass = $parent['resource_class'];
        $child = $parentModel->{$relation['relation_name']}()->findOrFail($childId);

        $rules = $relation['validation_rules'] ?? [];
        $rules = collect($rules)->mapWithKeys(fn ($r, $k) => [$k => array_merge(['sometimes'], (array) $r)])->toArray();
        $data = $request->validate($rules);

        $eventsEnabled = $this->eventsEnabled();

        if ($eventsEnabled) {
            ApiResourceUpdating::dispatch($resourceClass, $child, $data);
        }

        $child->update($data);

        if ($eventsEnabled) {
            ApiResourceUpdated::dispatch($resourceClass, $child, $data);
        }

        return new ApiForgeJsonResource($child->fresh());
    }

    public function destroy(Request $request, string $panelId, string $resourceSlug, string $recordId, string $childSlug, string $childId): JsonResponse
    {
        $parent = $this->resolveParent($panelId, $resourceSlug, $recordId);
        if ($parent instanceof JsonResponse) return $parent;

        $relation = $this->resolveRelation($parent, $childSlug, 'destroy');
        if ($relation instanceof JsonResponse) return $relation;

        $parentModel = $parent['_record'];
        $resourceClass = $parent['resource_class'];
        $child = $parentModel->{$relation['relation_name']}()->findOrFail($childId);

        $eventsEnabled = $this->eventsEnabled();

        if ($eventsEnabled) {
            ApiResourceDeleting::dispatch($resourceClass, $child);
        }

        $child->delete();

        if ($eventsEnabled) {
            ApiResourceDeleted::dispatch($resourceClass, $child);
        }

        return response()->json(['message' => 'Resource deleted successfully.', 'deleted' => true]);
    }

    protected function resolveParent(string $panelId, string $resourceSlug, string $recordId): array|JsonResponse
    {
        // Parent-level transformers do not apply to child records
        ApiForgeJsonResource::withTransformer(null);

        $resource = $this->discoveryService->findResource($panelId, $resourceSlug);

        if (! $resource) {
            return response()->json(['message' => 'Resource not found.', 'error' => 'not_found'], 404);
        }

        $model = $resource['model_class']::find($recordId);

        if (! $model) {
            return response()->json(['message' => 'Record not found.', 'error' => 'not_found'], 404);
        }

        $resource['_record'] = $model;

        return $resource;
    }

    protected function resolveRelation(array $parent, string $childSlug, string $method): array|JsonResponse
    {
        $relations = $parent['api_config']['relations'] ?? [];

        if (! isset($relations[$childSlug])) {
            return response()->json(['message' => 'Relation not found.', 'error' => 'not_found'], 404);
        }

        $relation = $relations[$childSlug];

        $allowedMethods = $relation['allowed_methods'] ?? ['index', 'show', 'store', 'update', 'destroy'];

        if (! in_array($method, $allowedMethods)) {
            return response()->json([
                'message' => "Method '{$method}' is not allowed for this relation.",
                'error'   => 'method_not_allowed',
            ], 405);
        }

        return $relation;
    }
}
