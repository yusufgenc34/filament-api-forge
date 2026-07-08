<?php

namespace YusufGenc34\FilamentApiForge\Http\Middleware;

use Closure;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use YusufGenc34\FilamentApiForge\Support\ResponseCacheManager;
use Illuminate\Http\Request;

class CacheApiForgeResponse
{
    /**
     * Route names eligible for response caching (read-only endpoints).
     */
    protected const CACHEABLE_ROUTES = [
        'api-forge.index',
        'api-forge.show',
        'api-forge.nested.index',
        'api-forge.nested.show',
    ];

    public function __construct(
        protected ResourceDiscoveryService $discoveryService,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! ResponseCacheManager::enabled() || $request->method() !== 'GET') {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (! in_array($routeName, self::CACHEABLE_ROUTES)) {
            return $next($request);
        }

        $panelId = $request->route('panelId');
        $slug    = $request->route('resourceSlug');

        if (! $panelId || ! $slug) {
            return $next($request);
        }

        $resource = $this->discoveryService->findResource($panelId, $slug);

        if (! $resource) {
            return $next($request);
        }

        $token = $request->attributes->get('api_forge_token');
        $key   = ResponseCacheManager::key($resource['resource_class'], $request->fullUrl(), $token?->id);

        $cached = ResponseCacheManager::get($key);

        if ($cached !== null) {
            return response($cached['content'], $cached['status'])
                ->header('Content-Type', 'application/json')
                ->header('X-ApiForge-Cache', 'hit');
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200) {
            ResponseCacheManager::put($key, [
                'content' => $response->getContent(),
                'status'  => $response->getStatusCode(),
            ]);

            $response->headers->set('X-ApiForge-Cache', 'miss');
        }

        return $response;
    }
}
