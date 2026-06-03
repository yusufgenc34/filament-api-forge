<?php

use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;

// ── Array-based helper tests (no Filament dependency) ──────────────────────

it('isMethodAllowed returns true for configured methods', function () {
    $service = app(ResourceDiscoveryService::class);

    $resource = ['api_config' => ['allowed_methods' => ['index', 'show']]];

    expect($service->isMethodAllowed($resource, 'index'))->toBeTrue()
        ->and($service->isMethodAllowed($resource, 'show'))->toBeTrue()
        ->and($service->isMethodAllowed($resource, 'store'))->toBeFalse();
});

it('isMethodAllowed falls back to config defaults', function () {
    $service = app(ResourceDiscoveryService::class);

    $resource = ['api_config' => []];

    expect($service->isMethodAllowed($resource, 'index'))->toBeTrue()
        ->and($service->isMethodAllowed($resource, 'store'))->toBeTrue()
        ->and($service->isMethodAllowed($resource, 'destroy'))->toBeTrue();
});

it('returns configured allowed filters', function () {
    $service = app(ResourceDiscoveryService::class);
    $resource = ['api_config' => ['allowed_filters' => ['title', 'status']]];

    expect($service->getAllowedFilters($resource))->toBe(['title', 'status']);
});

it('returns empty array when no filters configured', function () {
    $service = app(ResourceDiscoveryService::class);

    expect($service->getAllowedFilters([]))->toBe([]);
});

it('returns configured allowed sorts', function () {
    $service = app(ResourceDiscoveryService::class);
    $resource = ['api_config' => ['allowed_sorts' => ['created_at', 'title']]];

    expect($service->getAllowedSorts($resource))->toBe(['created_at', 'title']);
});

it('returns configured allowed includes', function () {
    $service = app(ResourceDiscoveryService::class);
    $resource = ['api_config' => ['allowed_includes' => ['author', 'comments']]];

    expect($service->getAllowedIncludes($resource))->toBe(['author', 'comments']);
});

it('returns configured allowed fields', function () {
    $service = app(ResourceDiscoveryService::class);
    $resource = ['api_config' => ['allowed_fields' => ['id', 'title', 'body']]];

    expect($service->getAllowedFields($resource))->toBe(['id', 'title', 'body']);
});

it('returns required scopes from resource config', function () {
    $service = app(ResourceDiscoveryService::class);
    $resource = ['api_config' => ['scopes' => ['read', 'write', 'delete']]];

    expect($service->getRequiredScopes($resource))->toBe(['read', 'write', 'delete']);
});

// ── Mocked discover / findResource tests ────────────────────────────────────

it('returns empty collection when discover yields no resources', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class)->makePartial();
    $mock->shouldReceive('discover')->andReturn(collect());

    expect($mock->discover()->isEmpty())->toBeTrue();
});

it('findResource returns null when not found', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class)->makePartial();
    $mock->shouldReceive('discover')->andReturn(collect());

    expect($mock->findResource('admin', 'posts'))->toBeNull();
});

it('findResource returns resource when panel and slug match', function () {
    $expected = [
        'resource_class' => 'App\\Filament\\Resources\\PostResource',
        'slug'           => 'posts',
        'panel_id'       => 'admin',
        'api_config'     => ['allowed_methods' => ['index']],
    ];

    $mock = Mockery::mock(ResourceDiscoveryService::class)->makePartial();
    $mock->shouldReceive('discover')->andReturn(collect([$expected]));

    $result = $mock->findResource('admin', 'posts');

    expect($result)->toBe($expected);
});

it('findResource falls back to slug-only search across panels', function () {
    $expected = [
        'resource_class' => 'App\\Filament\\Resources\\PostResource',
        'slug'           => 'posts',
        'panel_id'       => 'other',
        'api_config'     => ['allowed_methods' => ['index']],
    ];

    $mock = Mockery::mock(ResourceDiscoveryService::class)->makePartial();
    $mock->shouldReceive('discover')->andReturn(collect([$expected]));

    // Searching with panel 'admin' but resource is in 'other' panel
    $result = $mock->findResource('admin', 'posts');

    // Falls back to slug-only match
    expect($result)->toBe($expected);
});

it('flush clears cached resources', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class)->makePartial();
    $mock->shouldReceive('discover')
        ->once()
        ->andReturn(collect(['first_call']));

    $first = $mock->discover();
    expect($first->first())->toBe('first_call');

    // After flush, mocking discover again
    $mock->flush();

    $mock->shouldReceive('discover')
        ->once()
        ->andReturn(collect(['second_call']));

    $second = $mock->discover();
    expect($second->first())->toBe('second_call');
});
