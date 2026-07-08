<?php

namespace YusufGenc34\FilamentApiForge\Services;

use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

class ApiForgeTokenService
{
    /**
     * Creates a new hash-based API token.
     *
     * Returns the plain-text token ONCE — it is never stored anywhere.
     * Only the SHA-256 hash and a display prefix are persisted.
     *
     * @param  Authenticatable $user
     * @param  array{
     *     name: string,
     *     scopes: string[],
     *     expires_at: \Carbon\Carbon|string|null,
     *     allowed_resources: string[]|null,
     * } $data
     * @return array{ record: ApiForgeToken, plain_text_token: string }
     */
    public function create(Authenticatable $user, array $data): array
    {
        $plain  = 'forge_' . Str::random(40);
        $hash   = hash('sha256', $plain);
        $prefix = substr($plain, 0, 16); // 'forge_' + first 10 random chars

        $refreshPlain = null;
        $refreshHash  = null;

        if (config('filament-api-forge.auth.refresh_tokens', false)) {
            $refreshPlain = 'forge_refresh_' . Str::random(40);
            $refreshHash  = hash('sha256', $refreshPlain);
        }

        $record = ApiForgeToken::create([
            'user_id'            => $user->getAuthIdentifier(),
            'name'               => $data['name'],
            'token_hash'         => $hash,
            'token_prefix'       => $prefix,
            'refresh_token_hash' => $refreshHash,
            'scopes'             => $data['scopes'] ?? ['read'],
            'allowed_resources'  => $data['allowed_resources'] ?? null,
            'expires_at'         => $data['expires_at'] ?? null,
            'tenant_id'          => $data['tenant_id'] ?? null,
            'is_active'          => true,
        ]);

        return [
            'record'                 => $record,
            'plain_text_token'       => $plain,
            'plain_refresh_token'    => $refreshPlain,
        ];
    }

    /**
     * Rotate a token: replace its access token in place, keeping scopes,
     * resource restrictions and identity. The old access token stops
     * working immediately.
     *
     * @return array{ record: ApiForgeToken, plain_text_token: string }
     */
    public function rotate(ApiForgeToken $record): array
    {
        $plain  = 'forge_' . Str::random(40);

        $record->forceFill([
            'token_hash'   => hash('sha256', $plain),
            'token_prefix' => substr($plain, 0, 16),
            'expires_at'   => now()->addDays(
                (int) config('filament-api-forge.auth.default_expiration_days', 365)
            ),
        ])->save();

        return [
            'record'           => $record->fresh(),
            'plain_text_token' => $plain,
        ];
    }

    /**
     * Exchange a refresh token for a fresh access token (and a new refresh
     * token). Works even when the access token has already expired — the
     * refresh token itself must belong to an active token record.
     *
     * @return array{ record: ApiForgeToken, plain_text_token: string, plain_refresh_token: string }|null
     */
    public function refresh(string $plainRefreshToken): ?array
    {
        if (! str_starts_with($plainRefreshToken, 'forge_refresh_')) {
            return null;
        }

        $record = ApiForgeToken::where('refresh_token_hash', hash('sha256', $plainRefreshToken))
            ->where('is_active', true)
            ->first();

        if (! $record) {
            return null;
        }

        $plain        = 'forge_' . Str::random(40);
        $refreshPlain = 'forge_refresh_' . Str::random(40);

        $record->forceFill([
            'token_hash'         => hash('sha256', $plain),
            'token_prefix'       => substr($plain, 0, 16),
            'refresh_token_hash' => hash('sha256', $refreshPlain),
            'expires_at'         => now()->addDays(
                (int) config('filament-api-forge.auth.default_expiration_days', 365)
            ),
            'expiry_notified_at' => null,
        ])->save();

        return [
            'record'              => $record->fresh(),
            'plain_text_token'    => $plain,
            'plain_refresh_token' => $refreshPlain,
        ];
    }

    /**
     * Revokes (permanently deletes) a token.
     */
    public function revoke(ApiForgeToken $record): void
    {
        $record->delete();
    }
}
