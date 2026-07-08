<?php

use YusufGenc34\FilamentApiForge\Attributes\ApiVersion;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController;
use YusufGenc34\FilamentApiForge\Http\Middleware\SetApiForgeVersion;
use YusufGenc34\FilamentApiForge\Http\Resources\ApiForgeJsonResource;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use YusufGenc34\FilamentApiForge\Tests\Stubs\TestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

// ── Stubs ──────────────────────────────────────────────────────────────────

#[ApiVersion('v2')]
class V2OnlyResource
{
    public static function apiConfig(): array
    {
        return [];
    }
}

class TransformedResource
{
    public static function apiConfig(): array
    {
        return [
            'allowed_methods' => ['index', 'show'],
            'scope_map'       => ['show' => 'special:read'],
        ];
    }

    public static function apiTransform($record, array $data): array
    {
        $data['title_upper'] = strtoupper($data['title'] ?? '');
        unset($data['body']);

        return $data;
    }
}

// ── API Versioning ─────────────────────────────────────────────────────────

it('ApiVersion attribute restricts resource availability per version', function () {
    $service = new ResourceDiscoveryService();

    $versions = (new ReflectionMethod($service, 'resourceVersions'))
        ->invoke($service, V2OnlyResource::class);

    expect($versions)->toBe(['v2']);

    $resource = ['versions' => ['v2']];

    expect($service->resourceAvailableIn($resource, 'v2'))->toBeTrue()
        ->and($service->resourceAvailableIn($resource, 'v1'))->toBeFalse()
        ->and($service->resourceAvailableIn($resource, null))->toBeTrue()
        ->and($service->resourceAvailableIn(['versions' => null], 'v1'))->toBeTrue();
});

it('SetApiForgeVersion middleware sets the version request attribute', function () {
    $request = Request::create('/api/v2/admin/posts', 'GET');

    (new SetApiForgeVersion())->handle($request, fn ($r) => response('ok'), 'v2');

    expect($request->attributes->get('api_forge_version'))->toBe('v2');
});

it('multi-version mode registers prefixed route sets', function () {
    config()->set('filament-api-forge.versions', ['v1', 'v2']);
    config()->set('filament-api-forge.api_base', 'api');

    $provider = new \YusufGenc34\FilamentApiForge\FilamentApiForgeServiceProvider(app());
    (new ReflectionMethod($provider, 'registerApiRoutes'))->invoke($provider);

    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes());

    $v2Index = $routes->first(fn ($r) => $r->getName() === 'v2.api-forge.index');

    expect($v2Index)->not->toBeNull()
        ->and($v2Index->uri())->toBe('api/v2/{panelId}/{resourceSlug}')
        ->and(collect($v2Index->gatherMiddleware())->contains(
            fn ($m) => is_string($m) && str_contains($m, 'SetApiForgeVersion')
        ))->toBeTrue();
});

// ── Custom scopes (scope_map) ──────────────────────────────────────────────

it('scope_map overrides the default scope for a method', function () {
    config()->set('filament-api-forge.auth.enabled', true);

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => TransformedResource::class,
        'model_class'    => TestModel::class,
        'slug'           => 'transformed',
        'api_config'     => TransformedResource::apiConfig(),
    ]);
    $mock->shouldReceive('isMethodAllowed')->andReturn(true);

    $controller = new ApiResourceController($mock);
    $resolve = new ReflectionMethod($controller, 'resolveResource');

    // Token with only the generic read scope → rejected for custom-scoped 'show'
    $genericToken = new ApiForgeToken(['scopes' => ['read']]);
    $request = Request::create('/api/v1/admin/transformed/1', 'GET');
    $request->attributes->set('api_forge_token', $genericToken);
    app()->instance('request', $request);

    $denied = $resolve->invoke($controller, 'admin', 'transformed', 'show');

    expect($denied)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class)
        ->and($denied->getStatusCode())->toBe(403)
        ->and($denied->getData(true)['required_scope'])->toBe('special:read');

    // Token carrying the custom scope → allowed
    $customToken = new ApiForgeToken(['scopes' => ['special:read']]);
    $request->attributes->set('api_forge_token', $customToken);

    $granted = $resolve->invoke($controller, 'admin', 'transformed', 'show');

    expect($granted)->toBeArray();
});

// ── Response transformation ────────────────────────────────────────────────

it('apiTransform reshapes serialized records', function () {
    if (! Schema::hasTable('test_models')) {
        Schema::create('test_models', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    TestModel::query()->delete();
    $record = TestModel::create(['title' => 'hello world', 'body' => 'secret']);

    config()->set('filament-api-forge.auth.enabled', false);

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => TransformedResource::class,
        'model_class'    => TestModel::class,
        'slug'           => 'transformed',
        'plural_label'   => 'Transformed',
        'api_config'     => ['allowed_methods' => ['show']],
    ]);
    $mock->shouldReceive('isMethodAllowed')->andReturn(true);
    $mock->shouldReceive('getAllowedFields')->andReturn([]);
    $mock->shouldReceive('getAllowedIncludes')->andReturn([]);

    $controller = new ApiResourceController($mock);
    $request = Request::create('/api/v1/admin/transformed/' . $record->id, 'GET');
    app()->instance('request', $request);

    $response = $controller->show($request, 'admin', 'transformed', (string) $record->id);
    $data = $response->toResponse($request)->getData(true)['data'];

    expect($data['title_upper'])->toBe('HELLO WORLD')
        ->and($data)->not->toHaveKey('body');

    ApiForgeJsonResource::withTransformer(null);
});

it('OpenAPI spec is version-aware in multi-version mode', function () {
    config()->set('filament-api-forge.versions', ['v1', 'v2']);
    config()->set('filament-api-forge.api_base', 'api');

    $controller = app(\YusufGenc34\FilamentApiForge\Http\Controllers\ApiDocumentationController::class);

    $v1 = $controller->openApiSpec(Request::create('/docs/openapi.json'))->getData(true);
    $v2 = $controller->openApiSpec(Request::create('/docs/openapi.json?version=v2'))->getData(true);

    expect($v1['info']['version'])->toBe('v1')
        ->and($v1['servers'][0]['url'])->toEndWith('api/v1')
        ->and($v2['info']['version'])->toBe('v2')
        ->and($v2['servers'][0]['url'])->toEndWith('api/v2');

    // Unknown versions fall back to the first configured version
    $bogus = $controller->openApiSpec(Request::create('/docs/openapi.json?version=v99'))->getData(true);
    expect($bogus['info']['version'])->toBe('v1');
});
