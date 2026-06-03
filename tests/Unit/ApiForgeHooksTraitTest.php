<?php

use YusufGenc34\FilamentApiForge\Traits\ApiForgeHooks;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController;
use Illuminate\Database\Eloquent\Model;

it('usesApiForgeHooks returns true for class using trait', function () {
    $controller = new ApiResourceController(
        Mockery::mock(\YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService::class)
    );

    $class = new class {
        use ApiForgeHooks;
    };

    $ref = new ReflectionMethod($controller, 'usesApiForgeHooks');
    $result = $ref->invoke($controller, get_class($class));

    expect($result)->toBeTrue();
});

it('usesApiForgeHooks returns false for class without trait', function () {
    $controller = new ApiResourceController(
        Mockery::mock(\YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService::class)
    );

    $ref = new ReflectionMethod($controller, 'usesApiForgeHooks');
    $result = $ref->invoke($controller, Model::class);

    expect($result)->toBeFalse();
});

it('executeBeforeHooks returns data unchanged when no trait', function () {
    $controller = new ApiResourceController(
        Mockery::mock(\YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService::class)
    );

    config()->set('filament-api-forge.events.enabled', true);

    $ref = new ReflectionMethod($controller, 'executeBeforeHooks');
    $result = $ref->invoke($controller, Model::class, 'beforeCreate', ['title' => 'Test']);

    expect($result)->toBe(['title' => 'Test']);
});

it('executeAfterHooks does not throw for non-trait class', function () {
    $controller = new ApiResourceController(
        Mockery::mock(\YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService::class)
    );

    config()->set('filament-api-forge.events.enabled', true);

    $ref = new ReflectionMethod($controller, 'executeAfterHooks');
    $model = Mockery::mock(Model::class);

    expect(fn () => $ref->invoke($controller, Model::class, 'afterCreate', $model, ['title' => 'Test']))
        ->not->toThrow(\Exception::class);
});

it('executeVoidHooks does not throw for non-trait class', function () {
    $controller = new ApiResourceController(
        Mockery::mock(\YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService::class)
    );

    config()->set('filament-api-forge.events.enabled', true);

    $ref = new ReflectionMethod($controller, 'executeVoidHooks');
    $model = Mockery::mock(Model::class);

    expect(fn () => $ref->invoke($controller, Model::class, 'beforeDelete', $model))
        ->not->toThrow(\Exception::class);
});

it('hooks are skipped when events config is disabled', function () {
    $controller = new ApiResourceController(
        Mockery::mock(\YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService::class)
    );

    config()->set('filament-api-forge.events.enabled', false);

    $ref = new ReflectionMethod($controller, 'executeBeforeHooks');

    $class = new class {
        use ApiForgeHooks;
    };

    // Even with hooks trait, should return data unchanged when disabled
    $result = $ref->invoke($controller, get_class($class), 'beforeCreate', ['title' => 'Test']);
    expect($result)->toBe(['title' => 'Test']);
});

it('shouldSkipHooks auto-resets after a single read', function () {
    // Two consecutive reads: first true, second false
    $class = new class {
        use ApiForgeHooks;
    };

    $class::withoutHooks();
    expect($class::shouldSkipHooks())->toBeTrue();
    expect($class::shouldSkipHooks())->toBeFalse();
});
