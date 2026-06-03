<?php

use YusufGenc34\FilamentApiForge\Traits\ApiForgeHooks;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreating;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdating;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleting;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

// ── Stub with hooks ────────────────────────────────────────────────────────

class HookedResource
{
    use ApiForgeHooks;

    public static array $calls = [];

    public static function beforeCreate(array $data): array
    {
        static::$calls[] = 'beforeCreate';
        $data['hooked'] = true;
        return $data;
    }

    public static function afterCreate(Model $record, array $data): void
    {
        static::$calls[] = 'afterCreate';
    }

    public static function beforeUpdate(Model $record, array $data): array
    {
        static::$calls[] = 'beforeUpdate';
        $data['updated_hook'] = true;
        return $data;
    }

    public static function afterUpdate(Model $record, array $data): void
    {
        static::$calls[] = 'afterUpdate';
    }

    public static function beforeDelete(Model $record): void
    {
        static::$calls[] = 'beforeDelete';
    }

    public static function afterDelete(Model $record): void
    {
        static::$calls[] = 'afterDelete';
    }

    public static function resetCalls(): void
    {
        static::$calls = [];
    }
}

// ── Unit: Trait Tests ──────────────────────────────────────────────────────

it('withoutHooks sets skip flag', function () {
    HookedResource::resetCalls();

    HookedResource::withoutHooks();

    expect(HookedResource::shouldSkipHooks())->toBeTrue();
});

it('shouldSkipHooks auto-resets after read', function () {
    HookedResource::resetCalls();

    HookedResource::withoutHooks();
    expect(HookedResource::shouldSkipHooks())->toBeTrue();
    expect(HookedResource::shouldSkipHooks())->toBeFalse();
});

it('shouldSkipHooks returns false by default', function () {
    HookedResource::resetCalls();

    expect(HookedResource::shouldSkipHooks())->toBeFalse();
});

it('beforeCreate hook can modify data', function () {
    HookedResource::resetCalls();

    $data = HookedResource::beforeCreate(['title' => 'Original']);

    expect($data)->toHaveKey('hooked', true)
        ->and($data['title'])->toBe('Original')
        ->and(HookedResource::$calls)->toContain('beforeCreate');
});

it('afterCreate hook is called', function () {
    HookedResource::resetCalls();

    $model = Mockery::mock(Model::class);
    HookedResource::afterCreate($model, ['title' => 'Test']);

    expect(HookedResource::$calls)->toContain('afterCreate');
});

it('beforeUpdate hook can modify data', function () {
    HookedResource::resetCalls();

    $model = Mockery::mock(Model::class);
    $data = HookedResource::beforeUpdate($model, ['title' => 'Original']);

    expect($data)->toHaveKey('updated_hook', true)
        ->and(HookedResource::$calls)->toContain('beforeUpdate');
});

it('afterUpdate hook is called', function () {
    HookedResource::resetCalls();

    $model = Mockery::mock(Model::class);
    HookedResource::afterUpdate($model, ['title' => 'Test']);

    expect(HookedResource::$calls)->toContain('afterUpdate');
});

it('beforeDelete hook is called', function () {
    HookedResource::resetCalls();

    $model = Mockery::mock(Model::class);
    HookedResource::beforeDelete($model);

    expect(HookedResource::$calls)->toContain('beforeDelete');
});

it('afterDelete hook is called', function () {
    HookedResource::resetCalls();

    $model = Mockery::mock(Model::class);
    HookedResource::afterDelete($model);

    expect(HookedResource::$calls)->toContain('afterDelete');
});

it('default trait hooks are no-ops', function () {
    $class = new class {
        use ApiForgeHooks;
    };

    $model = Mockery::mock(Model::class);

    expect($class::beforeCreate([]))->toBe([]);
    expect(fn () => $class::afterCreate($model, []))->not->toThrow(\Exception::class);
    expect($class::beforeUpdate($model, []))->toBe([]);
    expect(fn () => $class::afterUpdate($model, []))->not->toThrow(\Exception::class);
    expect(fn () => $class::beforeDelete($model))->not->toThrow(\Exception::class);
    expect(fn () => $class::afterDelete($model))->not->toThrow(\Exception::class);
});

// ── Feature: Event Dispatching ─────────────────────────────────────────────

it('ApiResourceCreating event can be dispatched', function () {
    Event::fake();

    ApiResourceCreating::dispatch('App\\Filament\\Resources\\PostResource', ['title' => 'Test']);

    Event::assertDispatched(ApiResourceCreating::class, function ($event) {
        return $event->resourceClass === 'App\\Filament\\Resources\\PostResource'
            && $event->data['title'] === 'Test';
    });
});

it('ApiResourceCreated event can be dispatched', function () {
    Event::fake();

    $model = Mockery::mock(Model::class);
    ApiResourceCreated::dispatch('App\\Filament\\Resources\\PostResource', $model, ['title' => 'Test']);

    Event::assertDispatched(ApiResourceCreated::class);
});

it('ApiResourceUpdating event can be dispatched', function () {
    Event::fake();

    $model = Mockery::mock(Model::class);
    ApiResourceUpdating::dispatch('App\\Filament\\Resources\\PostResource', $model, ['title' => 'Updated']);

    Event::assertDispatched(ApiResourceUpdating::class);
});

it('ApiResourceUpdated event can be dispatched', function () {
    Event::fake();

    $model = Mockery::mock(Model::class);
    ApiResourceUpdated::dispatch('App\\Filament\\Resources\\PostResource', $model, ['title' => 'Updated']);

    Event::assertDispatched(ApiResourceUpdated::class);
});

it('ApiResourceDeleting event can be dispatched', function () {
    Event::fake();

    $model = Mockery::mock(Model::class);
    ApiResourceDeleting::dispatch('App\\Filament\\Resources\\PostResource', $model);

    Event::assertDispatched(ApiResourceDeleting::class);
});

it('ApiResourceDeleted event can be dispatched', function () {
    Event::fake();

    $model = Mockery::mock(Model::class);
    ApiResourceDeleted::dispatch('App\\Filament\\Resources\\PostResource', $model);

    Event::assertDispatched(ApiResourceDeleted::class);
});
