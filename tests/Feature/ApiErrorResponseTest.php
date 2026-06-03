<?php

use YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;

it('resolveResource returns 404 json for unknown resource', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn(null);

    $controller = new ApiResourceController($mock);

    $ref = new ReflectionMethod($controller, 'resolveResource');
    $result = $ref->invoke($controller, 'admin', 'nonexistent', 'index');

    expect($result)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);

    $data = $result->getData(true);
    expect($data['error'])->toBe('not_found');
    expect($result->getStatusCode())->toBe(404);
});

it('resolveResource returns 405 for disabled method', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\PostResource',
        'model_class'    => 'App\\Models\\Post',
        'slug'           => 'posts',
        'api_config'     => ['allowed_methods' => ['index', 'show']],
    ]);
    $mock->shouldReceive('isMethodAllowed')->andReturn(false);

    $controller = new ApiResourceController($mock);

    $ref = new ReflectionMethod($controller, 'resolveResource');
    $result = $ref->invoke($controller, 'admin', 'posts', 'store');

    expect($result)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);

    $data = $result->getData(true);
    expect($data['error'])->toBe('method_not_allowed');
    expect($result->getStatusCode())->toBe(405);
});
