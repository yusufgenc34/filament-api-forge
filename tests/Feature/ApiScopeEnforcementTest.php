<?php

use YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->user = User::create([
        'name'     => 'Scope User',
        'email'    => 'scope@example.com',
        'password' => bcrypt('password'),
    ]);
});

function makeToken(User $user, array $scopes, ?array $allowedResources = null): ApiForgeToken
{
    $plain = 'forge_' . str_repeat(chr(97 + random_int(0, 25)), 40);

    return ApiForgeToken::create([
        'user_id'           => $user->id,
        'name'              => 'Scope Test ' . implode(',', $scopes),
        'token_hash'        => hash('sha256', $plain),
        'token_prefix'      => substr($plain, 0, 16),
        'scopes'            => $scopes,
        'allowed_resources' => $allowedResources,
        'is_active'         => true,
    ]);
}

// ── Scope checks on the model ──────────────────────────────────────────────

it('read-scoped token has read but not write or delete', function () {
    $token = makeToken($this->user, ['read']);

    expect($token->hasScope('read'))->toBeTrue()
        ->and($token->hasScope('write'))->toBeFalse()
        ->and($token->hasScope('delete'))->toBeFalse();
});

it('write-scoped token has write but not read or delete', function () {
    $token = makeToken($this->user, ['write']);

    expect($token->hasScope('write'))->toBeTrue()
        ->and($token->hasScope('read'))->toBeFalse()
        ->and($token->hasScope('delete'))->toBeFalse();
});

it('delete-scoped token has delete but not read or write', function () {
    $token = makeToken($this->user, ['delete']);

    expect($token->hasScope('delete'))->toBeTrue()
        ->and($token->hasScope('read'))->toBeFalse()
        ->and($token->hasScope('write'))->toBeFalse();
});

it('wildcard scope grants all permissions', function () {
    $token = makeToken($this->user, ['*']);

    expect($token->hasScope('read'))->toBeTrue()
        ->and($token->hasScope('write'))->toBeTrue()
        ->and($token->hasScope('delete'))->toBeTrue()
        ->and($token->hasScope('arbitrary_scope'))->toBeTrue();
});

// ── Controller-level scope enforcement ─────────────────────────────────────

it('resolveResource returns 403 when token lacks required scope', function () {
    $token = makeToken($this->user, ['read']); // read-only

    $request = Request::create('/api/v1/admin/posts', 'POST');
    $request->attributes->set('api_forge_token', $token);
    $this->app->instance('request', $request);

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\PostResource',
        'model_class'    => 'App\\Models\\Post',
        'slug'           => 'posts',
        'api_config'     => ['allowed_methods' => ['store']],
    ]);
    $mock->shouldReceive('isMethodAllowed')->andReturn(true);

    $controller = new ApiResourceController($mock);

    $ref = new ReflectionMethod($controller, 'resolveResource');
    $result = $ref->invoke($controller, 'admin', 'posts', 'store');

    expect($result)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);

    $data = $result->getData(true);
    expect($data['error'])->toBe('insufficient_scope');
    expect($data['required_scope'])->toBe('write');
    expect($result->getStatusCode())->toBe(403);
});

it('resolveResource returns 403 when token is restricted to other resources', function () {
    // Token has write scope BUT is restricted to 'comments' resource only
    $token = makeToken($this->user, ['read', 'write'], ['comments']);

    $request = Request::create('/api/v1/admin/posts', 'POST');
    $request->attributes->set('api_forge_token', $token);
    $this->app->instance('request', $request);

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\PostResource',
        'model_class'    => 'App\\Models\\Post',
        'slug'           => 'posts',
        'api_config'     => ['allowed_methods' => ['store']],
    ]);
    $mock->shouldReceive('isMethodAllowed')->andReturn(true);

    $controller = new ApiResourceController($mock);

    $ref = new ReflectionMethod($controller, 'resolveResource');
    $result = $ref->invoke($controller, 'admin', 'posts', 'store');

    expect($result)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);

    $data = $result->getData(true);
    expect($data['error'])->toBe('resource_not_allowed');
    expect($result->getStatusCode())->toBe(403);
});

it('resolveResource allows access when token has full permissions', function () {
    $token = makeToken($this->user, ['write']); // null allowed_resources = all

    $request = Request::create('/api/v1/admin/posts', 'POST');
    $request->attributes->set('api_forge_token', $token);
    $this->app->instance('request', $request);

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\PostResource',
        'model_class'    => 'App\\Models\\Post',
        'slug'           => 'posts',
        'api_config'     => ['allowed_methods' => ['store']],
    ]);
    $mock->shouldReceive('isMethodAllowed')->andReturn(true);

    $controller = new ApiResourceController($mock);

    $ref = new ReflectionMethod($controller, 'resolveResource');
    $result = $ref->invoke($controller, 'admin', 'posts', 'store');

    // Should NOT be a JsonResponse (i.e., no error)
    // The result should be the resource array
    expect($result)->toBeArray()
        ->and($result)->toHaveKey('resource_class');
});

it('controller scope map covers all CRUD methods', function () {
    $ref = new ReflectionClass(ApiResourceController::class);
    $scopeMap = $ref->getConstant('SCOPE_MAP');

    expect($scopeMap)->toBe([
        'index'       => 'read',
        'show'        => 'read',
        'export'      => 'read',
        'store'       => 'write',
        'update'      => 'write',
        'restore'     => 'write',
        'destroy'     => 'delete',
        'forceDelete' => 'delete',
    ]);
});
