<?php

use YusufGenc34\FilamentApiForge\Http\Controllers\ApiBatchController;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use YusufGenc34\FilamentApiForge\Tests\Stubs\BatchTestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function makeJsonRequest(string $uri, array $body): Request
{
    $request = Request::create($uri, 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($body));
    $request->headers->set('Accept', 'application/json');
    return $request;
}

beforeEach(function () {
    Schema::create('batch_test_models', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('status')->default('draft');
        $table->integer('priority')->default(0);
        $table->timestamps();
    });
});

it('batch controller creates records in transaction', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\BatchResource',
        'model_class'    => BatchTestModel::class,
        'slug'           => 'batch-test',
        'api_config'     => ['batch' => ['max_size' => 50]],
    ]);

    $controller = new ApiBatchController($mock);
    $request = makeJsonRequest('/api/v1/admin/batch-test/batch', [
        'create' => [
            ['title' => 'Post A', 'status' => 'draft'],
            ['title' => 'Post B', 'status' => 'published'],
        ],
    ]);

    $response = $controller->batch($request, 'admin', 'batch-test');

    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect(count($data['created']))->toBe(2);
    expect(BatchTestModel::count())->toBe(2);
});

it('batch controller updates records', function () {
    BatchTestModel::create(['title' => 'A', 'status' => 'draft', 'priority' => 1]);
    BatchTestModel::create(['title' => 'B', 'status' => 'draft', 'priority' => 2]);

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\BatchResource',
        'model_class'    => BatchTestModel::class,
        'slug'           => 'batch-test',
        'api_config'     => ['batch' => ['max_size' => 50]],
    ]);

    $controller = new ApiBatchController($mock);
    $request = makeJsonRequest('/api/v1/admin/batch-test/batch', [
        'update' => [
            ['id' => 1, 'status' => 'published'],
            ['id' => 2, 'status' => 'archived'],
        ],
    ]);

    $response = $controller->batch($request, 'admin', 'batch-test');
    expect($response->getStatusCode())->toBe(200);

    $data = $response->getData(true);
    expect($data)->toHaveKey('updated')
        ->and($data)->toHaveKey('created')
        ->and($data)->toHaveKey('deleted')
        ->and($data)->toHaveKey('failed')
        ->and($data['message'])->toBe('Batch operation completed.');
});

it('batch controller deletes records', function () {
    BatchTestModel::create(['title' => 'A']);
    BatchTestModel::create(['title' => 'B']);
    BatchTestModel::create(['title' => 'C']);

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\BatchResource',
        'model_class'    => BatchTestModel::class,
        'slug'           => 'batch-test',
        'api_config'     => ['batch' => ['max_size' => 50]],
    ]);

    $controller = new ApiBatchController($mock);
    $request = makeJsonRequest('/api/v1/admin/batch-test/batch', [
        'delete' => [1, 2],
    ]);

    $response = $controller->batch($request, 'admin', 'batch-test');

    expect($response->getStatusCode())->toBe(200);

    $data = json_decode($response->getContent(), true);
    expect(count($data['deleted']))->toBe(2);
    expect(BatchTestModel::count())->toBe(1);
    expect(BatchTestModel::find(3))->not->toBeNull();
});

it('batch controller respects max size limit', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\BatchResource',
        'model_class'    => BatchTestModel::class,
        'slug'           => 'batch-test',
        'api_config'     => ['batch' => ['max_size' => 2]],
    ]);

    $controller = new ApiBatchController($mock);
    $request = makeJsonRequest('/api/v1/admin/batch-test/batch', [
        'create' => [
            ['title' => 'A'], ['title' => 'B'], ['title' => 'C'],
        ],
    ]);

    try {
        $controller->batch($request, 'admin', 'batch-test');
        $this->fail('Expected validation exception was not thrown');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->status)->toBe(422);
        expect($e->errors())->toHaveKey('create');
    }
});

it('batch controller returns 404 for unknown resource', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn(null);

    $controller = new ApiBatchController($mock);
    $request = makeJsonRequest('/api/v1/admin/unknown/batch', []);

    $response = $controller->batch($request, 'admin', 'unknown');

    expect($response->getStatusCode())->toBe(404);
    expect(json_decode($response->getContent(), true)['error'])->toBe('not_found');
});
