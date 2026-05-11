<?php

namespace YusufGenc34\FilamentApiForge\Pages;

use YusufGenc34\FilamentApiForge\Attributes\ApiTag;
use YusufGenc34\FilamentApiForge\Contracts\HasApi;
use YusufGenc34\FilamentApiForge\Models\ApiForgeResourceSetting;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class AccessControl extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';
    protected static string | \UnitEnum | null $navigationGroup = 'Developer Center';
    protected static ?string $navigationLabel = 'Access Control';
    protected static ?string $title = 'Access Control';
    protected static ?int $navigationSort = 3;
    protected static ?string $slug = 'developer/access-control';
    protected string $view = 'filament-api-forge::access-control';

    public array $resourceStates = [];

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->loadResourceStates();
    }

    public function toggleResource(string $resourceClass): void
    {
        ApiForgeResourceSetting::forResource($resourceClass)->toggleEnabled();
        app(ResourceDiscoveryService::class)->flush();
        $this->loadResourceStates();
    }

    public function toggleMethod(string $resourceClass, string $method): void
    {
        ApiForgeResourceSetting::forResource($resourceClass)->toggleMethod($method);
        app(ResourceDiscoveryService::class)->flush();
        $this->loadResourceStates();
    }

    public function saveResourceSettings(string $resourceClass, ?int $rateLimit, string $allowedIps): void
    {
        $ips = $this->sanitizeIps($allowedIps);
        ApiForgeResourceSetting::forResource($resourceClass)->saveSettings($rateLimit ?: null, $ips);
        $this->loadResourceStates();

        Notification::make()->title('Resource settings saved')->success()->send();
    }

    public function saveMethodSettings(string $resourceClass, string $method, ?int $rateLimit, string $allowedIps): void
    {
        $ips = $this->sanitizeIps($allowedIps);
        ApiForgeResourceSetting::forResource($resourceClass)->saveMethodConfig($method, $rateLimit ?: null, $ips);
        $this->loadResourceStates();

        Notification::make()->title('Method settings saved')->success()->send();
    }

    private function sanitizeIps(string $raw): array
    {
        return array_values(array_filter(
            array_map('trim', explode("\n", $raw)),
            fn (string $ip) => $ip !== '' && (
                filter_var($ip, FILTER_VALIDATE_IP) ||
                preg_match('/^[\d.\*]+$/', $ip) ||        // wildcard: 192.168.1.*
                preg_match('/^\d{1,3}(\.\d{1,3}){3}\/\d{1,2}$/', $ip) // CIDR
            )
        ));
    }

    private function loadResourceStates(): void
    {
        $settings = ApiForgeResourceSetting::all()->keyBy('resource_class');

        $this->resourceStates = [];

        foreach (filament()->getPanels() as $panel) {
            foreach ($panel->getResources() as $resourceClass) {
                if (! is_subclass_of($resourceClass, HasApi::class)) {
                    continue;
                }

                $setting    = $settings->get($resourceClass);
                $tag        = $this->resolveResourceTag($resourceClass);
                $apiConfig  = $resourceClass::apiConfig();
                $allMethods = $apiConfig['allowed_methods'] ?? ['index', 'show', 'store', 'update', 'destroy'];

                $disabledMethods = $setting ? ($setting->disabled_methods ?? []) : [];

                $methodSettings = [];
                foreach ($allMethods as $m) {
                    $mc = $setting ? $setting->getMethodConfig($m) : [];
                    $methodSettings[$m] = [
                        'rate_limit'  => $mc['rate_limit'] ?? null,
                        'allowed_ips' => $mc['allowed_ips'] ?? [],
                    ];
                }

                $this->resourceStates[$resourceClass] = [
                    'enabled'          => $setting ? $setting->enabled : true,
                    'tag'              => $tag,
                    'allowed_methods'  => $allMethods,
                    'disabled_methods' => $disabledMethods,
                    'rate_limit'       => $setting?->rate_limit,
                    'allowed_ips'      => $setting?->allowed_ips ?? [],
                    'method_settings'  => $methodSettings,
                    'model_fields'     => $this->resolveModelFields($resourceClass),
                    'api_config'       => $apiConfig,
                ];
            }
        }
    }

    private function resolveResourceTag(string $resourceClass): string
    {
        $ref   = new \ReflectionClass($resourceClass);
        $attrs = $ref->getAttributes(ApiTag::class);

        if (! empty($attrs)) {
            return $attrs[0]->newInstance()->name;
        }

        return $resourceClass::getPluralModelLabel();
    }

    private function resolveModelFields(string $resourceClass): array
    {
        try {
            $modelClass = $resourceClass::getModel();
            $model      = new $modelClass();
            return $model->getFillable();
        } catch (\Throwable) {
            return [];
        }
    }
}
