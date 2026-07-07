<?php

namespace YusufGenc34\FilamentApiForge\Concerns;

use YusufGenc34\FilamentApiForge\Traits\ApiForgeHooks;

trait ExecutesApiHooks
{
    /**
     * Per-request memo of consumed withoutHooks() flags, keyed by resource class.
     * The flag is one-shot on the trait; memoizing it here makes a single
     * withoutHooks() call suppress every hook of the current request
     * (before* AND after*), not just the first one evaluated.
     *
     * @var array<string, bool>
     */
    protected array $consumedHookSkips = [];

    /**
     * Master switch for the hook system.
     */
    protected function hooksEnabled(): bool
    {
        return (bool) config('filament-api-forge.events.enabled', true);
    }

    /**
     * Events require both the master switch and dispatch_events.
     */
    protected function eventsEnabled(): bool
    {
        return $this->hooksEnabled()
            && (bool) config('filament-api-forge.events.dispatch_events', true);
    }

    /**
     * Execute "before" style hooks that transform data.
     * The last argument is the data array and is returned (transformed or not).
     */
    protected function executeBeforeHooks(string $resourceClass, string $hook, ...$args): array
    {
        if ($this->shouldRunHooks($resourceClass)) {
            return $resourceClass::{$hook}(...$args);
        }

        return $args[count($args) - 1];
    }

    /**
     * Execute "after" style hooks that receive $record and $data.
     */
    protected function executeAfterHooks(string $resourceClass, string $hook, ...$args): void
    {
        if ($this->shouldRunHooks($resourceClass)) {
            $resourceClass::{$hook}(...$args);
        }
    }

    /**
     * Execute void hooks (beforeDelete/afterDelete).
     */
    protected function executeVoidHooks(string $resourceClass, string $hook, ...$args): void
    {
        if ($this->shouldRunHooks($resourceClass)) {
            $resourceClass::{$hook}(...$args);
        }
    }

    protected function shouldRunHooks(string $resourceClass): bool
    {
        if (! $this->hooksEnabled() || ! $this->usesApiForgeHooks($resourceClass)) {
            return false;
        }

        return ! $this->shouldSkipHooksFor($resourceClass);
    }

    /**
     * Consume the one-shot withoutHooks() flag once per request per resource.
     */
    protected function shouldSkipHooksFor(string $resourceClass): bool
    {
        return $this->consumedHookSkips[$resourceClass] ??= $resourceClass::shouldSkipHooks();
    }

    /**
     * Check if a resource class uses the ApiForgeHooks trait.
     */
    protected function usesApiForgeHooks(string $resourceClass): bool
    {
        if (! class_exists($resourceClass)) {
            return false;
        }

        return in_array(ApiForgeHooks::class, class_uses($resourceClass));
    }
}
