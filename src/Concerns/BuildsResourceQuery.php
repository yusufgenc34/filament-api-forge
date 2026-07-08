<?php

namespace YusufGenc34\FilamentApiForge\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

trait BuildsResourceQuery
{
    use ScopesTenant;

    /**
     * Build the Spatie QueryBuilder pipeline shared by index + export:
     * filters, sorts, fields, includes, full-text search and trashed handling.
     */
    protected function buildListQuery(array $resource, Request $request): QueryBuilder
    {
        $query = QueryBuilder::for($this->baseQueryFor($resource, $request));

        // Apply allowed filters
        $allowedFilters = $this->discoveryService->getAllowedFilters($resource);
        if (! empty($allowedFilters)) {
            $query->allowedFilters(
                ...collect($allowedFilters)->map(fn (string $filter) => AllowedFilter::partial($filter))->all()
            );
        }

        // Apply allowed sorts
        $allowedSorts = $this->discoveryService->getAllowedSorts($resource);
        if (! empty($allowedSorts)) {
            $query->allowedSorts(...$allowedSorts);
        }

        // Apply allowed fields (must come before allowedIncludes per Spatie QB contract)
        $allowedFields = $this->discoveryService->getAllowedFields($resource);
        if (! empty($allowedFields)) {
            $query->allowedFields(...$allowedFields);
        }

        // Apply allowed includes (relations)
        $allowedIncludes = $this->discoveryService->getAllowedIncludes($resource);
        if (! empty($allowedIncludes)) {
            $query->allowedIncludes(...$allowedIncludes);
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

        return $query;
    }

    /**
     * Base Eloquent query honoring the ?trashed=only|with parameter
     * for models using SoftDeletes.
     */
    protected function baseQueryFor(array $resource, Request $request): mixed
    {
        $modelClass = $resource['model_class'];

        $base = $modelClass::query();

        if ($this->modelUsesSoftDeletes($modelClass)) {
            match ($request->query('trashed')) {
                'only' => $base->onlyTrashed(),
                'with' => $base->withTrashed(),
                default => null,
            };
        }

        return $this->applyTenantScope($base, $resource);
    }

    protected function modelUsesSoftDeletes(string $modelClass): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($modelClass));
    }
}
