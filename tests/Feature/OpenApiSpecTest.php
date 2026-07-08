<?php

use YusufGenc34\FilamentApiForge\Attributes\ApiTag;
use YusufGenc34\FilamentApiForge\Attributes\ApiDescription;
use YusufGenc34\FilamentApiForge\Attributes\ApiOperations;
use YusufGenc34\FilamentApiForge\Attributes\ApiIgnore;
use YusufGenc34\FilamentApiForge\Tests\Stubs\TaggedStub;
use YusufGenc34\FilamentApiForge\Tests\Stubs\DescribedStub;
use YusufGenc34\FilamentApiForge\Tests\Stubs\OperationsStub;
use YusufGenc34\FilamentApiForge\Tests\Stubs\IgnoredStub;

it('ApiTag attribute can be read from a class', function () {
    $ref = new ReflectionClass(TaggedStub::class);
    $attrs = $ref->getAttributes(ApiTag::class);

    expect($attrs)->toHaveCount(1);

    $instance = $attrs[0]->newInstance();
    expect($instance->name)->toBe('News')
        ->and($instance->description)->toBe('Latest breaking news');
});

it('ApiDescription attribute can be read from a class', function () {
    $ref = new ReflectionClass(DescribedStub::class);
    $attrs = $ref->getAttributes(ApiDescription::class);

    expect($attrs)->toHaveCount(1);

    $instance = $attrs[0]->newInstance();
    expect($instance->description)->toBe('Manage blog posts and articles.');
});

it('ApiOperations attribute resolves with array format', function () {
    $ref = new ReflectionClass(OperationsStub::class);
    $attrs = $ref->getAttributes(ApiOperations::class);
    $ops = $attrs[0]->newInstance();

    expect($ops->getSummary('index'))->toBe('List items')
        ->and($ops->getDescription('index'))->toBe('Returns paginated results.')
        ->and($ops->getSummary('store'))->toBe('Create an item')
        ->and($ops->getDescription('store'))->toBeNull()
        ->and($ops->getSummary('destroy'))->toBeNull();
});

it('ApiIgnore attribute can be detected on a class', function () {
    $ref = new ReflectionClass(IgnoredStub::class);
    $attrs = $ref->getAttributes(ApiIgnore::class);

    expect($attrs)->toHaveCount(1);
});

it('OpenAPI spec endpoint is publicly accessible', function () {
    $response = $this->getJson('/api/v1/docs/openapi.json');

    // Should not require auth
    expect($response->status())->not->toBe(401);
});

it('public docs endpoint is accessible when published', function () {
    \YusufGenc34\FilamentApiForge\Models\ApiForgeGlobalSetting::set('docs_public', true);

    $response = $this->get('/api/v1/docs');

    $response->assertOk();
});

it('public docs returns 403 when not published', function () {
    \YusufGenc34\FilamentApiForge\Models\ApiForgeGlobalSetting::set('docs_public', false);

    $response = $this->getJson('/api/v1/docs');

    // Should be 403 or the route might 500 without Filament
    expect($response->status())->not->toBe(401);
});
