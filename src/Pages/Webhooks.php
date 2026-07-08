<?php

namespace YusufGenc34\FilamentApiForge\Pages;

use YusufGenc34\FilamentApiForge\Models\ApiForgeWebhook;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class Webhooks extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-signal';
    protected static string | \UnitEnum | null $navigationGroup = 'Developer Center';
    protected static ?string $navigationLabel = 'Webhooks';
    protected static ?string $title = 'Webhooks';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'developer/webhooks';
    protected string $view = 'filament-api-forge::webhooks';

    public const EVENT_OPTIONS = [
        '*'               => 'All events',
        'created'         => 'Created',
        'updated'         => 'Updated',
        'deleted'         => 'Deleted',
        'restored'        => 'Restored',
        'force_deleted'   => 'Force deleted',
        'action_executed' => 'Action executed',
    ];

    public array $webhooks = [];
    public array $resourceOptions = [];

    // New webhook form state
    public string $name = '';
    public string $url = '';
    public string $secret = '';
    public array $events = ['*'];
    public string $resourceClass = '';

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->loadWebhooks();

        $this->resourceOptions = app(ResourceDiscoveryService::class)
            ->discover()
            ->pluck('resource_class')
            ->unique()
            ->values()
            ->all();
    }

    public function loadWebhooks(): void
    {
        $this->webhooks = ApiForgeWebhook::orderBy('created_at', 'desc')
            ->get()
            ->map(fn (ApiForgeWebhook $hook) => [
                'id'                => $hook->id,
                'name'              => $hook->name,
                'url'               => $hook->url,
                'events'            => $hook->events,
                'resource_class'    => $hook->resource_class,
                'is_active'         => $hook->is_active,
                'has_secret'        => filled($hook->secret),
                'failure_count'     => $hook->failure_count,
                'last_triggered_at' => $hook->last_triggered_at?->diffForHumans(),
            ])
            ->all();
    }

    public function addWebhook(): void
    {
        $this->validate([
            'name'   => ['required', 'string', 'max:255'],
            'url'    => ['required', 'url', 'max:2048'],
            'secret' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
        ]);

        ApiForgeWebhook::create([
            'name'           => $this->name,
            'url'            => $this->url,
            'secret'         => $this->secret ?: null,
            'events'         => array_values($this->events),
            'resource_class' => $this->resourceClass ?: null,
            'is_active'      => true,
        ]);

        $this->reset(['name', 'url', 'secret', 'resourceClass']);
        $this->events = ['*'];
        $this->loadWebhooks();

        Notification::make()->title('Webhook created')->success()->send();
    }

    public function toggleWebhook(int $id): void
    {
        $hook = ApiForgeWebhook::findOrFail($id);
        $hook->update(['is_active' => ! $hook->is_active]);

        $this->loadWebhooks();
    }

    public function deleteWebhook(int $id): void
    {
        ApiForgeWebhook::findOrFail($id)->delete();

        $this->loadWebhooks();

        Notification::make()->title('Webhook deleted')->success()->send();
    }
}
