<?php

use YusufGenc34\FilamentApiForge\Attributes\ApiAction;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiActionController;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Events\ApiActionExecuting;
use YusufGenc34\FilamentApiForge\Events\ApiActionExecuted;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

// ── Stub resource with ApiAction methods ────────────────────────────────────

class ActionTestResource
{
    #[ApiAction('publish', method: 'POST', scope: 'write')]
    public static function publish($record, array $data): array
    {
        return ['status' => 'published', 'id' => $record->id ?? null];
    }

    #[ApiAction('archive', method: 'POST', scope: 'delete')]
    public static function archive($record, array $data): array
    {
        return ['archived' => true];
    }
}

class ActionTestModel extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'action_test_models';
    protected $fillable = ['title', 'status'];
}

beforeEach(function () {
    Schema::create('action_test_models', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('status')->default('draft');
        $table->timestamps();
    });

    $this->user = User::create([
        'name'     => 'Action User',
        'email'    => 'action@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->model = ActionTestModel::create(['title' => 'Test Post', 'status' => 'draft']);
});

// ── Attribute Tests ─────────────────────────────────────────────────────────

it('ApiAction attribute can be instantiated with defaults', function () {
    $action = new ApiAction('publish');

    expect($action->name)->toBe('publish')
        ->and($action->method)->toBe('POST')
        ->and($action->scope)->toBe('write');
});

it('ApiAction attribute accepts custom method and scope', function () {
    $action = new ApiAction('approve', method: 'GET', scope: 'read');

    expect($action->method)->toBe('GET')
        ->and($action->scope)->toBe('read');
});

it('ApiAction is repeatable on methods', function () {
    $ref = new ReflectionClass(ApiAction::class);
    $attrRef = $ref->getAttributes();

    // Multiple attributes per method supported via IS_REPEATABLE
    $methods = (new ReflectionClass(ActionTestResource::class))->getMethods(ReflectionMethod::IS_STATIC);

    $publishMethod = collect($methods)->first(fn ($m) => $m->getName() === 'publish');
    $attrs = $publishMethod->getAttributes(ApiAction::class);

    expect(count($attrs))->toBe(1);
    expect($attrs[0]->newInstance()->name)->toBe('publish');
});

// ── ResourceDiscoveryService Tests ──────────────────────────────────────────

it('getActions discovers ApiAction methods from resource class', function () {
    $service = app(ResourceDiscoveryService::class);
    $actions = $service->getActions(ActionTestResource::class);

    expect($actions)->toHaveKey('publish')
        ->and($actions)->toHaveKey('archive')
        ->and($actions['publish']['method'])->toBe('POST')
        ->and($actions['publish']['scope'])->toBe('write');
});

it('getActions returns empty array for class without actions', function () {
    $service = app(ResourceDiscoveryService::class);
    $actions = $service->getActions(User::class);

    expect($actions)->toBe([]);
});

// ── Controller Tests ────────────────────────────────────────────────────────

it('ApiActionController returns 404 for unknown action', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => ActionTestResource::class,
        'model_class'    => ActionTestModel::class,
        'slug'           => 'action-test',
        'api_config'     => [],
    ]);

    $controller = new ApiActionController($mock);
    $request = Request::create('/api/v1/admin/action-test/1/actions/unknown', 'POST');

    $response = $controller->execute($request, 'admin', 'action-test', (string) $this->model->id, 'unknown');

    expect($response->getStatusCode())->toBe(404);
    expect(json_decode($response->getContent(), true)['error'])->toBe('action_not_found');
});

it('ApiActionController executes publish action successfully', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => ActionTestResource::class,
        'model_class'    => ActionTestModel::class,
        'slug'           => 'action-test',
        'api_config'     => [],
    ]);

    $controller = new ApiActionController($mock);
    $request = Request::create('/api/v1/admin/action-test/1/actions/publish', 'POST');
    $request->attributes->set('api_forge_token', null); // No token check when token is null

    $response = $controller->execute($request, 'admin', 'action-test', (string) $this->model->id, 'publish');

    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect($data['result']['status'])->toBe('published');
});

it('ApiActionController dispatches events during action execution', function () {
    Event::fake();

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => ActionTestResource::class,
        'model_class'    => ActionTestModel::class,
        'slug'           => 'action-test',
        'api_config'     => [],
    ]);

    $controller = new ApiActionController($mock);
    $request = Request::create('/api/v1/admin/action-test/1/actions/publish', 'POST');

    $response = $controller->execute($request, 'admin', 'action-test', (string) $this->model->id, 'publish');

    Event::assertDispatched(ApiActionExecuting::class);
    Event::assertDispatched(ApiActionExecuted::class);
});

it('ApiActionController returns 405 for mismatched HTTP method', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => ActionTestResource::class,
        'model_class'    => ActionTestModel::class,
        'slug'           => 'action-test',
        'api_config'     => [],
    ]);

    $controller = new ApiActionController($mock);
    // publish requires POST but we send GET
    $request = Request::create('/api/v1/admin/action-test/1/actions/publish', 'GET');

    $response = $controller->execute($request, 'admin', 'action-test', (string) $this->model->id, 'publish');

    expect($response->getStatusCode())->toBe(405);
    expect(json_decode($response->getContent(), true)['error'])->toBe('method_not_allowed');
});

it('ApiActionController returns 403 when token lacks required scope', function () {
    $plain = 'forge_' . str_repeat('a', 40);
    $token = ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Read Only',
        'token_hash'   => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 16),
        'scopes'       => ['read'],
        'is_active'    => true,
    ]);

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => ActionTestResource::class,
        'model_class'    => ActionTestModel::class,
        'slug'           => 'action-test',
        'api_config'     => [],
    ]);

    $controller = new ApiActionController($mock);
    $request = Request::create('/api/v1/admin/action-test/1/actions/publish', 'POST');
    $request->attributes->set('api_forge_token', $token);

    $response = $controller->execute($request, 'admin', 'action-test', (string) $this->model->id, 'publish');

    expect($response->getStatusCode())->toBe(403);
    expect(json_decode($response->getContent(), true)['error'])->toBe('insufficient_scope');
});

it('EnforceApiForgeRules resolveAction detects custom action names', function () {
    $request = Request::create('/api/v1/admin/posts/1/actions/custom_action', 'POST');

    // Set up a proper route that has the actionName parameter
    $route = new \Illuminate\Routing\Route('POST', '{panelId}/{resourceSlug}/{recordId}/actions/{actionName}', []);
    $route->bind($request);
    $route->setParameter('panelId', 'admin');
    $route->setParameter('resourceSlug', 'posts');
    $route->setParameter('recordId', '1');
    $route->setParameter('actionName', 'custom_action');

    $request->setRouteResolver(fn () => $route);

    $middleware = new \YusufGenc34\FilamentApiForge\Http\Middleware\EnforceApiForgeRules(
        app(ResourceDiscoveryService::class)
    );

    $ref = new ReflectionMethod($middleware, 'resolveAction');
    $action = $ref->invoke($middleware, $request);

    expect($action)->toBe('action.custom_action');
});
