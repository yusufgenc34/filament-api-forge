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

        foreach (Filament::getPanels() as $panel) {
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
                ]);
            }
        }

        return $this->discoveredResources;
    }

    public function findResource(string $panelId, string $slug): ?array
    {
        return $this->discover()->first(function (array $resource) use ($panelId, $slug) {
            return $resource['panel_id'] === $panelId && $resource['slug'] === $slug;
        });
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

    public function flush(): void
    {
        $this->discoveredResources = null;
    }
}
