<?php

use YusufGenc34\FilamentApiForge\Http\Controllers\ApiExportController;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use YusufGenc34\FilamentApiForge\Tests\Stubs\TestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

function exportController(array $apiConfig = []): ApiExportController
{
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\ExportResource',
        'model_class'    => TestModel::class,
        'slug'           => 'test-models',
        'plural_label'   => 'Test Models',
        'api_config'     => array_merge(['allowed_methods' => ['index', 'export']], $apiConfig),
    ]);
    $mock->shouldReceive('isMethodAllowed')->andReturnUsing(
        fn (array $resource, string $method) => in_array($method, $resource['api_config']['allowed_methods'])
    );
    $mock->shouldReceive('getAllowedFilters')->andReturn($apiConfig['allowed_filters'] ?? []);
    $mock->shouldReceive('getAllowedSorts')->andReturn([]);
    $mock->shouldReceive('getAllowedFields')->andReturn($apiConfig['allowed_fields'] ?? []);
    $mock->shouldReceive('getAllowedIncludes')->andReturn([]);

    return new ApiExportController($mock);
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
    config()->set('filament-api-forge.auth.enabled', false);

    TestModel::create(['title' => 'First post', 'status' => 'published']);
    TestModel::create(['title' => 'Second post', 'status' => 'draft']);
});

it('exports rows as CSV honoring headers and content', function () {
    $request = Request::create('/api/v1/admin/test-models/export', 'GET');
    app()->instance('request', $request);

    $response = exportController()->export($request, 'admin', 'test-models');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('content-type'))->toContain('text/csv');

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    $lines = array_values(array_filter(explode("\n", trim($csv))));

    expect($lines[0])->toContain('id')
        ->and($lines[0])->toContain('title')
        ->and($csv)->toContain('First post')
        ->and($csv)->toContain('Second post')
        ->and(count($lines))->toBe(3); // header + 2 rows
});

it('exports rows as JSON with meta', function () {
    $request = Request::create('/api/v1/admin/test-models/export?format=json', 'GET');
    app()->instance('request', $request);

    $response = exportController()->export($request, 'admin', 'test-models');
    $data = $response->getData(true);

    expect($data['data'])->toHaveCount(2)
        ->and($data['meta']['total'])->toBe(2);
});

it('export respects filters from the query string', function () {
    $request = Request::create('/api/v1/admin/test-models/export?format=json&filter[status]=published', 'GET');
    app()->instance('request', $request);

    $response = exportController(['allowed_filters' => ['status']])->export($request, 'admin', 'test-models');
    $data = $response->getData(true);

    expect($data['data'])->toHaveCount(1)
        ->and($data['data'][0]['title'])->toBe('First post');
});

it('export limits columns to allowed_fields when declared', function () {
    $request = Request::create('/api/v1/admin/test-models/export?format=json', 'GET');
    app()->instance('request', $request);

    $response = exportController(['allowed_fields' => ['id', 'title']])->export($request, 'admin', 'test-models');
    $data = $response->getData(true);

    expect(array_keys($data['data'][0]))->toBe(['id', 'title']);
});

it('rejects unsupported export formats with 422', function () {
    $request = Request::create('/api/v1/admin/test-models/export?format=xml', 'GET');

    $response = exportController()->export($request, 'admin', 'test-models');

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true)['error'])->toBe('unsupported_format');
});

it('returns 405 when export is not in allowed_methods', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\ExportResource',
        'model_class'    => TestModel::class,
        'slug'           => 'test-models',
        'api_config'     => ['allowed_methods' => ['index']],
    ]);
    $mock->shouldReceive('isMethodAllowed')->andReturnUsing(
        fn (array $resource, string $method) => in_array($method, $resource['api_config']['allowed_methods'])
    );

    $controller = new ApiExportController($mock);
    $request = Request::create('/api/v1/admin/test-models/export', 'GET');

    $response = $controller->export($request, 'admin', 'test-models');

    expect($response->getStatusCode())->toBe(405);
});

it('returns 403 when export is globally disabled', function () {
    config()->set('filament-api-forge.export.enabled', false);

    $request = Request::create('/api/v1/admin/test-models/export', 'GET');
    $response = exportController()->export($request, 'admin', 'test-models');

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getData(true)['error'])->toBe('export_disabled');
});
