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

        $record = ApiForgeToken::create([
            'user_id'           => $user->getAuthIdentifier(),
            'name'              => $data['name'],
            'token_hash'        => $hash,
            'token_prefix'      => $prefix,
            'scopes'            => $data['scopes'] ?? ['read'],
            'allowed_resources' => $data['allowed_resources'] ?? null,
            'expires_at'        => $data['expires_at'] ?? null,
            'is_active'         => true,
        ]);

        return [
            'record'           => $record,
            'plain_text_token' => $plain,
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
