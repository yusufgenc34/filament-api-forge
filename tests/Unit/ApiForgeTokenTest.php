<?php

use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::create([
        'name'     => 'Test User',
        'email'    => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
});

it('creates a token with hash-based storage', function () {
    $plain  = 'forge_' . str_repeat('a', 40);
    $hash   = hash('sha256', $plain);
    $prefix = substr($plain, 0, 16);

    $token = ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Test Token',
        'token_hash'   => $hash,
        'token_prefix' => $prefix,
        'scopes'       => ['read', 'write'],
        'is_active'    => true,
    ]);

    expect($token->token_hash)->toBe($hash)
        ->and($token->token_prefix)->toBe($prefix)
        ->and($token->scopes)->toBe(['read', 'write'])
        ->and($token->is_active)->toBeTrue()
        ->and($token->token_hash)->not->toBe($plain); // Never store plain text
});

it('finds a token by plain-text value', function () {
    $plain = 'forge_' . str_repeat('b', 40);

    ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Findable',
        'token_hash'   => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 16),
        'scopes'       => ['read'],
        'is_active'    => true,
    ]);

    $found = ApiForgeToken::findByToken($plain);

    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Findable');
});

it('returns null when token not found', function () {
    $found = ApiForgeToken::findByToken('forge_nonexistent');

    expect($found)->toBeNull();
});

it('detects expired tokens', function () {
    $token = ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Expired Token',
        'token_hash'   => hash('sha256', 'forge_expired'),
        'token_prefix' => 'forge_expired_xx',
        'scopes'       => ['read'],
        'expires_at'   => Carbon::yesterday(),
        'is_active'    => true,
    ]);

    expect($token->isExpired())->toBeTrue()
        ->and($token->isValid())->toBeFalse();
});

it('detects valid non-expired tokens', function () {
    $token = ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Valid Token',
        'token_hash'   => hash('sha256', 'forge_valid'),
        'token_prefix' => 'forge_valid_xxxx',
        'scopes'       => ['read'],
        'expires_at'   => Carbon::tomorrow(),
        'is_active'    => true,
    ]);

    expect($token->isExpired())->toBeFalse()
        ->and($token->isValid())->toBeTrue();
});

it('treats null expiry as never expires', function () {
    $token = ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Forever Token',
        'token_hash'   => hash('sha256', 'forge_forever'),
        'token_prefix' => 'forge_forever_xx',
        'scopes'       => ['read'],
        'expires_at'   => null,
        'is_active'    => true,
    ]);

    expect($token->isExpired())->toBeFalse()
        ->and($token->isValid())->toBeTrue();
});

it('marks inactive tokens as invalid', function () {
    $token = ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Inactive',
        'token_hash'   => hash('sha256', 'forge_inactive'),
        'token_prefix' => 'forge_inactive_x',
        'scopes'       => ['read'],
        'is_active'    => false,
    ]);

    expect($token->isValid())->toBeFalse();
});

it('checks scope with wildcard support', function () {
    $readToken = ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Read Only',
        'token_hash'   => hash('sha256', 'forge_read'),
        'token_prefix' => 'forge_read_xxxxx',
        'scopes'       => ['read'],
        'is_active'    => true,
    ]);

    $wildcardToken = ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Full Access',
        'token_hash'   => hash('sha256', 'forge_wildcard'),
        'token_prefix' => 'forge_wildcard_x',
        'scopes'       => ['*'],
        'is_active'    => true,
    ]);

    expect($readToken->hasScope('read'))->toBeTrue()
        ->and($readToken->hasScope('write'))->toBeFalse()
        ->and($readToken->hasScope('delete'))->toBeFalse()
        ->and($wildcardToken->hasScope('read'))->toBeTrue()
        ->and($wildcardToken->hasScope('write'))->toBeTrue()
        ->and($wildcardToken->hasScope('delete'))->toBeTrue()
        ->and($wildcardToken->hasScope('anything'))->toBeTrue();
});

it('records usage by incrementing request count', function () {
    $token = ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Usage Tracker',
        'token_hash'   => hash('sha256', 'forge_usage'),
        'token_prefix' => 'forge_usage_xxxx',
        'scopes'       => ['read'],
        'is_active'    => true,
        'request_count' => 0,
    ]);

    expect($token->fresh()->request_count)->toBe(0)
        ->and($token->fresh()->last_used_at)->toBeNull();

    $token->recordUsage();
    $token->refresh();

    expect($token->request_count)->toBe(1)
        ->and($token->last_used_at)->not->toBeNull();
});

it('hides token_hash from serialization', function () {
    $token = ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Hidden Hash',
        'token_hash'   => hash('sha256', 'forge_hidden'),
        'token_prefix' => 'forge_hidden_xxx',
        'scopes'       => ['read'],
        'is_active'    => true,
    ]);

    $array = $token->toArray();

    expect($array)->not->toHaveKey('token_hash');
});

it('filters active tokens by scope', function () {
    ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Active',
        'token_hash'   => hash('sha256', 'forge_active'),
        'token_prefix' => 'forge_active_xxx',
        'scopes'       => ['read'],
        'is_active'    => true,
    ]);

    ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Inactive',
        'token_hash'   => hash('sha256', 'forge_inactive2'),
        'token_prefix' => 'forge_inactive2x',
        'scopes'       => ['read'],
        'is_active'    => false,
    ]);

    expect(ApiForgeToken::active()->count())->toBe(1);
});

it('filters non-expired tokens', function () {
    ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Future',
        'token_hash'   => hash('sha256', 'forge_future'),
        'token_prefix' => 'forge_future_xxx',
        'scopes'       => ['read'],
        'expires_at'   => Carbon::tomorrow(),
        'is_active'    => true,
    ]);

    ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Past',
        'token_hash'   => hash('sha256', 'forge_past'),
        'token_prefix' => 'forge_past_xxxxx',
        'scopes'       => ['read'],
        'expires_at'   => Carbon::yesterday(),
        'is_active'    => true,
    ]);

    expect(ApiForgeToken::notExpired()->count())->toBe(1);
});