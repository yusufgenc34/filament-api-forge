<?php

use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Services\ApiForgeTokenService;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->service = app(ApiForgeTokenService::class);
    $this->user = User::create([
        'name'     => 'Test User',
        'email'    => 'token-service@example.com',
        'password' => bcrypt('password'),
    ]);
});

it('creates a token with forge_ prefix', function () {
    $result = $this->service->create($this->user, [
        'name'   => 'Service Token',
        'scopes' => ['read'],
    ]);

    expect($result['plain_text_token'])->toStartWith('forge_')
        ->and(strlen($result['plain_text_token']))->toBe(46); // forge_ + 40 chars
});

it('stores only the hash, never the plain text', function () {
    $result = $this->service->create($this->user, [
        'name'   => 'Hash Check',
        'scopes' => ['read', 'write'],
    ]);

    $record = $result['record'];

    expect($record->token_hash)->not->toBe($result['plain_text_token'])
        ->and($record->token_hash)->toBe(hash('sha256', $result['plain_text_token']))
        ->and($record->token_prefix)->toBe(substr($result['plain_text_token'], 0, 16));
});

it('sets default scopes to read-only', function () {
    $result = $this->service->create($this->user, [
        'name' => 'Default Scope',
    ]);

    expect($result['record']->scopes)->toBe(['read']);
});

it('creates token with expiration date', function () {
    $expires = Carbon::now()->addDays(30);

    $result = $this->service->create($this->user, [
        'name'       => 'Expiring Token',
        'scopes'     => ['read'],
        'expires_at' => $expires,
    ]);

    expect($result['record']->expires_at->toDateString())->toBe($expires->toDateString());
});

it('creates token with allowed resources restriction', function () {
    $result = $this->service->create($this->user, [
        'name'              => 'Restricted Token',
        'scopes'            => ['read'],
        'allowed_resources' => ['posts', 'comments'],
    ]);

    expect($result['record']->allowed_resources)->toBe(['posts', 'comments']);
});

it('creates active token by default', function () {
    $result = $this->service->create($this->user, [
        'name' => 'Active Check',
    ]);

    expect($result['record']->is_active)->toBeTrue();
});

it('revokes a token by deleting it', function () {
    $result = $this->service->create($this->user, [
        'name' => 'To Be Revoked',
    ]);

    $tokenId = $result['record']->id;

    $this->service->revoke($result['record']);

    expect(ApiForgeToken::find($tokenId))->toBeNull();
});

it('generates unique plain-text tokens', function () {
    $tokens = [];

    for ($i = 0; $i < 10; $i++) {
        $result = $this->service->create($this->user, ['name' => "Token {$i}"]);
        $tokens[] = $result['plain_text_token'];
    }

    expect(count(array_unique($tokens)))->toBe(10);
});