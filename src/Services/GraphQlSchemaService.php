<?php

namespace YusufGenc34\FilamentApiForge\Services;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use YusufGenc34\FilamentApiForge\Concerns\ExecutesApiHooks;
use YusufGenc34\FilamentApiForge\Concerns\ExtractsApiValidationRules;
use YusufGenc34\FilamentApiForge\Concerns\ScopesTenant;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreating;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleted;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleting;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdating;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Builds an executable GraphQL schema from the discovered HasApi resources.
 *
 * Queries:   post(id: ID!)          posts(page, perPage, search, <filters>)
 * Mutations: createPost(...fields)  updatePost(id, ...fields)  deletePost(id)
 *
 * Scope enforcement mirrors REST: read for queries, write for create/update,
 * delete for deletes — including apiConfig()['scope_map'] overrides.
 */
class GraphQlSchemaService
{
    use ExecutesApiHooks;
    use ExtractsApiValidationRules;
    use ScopesTenant;

    /** @var array<string, ObjectType> */
    protected array $typeCache = [];

    public function __construct(
        protected ResourceDiscoveryService $discoveryService,
    ) {}

    public function schema(): Schema
    {
        $queryFields    = [];
        $mutationFields = [];

        foreach ($this->discoveryService->discoverForVersion() as $resource) {
            $allowed  = $resource['api_config']['allowed_methods'] ?? [];
            $type     = $this->objectType($resource);
            $singular = Str::camel(Str::singular($resource['slug']));
            $plural   = Str::camel(Str::plural($resource['slug']));
            $studly   = Str::studly(Str::singular($resource['slug']));

            if (in_array('show', $allowed)) {
                $queryFields[$singular] = [
                    'type' => $type,
                    'args' => ['id' => Type::nonNull(Type::id())],
                    'resolve' => fn ($root, array $args) => $this->resolveShow($resource, $args),
                ];
            }

            if (in_array('index', $allowed)) {
                $queryFields[$plural] = [
                    'type' => $this->pageType($resource, $type),
                    'args' => array_merge(
                        [
                            'page'    => Type::int(),
                            'perPage' => Type::int(),
                            'search'  => Type::string(),
                        ],
                        $this->filterArgs($resource),
                    ),
                    'resolve' => fn ($root, array $args) => $this->resolveIndex($resource, $args),
                ];
            }

            if (in_array('store', $allowed)) {
                $mutationFields["create{$studly}"] = [
                    'type' => $type,
                    'args' => $this->fieldArgs($resource),
                    'resolve' => fn ($root, array $args) => $this->resolveCreate($resource, $args),
                ];
            }

            if (in_array('update', $allowed)) {
                $mutationFields["update{$studly}"] = [
                    'type' => $type,
                    'args' => array_merge(['id' => Type::nonNull(Type::id())], $this->fieldArgs($resource)),
                    'resolve' => fn ($root, array $args) => $this->resolveUpdate($resource, $args),
                ];
            }

            if (in_array('destroy', $allowed)) {
                $mutationFields["delete{$studly}"] = [
                    'type' => Type::boolean(),
                    'args' => ['id' => Type::nonNull(Type::id())],
                    'resolve' => fn ($root, array $args) => $this->resolveDelete($resource, $args),
                ];
            }
        }

        if (empty($queryFields)) {
            $queryFields['_service'] = [
                'type'    => Type::string(),
                'resolve' => fn () => 'filament-api-forge',
            ];
        }

        $config = [
            'query' => new ObjectType(['name' => 'Query', 'fields' => $queryFields]),
        ];

        if (! empty($mutationFields)) {
            $config['mutation'] = new ObjectType(['name' => 'Mutation', 'fields' => $mutationFields]);
        }

        return new Schema($config);
    }

    // ── Type builders ─────────────────────────────────────────────────────

    protected function objectType(array $resource): ObjectType
    {
        $name = Str::studly(Str::singular($resource['slug']));

        if (isset($this->typeCache[$name])) {
            return $this->typeCache[$name];
        }

        $fields = ['id' => Type::id()];

        $model = new ($resource['model_class'])();

        foreach ($model->getFillable() as $field) {
            $fields[$field] = Type::string();
        }

        if ($model->usesTimestamps()) {
            $fields['created_at'] = Type::string();
            $fields['updated_at'] = Type::string();
        }

        return $this->typeCache[$name] = new ObjectType([
            'name'   => $name,
            'fields' => $fields,
            'resolveField' => function ($record, array $args, $context, $info) {
                $value = $record->getAttribute($info->fieldName);

                return is_scalar($value) || $value === null ? $value : (string) $value;
            },
        ]);
    }

    protected function pageType(array $resource, ObjectType $type): ObjectType
    {
        $name = $type->name . 'Page';

        return $this->typeCache[$name] ??= new ObjectType([
            'name'   => $name,
            'fields' => [
                'data'        => Type::listOf($type),
                'total'       => Type::int(),
                'perPage'     => Type::int(),
                'currentPage' => Type::int(),
                'lastPage'    => Type::int(),
            ],
        ]);
    }

    protected function filterArgs(array $resource): array
    {
        $args = [];

        foreach ($resource['api_config']['allowed_filters'] ?? [] as $filter) {
            if (preg_match('/^[_A-Za-z][_0-9A-Za-z]*$/', $filter)) {
                $args[$filter] = Type::string();
            }
        }

        return $args;
    }

