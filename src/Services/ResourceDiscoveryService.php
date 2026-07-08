<?php

namespace YusufGenc34\FilamentApiForge\Services;

use YusufGenc34\FilamentApiForge\Contracts\HasApi;
use YusufGenc34\FilamentApiForge\Models\ApiForgeResourceSetting;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;

class ResourceDiscoveryService
{
    protected ?Collection $discoveredResources = null;

    public function discover(): Collection
    {
        if ($this->discoveredResources !== null) {
            return $this->discoveredResources;
        }

        $this->discoveredResources = collect();

        $settings = ApiForgeResourceSetting::all()->keyBy('resource_class');

        try {
            $panels = Filament::getPanels();
        } catch (\Throwable) {
            // Filament not booted (headless/console/test contexts) — no panels to scan
            $panels = [];
        }

        foreach ($panels as $panel) {
            $panelId   = $panel->getId();
            $resources = $panel->getResources();

            foreach ($resources as $resourceClass) {
                if (! is_subclass_of($resourceClass, HasApi::class)) {
                    continue;
                }

                $setting = $settings->get($resourceClass);

                if ($setting && ! $setting->enabled) {
                    continue;
                }

                /** @var HasApi $resourceClass */
                $apiConfig       = $resourceClass::apiConfig();
                $allMethods      = $apiConfig['allowed_methods']
                    ?? config('filament-api-forge.discovery.allowed_methods', ['index', 'show', 'store', 'update', 'destroy']);
                $disabledMethods = $setting ? ($setting->disabled_methods ?? []) : [];

                $apiConfig['allowed_methods'] = array_values(array_filter(
                    $allMethods,
                    fn ($m) => ! in_array($m, $disabledMethods)
                ));

                $this->discoveredResources->push([
                    'resource_class' => $resourceClass,
                    'model_class'    => $resourceClass::getModel(),
                    'slug'           => $resourceClass::getSlug(),
                    'panel_id'       => $panelId,
                    'api_config'     => $apiConfig,
                    'label'          => $resourceClass::getModelLabel(),
                    'plural_label'   => $resourceClass::getPluralModelLabel(),
                    'versions'       => $this->resourceVersions($resourceClass),
                ]);
            }
        }

        return $this->discoveredResources;
    }

    public function findResource(string $panelId, string $slug, ?string $version = null): ?array
    {
        // Version context comes from the SetApiForgeVersion middleware when
        // multi-version mode is active; explicit argument wins.
        $version ??= $this->currentVersion();

        $matchesVersion = fn (array $r) => $this->resourceAvailableIn($r, $version);

        // Try exact panel match first
        $resource = $this->discover()->first(function (array $resource) use ($panelId, $slug, $matchesVersion) {
            return $resource['panel_id'] === $panelId && $resource['slug'] === $slug && $matchesVersion($resource);
        });

        // If no exact panel match, search by slug across all panels (supports custom route segments)
        if (! $resource) {
            $resource = $this->discover()->first(fn (array $r) => $r['slug'] === $slug && $matchesVersion($r));
        }

        return $resource;
    }

    /**
     * Resources available in the given (or current) API version.
     */
    public function discoverForVersion(?string $version = null): Collection
    {
        $version ??= $this->currentVersion();

        return $this->discover()
            ->filter(fn (array $r) => $this->resourceAvailableIn($r, $version))
            ->values();
    }

    public function resourceAvailableIn(array $resource, ?string $version): bool
    {
        $versions = $resource['versions'] ?? null;

        // No version context or unrestricted resource → always available
        return $version === null || $versions === null || in_array($version, $versions);
    }

    protected function currentVersion(): ?string
    {
        try {
            return request()->attributes->get('api_forge_version');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return string[]|null null = available in every version
     */
    protected function resourceVersions(string $resourceClass): ?array
    {
        try {
            $ref   = new \ReflectionClass($resourceClass);
            $attrs = $ref->getAttributes(\YusufGenc34\FilamentApiForge\Attributes\ApiVersion::class);

            return $attrs ? $attrs[0]->newInstance()->versions : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getResourcesForPanel(string $panelId): Collection
    {
        return $this->discover()->where('panel_id', $panelId)->values();
    }

    public function isMethodAllowed(array $resource, string $method): bool
    {
        return in_array($method, $resource['api_config']['allowed_methods']
            ?? config('filament-api-forge.discovery.allowed_methods', ['index', 'show', 'store', 'update', 'destroy']));
    }

    public function getAllowedFilters(array $resource): array  { return $resource['api_config']['allowed_filters']  ?? []; }
    public function getAllowedSorts(array $resource): array    { return $resource['api_config']['allowed_sorts']    ?? []; }
    public function getAllowedIncludes(array $resource): array { return $resource['api_config']['allowed_includes'] ?? []; }
    public function getAllowedFields(array $resource): array   { return $resource['api_config']['allowed_fields']   ?? []; }
    public function getRequiredScopes(array $resource): array  { return $resource['api_config']['scopes']           ?? []; }

    /**
     * Get all #[ApiAction] methods for a resource class.
     *
     * @return array<string, array{name: string, method: string, scope: string, record: bool}>
     */
    public function getActions(string $resourceClass): array
    {
        $actions = [];

        try {
            $ref = new \ReflectionClass($resourceClass);

            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_STATIC) as $method) {
                $attrs = $method->getAttributes(\YusufGenc34\FilamentApiForge\Attributes\ApiAction::class);

                foreach ($attrs as $attr) {
                    /** @var \YusufGenc34\FilamentApiForge\Attributes\ApiAction $instance */
                    $instance = $attr->newInstance();
                    $actions[$instance->name] = [
                        'name'   => $instance->name,
                        'method' => $instance->method,
                        'scope'  => $instance->scope,
                        'record' => $instance->record,
                    ];
                }
            }
        } catch (\Throwable) {
            // Silently return empty if reflection fails
        }

        return $actions;
    }

    public function flush(): void
    {
        $this->discoveredResources = null;
    }
}
