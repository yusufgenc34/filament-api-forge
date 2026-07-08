<?php

namespace YusufGenc34\FilamentApiForge\Listeners;

use YusufGenc34\FilamentApiForge\Events\ApiActionExecuted;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleted;
use YusufGenc34\FilamentApiForge\Events\ApiResourceForceDeleted;
use YusufGenc34\FilamentApiForge\Events\ApiResourceRestored;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdated;
use YusufGenc34\FilamentApiForge\Jobs\SendApiForgeWebhook;
use YusufGenc34\FilamentApiForge\Models\ApiForgeWebhook;
use YusufGenc34\FilamentApiForge\Support\ResponseCacheManager;
use Illuminate\Events\Dispatcher;

/**
 * Central subscriber for API write events: fans out webhooks and
 * invalidates the response cache for the affected resource.
 */
class ApiForgeEventSubscriber
{
    /**
     * Event class → webhook event name.
     */
    protected const EVENT_NAMES = [
        ApiResourceCreated::class      => 'created',
        ApiResourceUpdated::class      => 'updated',
        ApiResourceDeleted::class      => 'deleted',
        ApiResourceRestored::class     => 'restored',
        ApiResourceForceDeleted::class => 'force_deleted',
        ApiActionExecuted::class       => 'action_executed',
    ];

    public function subscribe(Dispatcher $events): array
    {
        return array_fill_keys(array_keys(self::EVENT_NAMES), 'handle');
    }

    public function handle(object $event): void
    {
        $eventName     = self::EVENT_NAMES[get_class($event)] ?? null;
        $resourceClass = $event->resourceClass ?? null;

        if (! $eventName || ! $resourceClass) {
            return;
        }

        // 1. Response cache invalidation — any write bumps the resource version
        ResponseCacheManager::bump($resourceClass);

        // 2. Webhook fan-out
        if (! config('filament-api-forge.webhooks.enabled', true)) {
            return;
        }

        $payload = $this->buildPayload($event, $eventName, $resourceClass);

        ApiForgeWebhook::active()
            ->get()
            ->filter(fn (ApiForgeWebhook $webhook) => $webhook->listensTo($eventName, $resourceClass))
            ->each(fn (ApiForgeWebhook $webhook) => SendApiForgeWebhook::dispatch($webhook->id, $payload));
    }

    protected function buildPayload(object $event, string $eventName, string $resourceClass): array
    {
        $record = $event->record ?? null;

        $payload = [
            'event'     => $eventName,
            'resource'  => class_basename($resourceClass),
            'resource_class' => $resourceClass,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($record !== null) {
            $payload['record'] = [
                'id'         => $record->getKey(),
                'attributes' => $record->attributesToArray(),
            ];
        }

        if ($event instanceof ApiActionExecuted) {
            $payload['action'] = $event->actionName;
            $payload['result'] = $event->result;
        }

        return $payload;
    }
}
