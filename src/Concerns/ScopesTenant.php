<?php

namespace YusufGenc34\FilamentApiForge\Concerns;

trait ScopesTenant
{
    /**
     * The tenant column declared in apiConfig()['tenant_column'], or null
     * when multi-tenancy does not apply to this resource.
     */
    protected function tenantColumn(array $resource): ?string
    {
        if (! config('filament-api-forge.multi_tenant.enabled', true)) {
            return null;
        }

        return $resource['api_config']['tenant_column'] ?? null;
    }

    /**
     * The tenant bound to the current token, if any.
     */
    protected function currentTenantId(): ?string
    {
        return request()->attributes->get('api_forge_token')?->tenant_id;
    }

    /**
     * Constrain a query to the token's tenant when both the resource
     * declares a tenant_column and the token carries a tenant_id.
     */
    protected function applyTenantScope(mixed $query, array $resource): mixed
    {
        $column = $this->tenantColumn($resource);
        $tenant = $this->currentTenantId();

        if ($column && $tenant !== null) {
            $query->where($column, $tenant);
        }

        return $query;
    }

    /**
     * Stamp the token's tenant onto a record about to be created.
     */
    protected function stampTenant(mixed $record, array $resource): void
    {
        $column = $this->tenantColumn($resource);
        $tenant = $this->currentTenantId();

        if ($column && $tenant !== null) {
            $record->setAttribute($column, $tenant);
        }
    }

    /**
     * Tenant-scoped base query for record lookups.
     */
    protected function tenantScopedQuery(string $modelClass, array $resource): mixed
    {
        return $this->applyTenantScope($modelClass::query(), $resource);
    }
}
