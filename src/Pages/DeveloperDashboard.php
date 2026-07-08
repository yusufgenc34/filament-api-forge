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
    public int    $requestsToday   = 0;
    public ?float $errorRate       = null;   // % of 4xx/5xx in the last 24h
    public array  $dailyRequests   = [];     // last 7 days bar chart
    public array  $topEndpoints    = [];     // by resource+action, last 7 days
    public array  $topTokens       = [];     // by all-time request_count
    public array  $expiringTokens  = [];     // within the next 14 days
    public array  $webhookOverview = [];
    public array  $featureFlags    = [];

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
        $this->loadTokenInsights();
        $this->loadWebhookOverview();
        $this->loadFeatureFlags();

        if (! config('filament-api-forge.audit.enabled', true)
            || ! \Illuminate\Support\Facades\Schema::hasTable('api_forge_request_logs')) {
            return;
        }

        $logModel = \YusufGenc34\FilamentApiForge\Models\ApiForgeRequestLog::class;

        $this->recentRequests = $logModel::query()
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

        // 24h aggregates
        $last24h = $logModel::query()->where('created_at', '>=', now()->subDay());

        $total24h = (clone $last24h)->count();
        $avg      = (clone $last24h)->avg('duration_ms');

        $this->avgResponseMs = $avg !== null ? (int) round($avg) : null;

        if ($total24h > 0) {
            $errors = (clone $last24h)->where('status', '>=', 400)->count();
            $this->errorRate = round($errors * 100 / $total24h, 1);
        }

        $this->requestsToday = $logModel::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        // Last 7 days — grouped in PHP for cross-database compatibility
        $byDay = $logModel::query()
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->get(['created_at'])
            ->groupBy(fn ($log) => $log->created_at->format('Y-m-d'))
            ->map->count();

        $max = max(1, $byDay->max() ?? 0);

        $this->dailyRequests = collect(range(6, 0))
            ->map(function (int $daysAgo) use ($byDay, $max) {
                $date = now()->subDays($daysAgo);
                $count = $byDay[$date->format('Y-m-d')] ?? 0;

                return [
                    'label' => $date->format('D'),
                    'count' => $count,
                    'pct'   => (int) round($count * 100 / $max),
                ];
            })
            ->all();

        // Top endpoints (7d) by resource + action
        $this->topEndpoints = $logModel::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('resource_class')
            ->get(['resource_class', 'action', 'method'])
            ->groupBy(fn ($log) => class_basename($log->resource_class) . '·' . $log->action . '·' . $log->method)
            ->map(fn ($group) => [
                'resource' => class_basename($group->first()->resource_class),
                'action'   => $group->first()->action,
                'method'   => $group->first()->method,
                'count'    => $group->count(),
            ])
            ->sortByDesc('count')
            ->take(6)
            ->values()
            ->all();
    }

    protected function loadTokenInsights(): void
    {
        $this->topTokens = ApiForgeToken::query()
            ->where('request_count', '>', 0)
            ->orderByDesc('request_count')
            ->limit(5)
            ->get()
            ->map(fn (ApiForgeToken $token) => [
                'name'   => $token->name,
                'prefix' => $token->token_prefix,
                'count'  => self::abbreviateCount($token->request_count),
                'active' => $token->is_active && ! $token->isExpired(),
            ])
            ->all();

        $this->expiringTokens = ApiForgeToken::query()
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(14)])
            ->orderBy('expires_at')
            ->limit(5)
            ->get()
            ->map(fn (ApiForgeToken $token) => [
                'name'    => $token->name,
                'prefix'  => $token->token_prefix,
                'days'    => (int) now()->diffInDays($token->expires_at),
                'notified' => $token->expiry_notified_at !== null,
            ])
            ->all();
    }

    protected function loadWebhookOverview(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('api_forge_webhooks')) {
            return;
        }

        $this->webhookOverview = \YusufGenc34\FilamentApiForge\Models\ApiForgeWebhook::query()
            ->orderByDesc('is_active')
            ->orderByDesc('last_triggered_at')
            ->limit(5)
            ->get()
            ->map(fn ($hook) => [
                'name'     => $hook->name,
                'active'   => $hook->is_active,
                'failures' => $hook->failure_count,
                'last'     => $hook->last_triggered_at?->diffForHumans(short: true) ?? 'never',
            ])
            ->all();
    }

    protected function loadFeatureFlags(): void
    {
        $versions = config('filament-api-forge.versions');

        $this->featureFlags = [
            ['label' => 'Audit Log',      'on' => (bool) config('filament-api-forge.audit.enabled', true)],
            ['label' => 'Response Cache', 'on' => (bool) config('filament-api-forge.cache.enabled', false)],
            ['label' => 'Webhooks',       'on' => (bool) config('filament-api-forge.webhooks.enabled', true)],
            ['label' => 'GraphQL',        'on' => (bool) config('filament-api-forge.graphql.enabled', false)],
            ['label' => 'Refresh Tokens', 'on' => (bool) config('filament-api-forge.auth.refresh_tokens', false)],
            ['label' => 'Export',         'on' => (bool) config('filament-api-forge.export.enabled', true)],
            ['label' => 'Multi-Version',  'on' => is_array($versions) && count($versions) > 1],
        ];
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
