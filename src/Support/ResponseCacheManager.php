<?php

namespace YusufGenc34\FilamentApiForge\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Version-key based response cache for GET endpoints.
 *
 * Every resource class has a monotonically increasing version number.
 * Cached responses embed the version in their key, so bumping the
 * version on any write instantly invalidates every cached page of
 * that resource without scanning or tagging.
 */
class ResponseCacheManager
{
    protected const VERSION_PREFIX = 'api-forge:cache-ver:';
    protected const RESPONSE_PREFIX = 'api-forge:response:';

    public static function enabled(): bool
    {
        return (bool) config('filament-api-forge.cache.enabled', false);
    }

    public static function ttl(): int
    {
        return (int) config('filament-api-forge.cache.ttl', 60);
    }

    public static function store()
    {
        return Cache::store(config('filament-api-forge.cache.store'));
    }

    public static function version(string $resourceClass): int
    {
        return (int) static::store()->get(self::VERSION_PREFIX . $resourceClass, 1);
    }

    public static function bump(string $resourceClass): void
    {
        if (! static::enabled()) {
            return;
        }

        $key = self::VERSION_PREFIX . $resourceClass;
        $store = static::store();

        $store->put($key, (int) $store->get($key, 1) + 1);
    }

    public static function key(string $resourceClass, string $url, ?string $tokenId): string
    {
        return self::RESPONSE_PREFIX . sha1(implode('|', [
            $resourceClass,
            static::version($resourceClass),
            $url,
            $tokenId ?? 'anonymous',
        ]));
    }

    public static function get(string $key): ?array
    {
        return static::store()->get($key);
    }

    public static function put(string $key, array $payload): void
    {
        static::store()->put($key, $payload, static::ttl());
    }
}
