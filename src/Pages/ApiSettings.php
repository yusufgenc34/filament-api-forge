<?php

namespace YusufGenc34\FilamentApiForge\Pages;

use YusufGenc34\FilamentApiForge\Contracts\HasApi;
use YusufGenc34\FilamentApiForge\Models\ApiForgeGlobalSetting;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Pages\DeveloperDashboard;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class ApiSettings extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string | \UnitEnum | null $navigationGroup = 'Developer Center';
    protected static ?string $navigationLabel = 'Settings';
    protected static ?string $title = 'API Settings';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'developer/settings';
    protected string $view = 'filament-api-forge::api-settings';

    public string $routeSegment          = '';
    public array  $routePreview          = [];
    public string $apiPrefix             = '';
    public int    $totalRequests         = 0;
    public string $formattedTotalRequests = '0';
    public array  $tokenCounts           = [];

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->apiPrefix    = config('filament-api-forge.api_prefix', 'api/v1');
        $this->routeSegment = (string) (ApiForgeGlobalSetting::get('route_segment') ?? '');
        $this->buildRoutePreview();
        $this->loadRequestCounts();
    }

    public function saveRouteSegment(): void
    {
        $segment = trim($this->routeSegment);

        // Sanitize: only allow alphanumeric, hyphens, underscores, max 40 chars
        $segment = substr(preg_replace('/[^a-zA-Z0-9\-_]/', '', $segment), 0, 40);
        $this->routeSegment = $segment;

        // Guard against reserved Laravel/Filament route segments
        $reserved = ['api', 'sanctum', 'livewire', 'filament', '_ignition', 'telescope'];
        if (in_array(strtolower($segment), $reserved, true)) {
            Notification::make()
                ->title('Reserved segment')
                ->body("'{$segment}' is a reserved route segment and cannot be used.")
                ->danger()
                ->send();
            return;
        }

        if ($segment === '') {
            ApiForgeGlobalSetting::set('route_segment', null);
        } else {
            ApiForgeGlobalSetting::set('route_segment', $segment);
        }

        $this->buildRoutePreview();

        Notification::make()
            ->title('Route segment saved')
            ->body($segment ? "API paths will use /{$segment}/..." : 'Using panel ID (default)')
            ->success()
            ->send();
    }

    public function resetRequestCounts(): void
    {
        ApiForgeToken::query()->update(['request_count' => 0]);
        $this->loadRequestCounts();

        Notification::make()
            ->title('Request counters reset')
            ->body('All token request counts have been set to zero.')
            ->success()
            ->send();
    }

    private function loadRequestCounts(): void
    {
        $this->totalRequests          = (int) ApiForgeToken::sum('request_count');
        $this->formattedTotalRequests = DeveloperDashboard::abbreviateCount($this->totalRequests);

        $this->tokenCounts = ApiForgeToken::orderByDesc('request_count')
            ->limit(10)
            ->get(['name', 'token_prefix', 'request_count', 'last_used_at'])
            ->map(fn ($t) => [
                'name'      => $t->name,
                'prefix'    => $t->token_prefix,
                'count'     => $t->request_count,
                'formatted' => DeveloperDashboard::abbreviateCount($t->request_count),
                'last_used' => $t->last_used_at?->diffForHumans() ?? 'Never',
            ])
            ->toArray();
    }

    public function resetRouteSegment(): void
    {
        $this->routeSegment = '';
        ApiForgeGlobalSetting::set('route_segment', null);
        $this->buildRoutePreview();

        Notification::make()->title('Reset to default (panel ID)')->success()->send();
    }

    private function buildRoutePreview(): void
    {
        $this->routePreview = [];
        $segment = trim($this->routeSegment);

        foreach (filament()->getPanels() as $panel) {
            foreach ($panel->getResources() as $resourceClass) {
                if (! is_subclass_of($resourceClass, HasApi::class)) {
                    continue;
                }

                $slug        = $resourceClass::getSlug();
                $panelId     = $panel->getId();
                $label       = $resourceClass::getPluralModelLabel();
                $usedSegment = $segment ?: $panelId;

                $this->routePreview[] = [
                    'label'   => $label,
                    'slug'    => $slug,
                    'panel'   => $panelId,
                    'current' => "/{$this->apiPrefix}/{$panelId}/{$slug}",
                    'preview' => "/{$this->apiPrefix}/{$usedSegment}/{$slug}",
                    'changed' => $usedSegment !== $panelId,
                ];
            }
        }
    }
}
