<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;

class TestPostModel extends Model
{
    protected $table = 'test_posts';

    protected $fillable = ['title'];
}

class TestPostPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function update(?User $user, TestPostModel $post): bool
    {
        return $user !== null && $user->name === 'Authorized User';
    }
}

beforeEach(function () {
    Gate::policy(TestPostModel::class, TestPostPolicy::class);
});

it('denies action when policy denies access', function () {
    $user = User::create([
        'name' => 'Unauthorized User',
        'email' => 'unauth@example.com',
        'password' => bcrypt('password'),
    ]);

    Auth::setUser($user);

    $plain = 'forge_'.str_repeat('a', 40);
    $token = ApiForgeToken::create([
        'user_id' => $user->id,
        'name' => 'Test Token',
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 16),
        'scopes' => ['*'],
        'is_active' => true,
    ]);

    $request = Request::create('/api/v1/admin/posts/1', 'PUT');
    $request->setUserResolver(fn () => $user);
    $request->attributes->set('api_forge_token', $token);
    $this->app->instance('request', $request);

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $controller = new ApiResourceController($mock);

    $post = new TestPostModel(['title' => 'Sample']);
    $ref = new ReflectionMethod($controller, 'checkPolicy');
    $result = $ref->invoke($controller, [
        'resource_class' => 'App\\Filament\\Resources\\TestPostResource',
        'model_class' => TestPostModel::class,
        'slug' => 'posts',
        'api_config' => ['allowed_methods' => ['update'], 'use_policies' => true],
    ], 'update', $post);

    expect($result)->toBeInstanceOf(JsonResponse::class);
    expect($result->getStatusCode())->toBe(403);
    expect($result->getData(true)['error'])->toBe('forbidden');
});

it('allows action when policy grants access', function () {
    $user = User::create([
        'name' => 'Authorized User',
        'email' => 'auth@example.com',
        'password' => bcrypt('password'),
    ]);

    Auth::setUser($user);

    $plain = 'forge_'.str_repeat('b', 40);
    $token = ApiForgeToken::create([
        'user_id' => $user->id,
        'name' => 'Test Token 2',
        'token_hash' => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 16),
        'scopes' => ['*'],
        'is_active' => true,
    ]);

    $request = Request::create('/api/v1/admin/posts/1', 'PUT');
    $request->setUserResolver(fn () => $user);
    $request->attributes->set('api_forge_token', $token);
    $this->app->instance('request', $request);

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $controller = new ApiResourceController($mock);

    $post = new TestPostModel(['title' => 'Sample']);
    $ref = new ReflectionMethod($controller, 'checkPolicy');

    $result = $ref->invoke($controller, [
        'resource_class' => 'App\\Filament\\Resources\\TestPostResource',
        'model_class' => TestPostModel::class,
        'slug' => 'posts',
        'api_config' => ['allowed_methods' => ['update'], 'use_policies' => true],
    ], 'update', $post);

    expect($result)->toBeNull();
});
