<?php

namespace YusufGenc34\FilamentApiForge\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;

class CheckResourceEnabled
{
    public function __construct(
        protected ResourceDiscoveryService $discoveryService,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $panelId = $request->route('panelId');
        $slug = $request->route('resourceSlug');

        if (! $panelId || ! $slug) {
            return $next($request);
        }

        // discover() already filters out disabled resources
        $resource = $this->discoveryService->findResource($panelId, $slug);

        if ($resource === null) {
            return response()->json([
                'message' => 'Resource not found or not exposed via API Forge.',
                'error' => 'not_found',
            ], 404);
        }

        return $next($request);
    }
}
