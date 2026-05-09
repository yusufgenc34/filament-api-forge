<?php

namespace YusufGenc34\FilamentApiForge\Http\Middleware;

use Closure;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiForgeAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        if (! $plain || ! str_starts_with($plain, 'forge_')) {
            return response()->json([
                'message' => 'Unauthenticated. Provide a valid API Forge token via Bearer authentication.',
                'error'   => 'unauthenticated',
            ], 401);
        }

        $apiForgeToken = ApiForgeToken::findByToken($plain);

        if (! $apiForgeToken) {
            return response()->json([
                'message' => 'Invalid API token.',
                'error'   => 'invalid_token',
            ], 401);
        }

        if (! $apiForgeToken->isValid()) {
            $reason = $apiForgeToken->isExpired()
                ? 'Token has expired.'
                : 'Token has been deactivated.';

            return response()->json([
                'message' => $reason,
                'error'   => 'token_invalid',
            ], 403);
        }

        $user = $apiForgeToken->user;

        if (! $user) {
            return response()->json([
                'message' => 'Token owner not found.',
                'error'   => 'invalid_token',
            ], 401);
        }

        // Make $request->user() work for downstream middleware
        $request->setUserResolver(fn ($guard = null) => $user);

        $apiForgeToken->recordUsage();

        $request->attributes->set('api_forge_token', $apiForgeToken);

        return $next($request);
    }
}