    protected function fieldArgs(array $resource): array
    {
        $args = [];

        $model = new ($resource['model_class'])();

        foreach ($model->getFillable() as $field) {
            if (preg_match('/^[_A-Za-z][_0-9A-Za-z]*$/', $field)) {
                $args[$field] = Type::string();
            }
        }

        return $args;
    }

    // ── Resolvers ─────────────────────────────────────────────────────────

    protected function resolveShow(array $resource, array $args): mixed
    {
        $this->enforceScope($resource, 'show');

        return $this->tenantScopedQuery($resource['model_class'], $resource)->findOrFail($args['id']);
    }

    protected function resolveIndex(array $resource, array $args): array
    {
        $this->enforceScope($resource, 'index');

        $query = $this->tenantScopedQuery($resource['model_class'], $resource);

        foreach ($resource['api_config']['allowed_filters'] ?? [] as $filter) {
            if (isset($args[$filter])) {
                $query->where($filter, 'LIKE', '%' . addcslashes($args[$filter], '\\%_') . '%');
            }
        }

        $searchable = $resource['api_config']['searchable'] ?? [];
        if (! empty($searchable) && isset($args['search'])) {
            $term = addcslashes($args['search'], '\\%_');
            $query->where(function ($q) use ($searchable, $term) {
                foreach ($searchable as $i => $column) {
                    $q->{$i === 0 ? 'where' : 'orWhere'}($column, 'LIKE', "%{$term}%");
                }
            });
        }

        $perPage = min(
            $args['perPage'] ?? (int) config('filament-api-forge.pagination.default_per_page', 15),
            (int) config('filament-api-forge.pagination.max_per_page', 100)
        );

        $page = $query->paginate($perPage, ['*'], 'page', $args['page'] ?? 1);

        return [
            'data'        => $page->items(),
            'total'       => $page->total(),
            'perPage'     => $page->perPage(),
            'currentPage' => $page->currentPage(),
            'lastPage'    => $page->lastPage(),
        ];
    }

    protected function resolveCreate(array $resource, array $args): mixed
    {
        $this->enforceScope($resource, 'store');

        $resourceClass = $resource['resource_class'];
        $rules = $this->extractValidationRules($resourceClass, false, $resource['api_config'], $resource['model_class']);

        $data = Validator::make($args, $rules)->validate();
        $data = $this->executeBeforeHooks($resourceClass, 'beforeCreate', $data);

        if ($this->eventsEnabled()) {
            ApiResourceCreating::dispatch($resourceClass, $data);
        }

        $record = new ($resource['model_class'])();
        $record->fill($data);
        $this->stampTenant($record, $resource);
        $record->save();

        if ($this->eventsEnabled()) {
            ApiResourceCreated::dispatch($resourceClass, $record, $data);
        }

        $this->executeAfterHooks($resourceClass, 'afterCreate', $record, $data);

        return $record->fresh();
    }

    protected function resolveUpdate(array $resource, array $args): mixed
    {
        $this->enforceScope($resource, 'update');

        $resourceClass = $resource['resource_class'];
        $record = $this->tenantScopedQuery($resource['model_class'], $resource)->findOrFail($args['id']);
        unset($args['id']);

        $rules = $this->extractValidationRules($resourceClass, true, $resource['api_config'], $resource['model_class']);

        $data = Validator::make($args, $rules)->validate();
        $data = $this->executeBeforeHooks($resourceClass, 'beforeUpdate', $record, $data);

        if ($this->eventsEnabled()) {
            ApiResourceUpdating::dispatch($resourceClass, $record, $data);
        }

        $record->fill($data);
        $record->save();

        if ($this->eventsEnabled()) {
            ApiResourceUpdated::dispatch($resourceClass, $record, $data);
        }

        $this->executeAfterHooks($resourceClass, 'afterUpdate', $record, $data);

        return $record->fresh();
    }

    protected function resolveDelete(array $resource, array $args): bool
    {
        $this->enforceScope($resource, 'destroy');

        $resourceClass = $resource['resource_class'];
        $record = $this->tenantScopedQuery($resource['model_class'], $resource)->findOrFail($args['id']);

        $this->executeVoidHooks($resourceClass, 'beforeDelete', $record);

        if ($this->eventsEnabled()) {
            ApiResourceDeleting::dispatch($resourceClass, $record);
        }

        $record->delete();

        if ($this->eventsEnabled()) {
            ApiResourceDeleted::dispatch($resourceClass, $record);
        }

        $this->executeVoidHooks($resourceClass, 'afterDelete', $record);

        return true;
    }

    // ── Scope enforcement ─────────────────────────────────────────────────

    protected const SCOPE_MAP = [
        'index'   => 'read',
        'show'    => 'read',
        'store'   => 'write',
        'update'  => 'write',
        'destroy' => 'delete',
    ];

    protected function enforceScope(array $resource, string $method): void
    {
        if (! config('filament-api-forge.auth.enabled', true)) {
            return;
        }

        $requiredScope = $resource['api_config']['scope_map'][$method]
            ?? self::SCOPE_MAP[$method]
            ?? null;

        if (! $requiredScope) {
            return;
        }

        $token = request()->attributes->get('api_forge_token');

        if (! $token || ! $token->hasScope($requiredScope)) {
            throw new \GraphQL\Error\UserError(
                "This token does not have the required '{$requiredScope}' scope for this operation."
            );
        }

        $allowedResources = $token->allowed_resources;

        if (! empty($allowedResources) && ! in_array($resource['slug'], $allowedResources)) {
            throw new \GraphQL\Error\UserError(
                "This token is not authorized to access the '{$resource['slug']}' resource."
            );
        }
    }
}
