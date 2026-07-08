<?php

namespace YusufGenc34\FilamentApiForge\Pages;

use YusufGenc34\FilamentApiForge\Contracts\HasApi;
use YusufGenc34\FilamentApiForge\Models\ApiForgeResourceSetting;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class DeveloperDashboard extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-code-bracket-square';
    protected static string | \UnitEnum | null $navigationGroup = 'Developer Center';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $title = 'Developer Center';
    protected static ?int $navigationSort = 0;
    protected static ?string $slug = 'developer/dashboard';
    protected string $view = 'filament-api-forge::developer-dashboard';

    public array  $apiResources    = [];
    public array  $treeResources   = [];
    public int    $resourceCount   = 0;
    public int    $totalEndpoints  = 0;
    public int    $activeTokens    = 0;
    public int    $totalRequests         = 0;
    public string $formattedTotalRequests = '0';
    public string $apiBaseUrl      = '';
    public string $apiVersion      = '';
    public array  $recentRequests  = [];
    public ?int   $avgResponseMs   = null;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $discovery = app(ResourceDiscoveryService::class);
        $resources = $discovery->discover();

        $this->apiResources   = $resources->toArray();
        $this->resourceCount  = $resources->count();
        $this->apiBaseUrl     = url(config('filament-api-forge.api_prefix', 'api/v1'));
        $this->apiVersion     = config('filament-api-forge.api_version', 'v1');

        $this->totalEndpoints = $resources->sum(
            fn ($r) => count($r['api_config']['allowed_methods'] ?? [])
        );

        $this->activeTokens = ApiForgeToken::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        $this->totalRequests          = (int) ApiForgeToken::sum('request_count');
        $this->formattedTotalRequests = self::abbreviateCount($this->totalRequests);

        $this->treeResources = $this->discoverAllForTree();

        $this->loadAuditStats();
    }

    protected function loadAuditStats(): void
    {
        if (! config('filament-api-forge.audit.enabled', true)
            || ! \Illuminate\Support\Facades\Schema::hasTable('api_forge_request_logs')) {
            return;
        }

        $this->recentRequests = \YusufGenc34\FilamentApiForge\Models\ApiForgeRequestLog::query()
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn ($log) => [
                'method'      => $log->method,
                'path'        => $log->path,
                'action'      => $log->action,
                'status'      => $log->status,
                'duration_ms' => $log->duration_ms,
                'ip'          => $log->ip,
                'when'        => $log->created_at?->diffForHumans(short: true),
            ])
            ->all();

        $avg = \YusufGenc34\FilamentApiForge\Models\ApiForgeRequestLog::query()
            ->where('created_at', '>=', now()->subDay())
            ->avg('duration_ms');

        $this->avgResponseMs = $avg !== null ? (int) round($avg) : null;
    }

    protected function discoverAllForTree(): array
    {
        $allMethods = config(
            'filament-api-forge.discovery.allowed_methods',
            ['index', 'show', 'store', 'update', 'destroy']
        );

        $settings = ApiForgeResourceSetting::all()->keyBy('resource_class');
        $tree     = [];

        foreach (Filament::getPanels() as $panel) {
            $panelId = $panel->getId();

            foreach ($panel->getResources() as $resourceClass) {
                if (! is_subclass_of($resourceClass, HasApi::class)) {
                    continue;
                }

                $setting         = $settings->get($resourceClass);
                $enabled         = ! $setting || $setting->enabled;
                $apiConfig       = $resourceClass::apiConfig();
                $configMethods   = $apiConfig['allowed_methods'] ?? $allMethods;
                $disabledMethods = $setting ? ($setting->disabled_methods ?? []) : [];

                $methods = [];
                foreach ($configMethods as $method) {
                    $methods[] = [
                        'method'   => $method,
                        'disabled' => in_array($method, $disabledMethods),
                    ];
                }

                $tree[] = [
                    'resource_class' => $resourceClass,
                    'slug'           => $resourceClass::getSlug(),
                    'panel_id'       => $panelId,
                    'plural_label'   => $resourceClass::getPluralModelLabel(),
                    'enabled'        => $enabled,
                    'methods'        => $methods,
                ];
            }
        }

        return $tree;
    }

    public static function abbreviateCount(int $n): string
    {
        if ($n >= 1_000_000_000) return round($n / 1_000_000_000, 1) . 'B';
        if ($n >= 1_000_000)     return round($n / 1_000_000, 1) . 'M';
        if ($n >= 1_000)         return round($n / 1_000, 1) . 'K';
        return (string) $n;
    }
}
