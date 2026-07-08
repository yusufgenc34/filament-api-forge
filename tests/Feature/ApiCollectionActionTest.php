<?php

use YusufGenc34\FilamentApiForge\Attributes\ApiAction;
use YusufGenc34\FilamentApiForge\Events\ApiActionExecuted;
use YusufGenc34\FilamentApiForge\Events\ApiActionExecuting;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiActionController;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiDocumentationController;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use YusufGenc34\FilamentApiForge\Tests\Stubs\TestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// ── Stub resource with record + collection actions ─────────────────────────

class CollectionActionResource
{
    #[ApiAction('sync', method: 'POST', scope: 'write', record: false)]
    public static function sync(array $data): array
    {
        return ['synced' => true, 'received' => $data];
    }

    #[ApiAction('publish', method: 'POST', scope: 'write')]
    public static function publish($record, array $data): array
    {
        return ['published' => true];
    }
}

function makeActionController(): ApiActionController
{
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => CollectionActionResource::class,
        'model_class'    => TestModel::class,
        'slug'           => 'test-models',
        'api_config'     => [],
    ]);

    return new ApiActionController($mock);
}

beforeEach(function () {
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
});

it('executes a collection-level action without a record', function () {
    $request = Request::create('/api/v1/admin/test-models/actions/sync', 'POST', ['source' => 'crm']);

    $response = makeActionController()->executeCollection($request, 'admin', 'test-models', 'sync');

    expect($response->getStatusCode())->toBe(200);

    $data = $response->getData(true);
    expect($data['action'])->toBe('sync')
        ->and($data['result']['synced'])->toBeTrue()
        ->and($data['result']['received']['source'])->toBe('crm');
});

it('collection route returns 404 for a record-level action', function () {
    $request = Request::create('/api/v1/admin/test-models/actions/publish', 'POST');

    $response = makeActionController()->executeCollection($request, 'admin', 'test-models', 'publish');

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['error'])->toBe('action_not_found');
});

it('record route returns 404 for a collection-level action', function () {
    $record = TestModel::create(['title' => 'A post']);

    $request = Request::create('/api/v1/admin/test-models/1/actions/sync', 'POST');

    $response = makeActionController()->execute($request, 'admin', 'test-models', (string) $record->id, 'sync');

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getData(true)['error'])->toBe('action_not_found');
});

it('collection action dispatches events with a null record', function () {
    Event::fake();

    $request = Request::create('/api/v1/admin/test-models/actions/sync', 'POST');

    makeActionController()->executeCollection($request, 'admin', 'test-models', 'sync');

    Event::assertDispatched(ApiActionExecuting::class, fn ($event) => $event->record === null);
    Event::assertDispatched(ApiActionExecuted::class, fn ($event) => $event->record === null);
});

it('getActions exposes the record flag', function () {
    $actions = app(ResourceDiscoveryService::class)->getActions(CollectionActionResource::class);

    expect($actions['sync']['record'])->toBeFalse()
        ->and($actions['publish']['record'])->toBeTrue();
});

it('registers action routes before nested wildcard routes', function () {
    $names = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->values();

    $collectionActionIdx = $names->search('api-forge.action.collection');
    $recordActionIdx     = $names->search('api-forge.action');
    $nestedIndexIdx      = $names->search('api-forge.nested.index');
    $nestedShowIdx       = $names->search('api-forge.nested.show');

    expect($collectionActionIdx)->not->toBeFalse()
        ->and($recordActionIdx)->not->toBeFalse()
        ->and($nestedIndexIdx)->not->toBeFalse()
        ->and($collectionActionIdx)->toBeLessThan($nestedIndexIdx)
        ->and($recordActionIdx)->toBeLessThan($nestedShowIdx);
});

it('OpenAPI spec documents action, batch and nested endpoints', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('discoverForVersion')->andReturn(collect([
        [
            'resource_class' => CollectionActionResource::class,
            'model_class'    => TestModel::class,
            'panel_id'       => 'admin',
            'slug'           => 'test-models',
            'label'          => 'Test Model',
            'plural_label'   => 'Test Models',
            'api_config'     => [
                'allowed_methods' => ['index', 'show'],
                'relations'       => [
                    'comments' => ['relation_name' => 'comments', 'allowed_methods' => ['index', 'show']],
                ],
            ],
        ],
    ]));
    $mock->shouldReceive('getActions')->andReturn([
        'sync'    => ['name' => 'sync', 'method' => 'POST', 'scope' => 'write', 'record' => false],
        'publish' => ['name' => 'publish', 'method' => 'POST', 'scope' => 'write', 'record' => true],
    ]);

    $controller = new ApiDocumentationController($mock);
    $spec = $controller->openApiSpec(Request::create('/api/v1/docs/openapi.json'))->getData(true);

    expect($spec['paths'])->toHaveKey('/admin/test-models/actions/sync')
        ->and($spec['paths'])->toHaveKey('/admin/test-models/{id}/actions/publish')
        ->and($spec['paths'])->toHaveKey('/admin/test-models/batch')
        ->and($spec['paths'])->toHaveKey('/admin/test-models/{id}/comments')
        ->and($spec['paths'])->toHaveKey('/admin/test-models/{id}/comments/{childId}')
        ->and($spec['paths']['/admin/test-models/actions/sync'])->toHaveKey('post')
        ->and($spec['paths']['/admin/test-models/batch'])->toHaveKey('post');
});
