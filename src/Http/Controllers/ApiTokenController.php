<?php

namespace YusufGenc34\FilamentApiForge\Http\Controllers;

use YusufGenc34\FilamentApiForge\Services\ApiForgeTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApiTokenController extends Controller
{
    public function __construct(
        protected ApiForgeTokenService $tokenService,
    ) {}

    /**
     * POST /auth/token/rotate  (authenticated)
     *
     * Replace the current access token with a fresh one. The old token
     * stops working immediately; the new plain token is returned once.
     */
    public function rotate(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_forge_token');

        if (! $token) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'error'   => 'unauthenticated',
            ], 401);
        }

        $result = $this->tokenService->rotate($token);

        return response()->json([
            'message'    => 'Token rotated successfully. Store the new token now — it will not be shown again.',
            'token'      => $result['plain_text_token'],
            'expires_at' => $result['record']->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * POST /auth/token/refresh  (public — authenticated by the refresh token itself)
     *
     * Exchange a forge_refresh_ token for a new access + refresh token pair.
     */
    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $result = $this->tokenService->refresh($validated['refresh_token']);

        if (! $result) {
            return response()->json([
                'message' => 'Invalid refresh token.',
                'error'   => 'invalid_refresh_token',
            ], 401);
        }

        return response()->json([
            'message'       => 'Token refreshed successfully. Store the new tokens now — they will not be shown again.',
            'token'         => $result['plain_text_token'],
            'refresh_token' => $result['plain_refresh_token'],
            'expires_at'    => $result['record']->expires_at?->toIso8601String(),
        ]);
    }
}
