<?php

use YusufGenc34\FilamentApiForge\Http\Middleware\ApiForgeAuthenticate;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::create([
        'name'     => 'API User',
        'email'    => 'api-auth@example.com',
        'password' => bcrypt('password'),
    ]);
});

// ── Unit-level middleware tests (no Filament dependency) ────────────────────

it('returns 401 when no bearer token is present', function () {
    $request = Request::create('/api/v1/admin/posts');

    $middleware = new ApiForgeAuthenticate();
    $response = $middleware->handle($request, fn () => response()->noContent());

    expect($response->getStatusCode())->toBe(401);

    $body = json_decode($response->getContent(), true);
    expect($body['error'])->toBe('unauthenticated');
});

it('returns 401 when bearer token lacks forge_ prefix', function () {
    $request = Request::create('/api/v1/admin/posts');
    $request->headers->set('Authorization', 'Bearer not_forge_token_here');

    $middleware = new ApiForgeAuthenticate();
    $response = $middleware->handle($request, fn () => response()->noContent());

    expect($response->getStatusCode())->toBe(401)
        ->and(json_decode($response->getContent(), true)['error'])->toBe('unauthenticated');
});

it('returns 401 when token does not exist in database', function () {
    $request = Request::create('/api/v1/admin/posts');
    $request->headers->set('Authorization', 'Bearer forge_nonexistenttokenxxxxxxxxxxxxxxxxxxxxxx');

    $middleware = new ApiForgeAuthenticate();
    $response = $middleware->handle($request, fn () => response()->noContent());

    expect($response->getStatusCode())->toBe(401)
        ->and(json_decode($response->getContent(), true)['error'])->toBe('invalid_token');
});

it('returns 403 when token is expired', function () {
    $plain = 'forge_' . str_repeat('e', 40);

    ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Expired API Token',
        'token_hash'   => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 16),
        'scopes'       => ['read'],
        'expires_at'   => Carbon::yesterday(),
        'is_active'    => true,
    ]);

    $request = Request::create('/api/v1/admin/posts');
    $request->headers->set('Authorization', "Bearer {$plain}");

    $middleware = new ApiForgeAuthenticate();
    $response = $middleware->handle($request, fn () => response()->noContent());

    expect($response->getStatusCode())->toBe(403)
        ->and(json_decode($response->getContent(), true)['error'])->toBe('token_invalid');
});

it('returns 403 when token is deactivated', function () {
    $plain = 'forge_' . str_repeat('d', 40);

    ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Deactivated API Token',
        'token_hash'   => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 16),
        'scopes'       => ['read'],
        'is_active'    => false,
    ]);

    $request = Request::create('/api/v1/admin/posts');
    $request->headers->set('Authorization', "Bearer {$plain}");

    $middleware = new ApiForgeAuthenticate();
    $response = $middleware->handle($request, fn () => response()->noContent());

    expect($response->getStatusCode())->toBe(403)
        ->and(json_decode($response->getContent(), true)['error'])->toBe('token_invalid');
});

it('attaches token and user to request on success', function () {
    $plain = 'forge_' . str_repeat('v', 40);

    ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Valid API Token',
        'token_hash'   => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 16),
        'scopes'       => ['*'],
        'is_active'    => true,
    ]);

    $request = Request::create('/api/v1/admin/posts');
    $request->headers->set('Authorization', "Bearer {$plain}");

    $middleware = new ApiForgeAuthenticate();

    $capturedToken = null;
    $capturedUser  = null;

    $middleware->handle($request, function (Request $req) use (&$capturedToken, &$capturedUser) {
        $capturedToken = $req->attributes->get('api_forge_token');
        $capturedUser  = $req->user();
        return response()->noContent();
    });

    expect($capturedToken)->not->toBeNull()
        ->and($capturedToken)->toBeInstanceOf(ApiForgeToken::class)
        ->and($capturedUser)->not->toBeNull()
        ->and($capturedUser->id)->toBe($this->user->id);
});

it('increments request count on successful auth', function () {
    $plain = 'forge_' . str_repeat('c', 40);

    $token = ApiForgeToken::create([
        'user_id'       => $this->user->id,
        'name'          => 'Counter Token',
        'token_hash'    => hash('sha256', $plain),
        'token_prefix'  => substr($plain, 0, 16),
        'scopes'        => ['read'],
        'is_active'     => true,
        'request_count' => 0,
    ]);

    $request = Request::create('/api/v1/admin/posts');
    $request->headers->set('Authorization', "Bearer {$plain}");

    $middleware = new ApiForgeAuthenticate();
    $middleware->handle($request, fn () => response()->noContent());

    $token->refresh();
    expect($token->request_count)->toBe(1);
});

it('returns 401 when token user is missing', function () {
    $plain = 'forge_' . str_repeat('o', 40);

    ApiForgeToken::create([
        'user_id'      => 99999,
        'name'         => 'Orphan Token',
        'token_hash'   => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 16),
        'scopes'       => ['read'],
        'is_active'    => true,
    ]);

    $request = Request::create('/api/v1/admin/posts');
    $request->headers->set('Authorization', "Bearer {$plain}");

    $middleware = new ApiForgeAuthenticate();
    $response = $middleware->handle($request, fn () => response()->noContent());

    expect($response->getStatusCode())->toBe(401)
        ->and(json_decode($response->getContent(), true)['error'])->toBe('invalid_token');
});

it('returns json responses even for auth failures', function () {
    $request = Request::create('/api/v1/admin/posts');

    $middleware = new ApiForgeAuthenticate();
    $response = $middleware->handle($request, fn () => response()->noContent());

    expect($response->headers->get('Content-Type'))->toContain('application/json');
});
