<?php

namespace YusufGenc34\FilamentApiForge\Pages;

use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
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
    public int    $resourceCount   = 0;
    public int    $totalEndpoints  = 0;
    public int    $activeTokens    = 0;
    public int    $totalRequests         = 0;
    public string $formattedTotalRequests = '0';
    public string $apiBaseUrl      = '';
    public string $apiVersion      = '';

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

        // Count all enabled endpoints across all resources
        $this->totalEndpoints = $resources->sum(
            fn ($r) => count($r['api_config']['allowed_methods'] ?? [])
        );

        // Token stats
        $this->activeTokens   = ApiForgeToken::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        $this->totalRequests          = (int) ApiForgeToken::sum('request_count');
        $this->formattedTotalRequests = self::abbreviateCount($this->totalRequests);
    }

    public static function abbreviateCount(int $n): string
    {
        if ($n >= 1_000_000_000) return round($n / 1_000_000_000, 1) . 'B';
        if ($n >= 1_000_000)     return round($n / 1_000_000, 1) . 'M';
        if ($n >= 1_000)         return round($n / 1_000, 1) . 'K';
        return (string) $n;
    }
}
