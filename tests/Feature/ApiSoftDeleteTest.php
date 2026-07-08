<?php

use YusufGenc34\FilamentApiForge\Events\ApiResourceForceDeleted;
use YusufGenc34\FilamentApiForge\Events\ApiResourceRestored;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use YusufGenc34\FilamentApiForge\Traits\ApiForgeHooks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

// ── Stubs ──────────────────────────────────────────────────────────────────

class SoftDeleteModel extends Model
{
    use SoftDeletes;

    protected $table = 'soft_delete_models';
    protected $fillable = ['title'];
}

class SoftDeleteResource
{
    use ApiForgeHooks;

    public static array $hookLog = [];

    public static function apiConfig(): array
    {
        return [
            'allowed_methods' => ['index', 'show', 'destroy', 'restore', 'forceDelete'],
        ];
    }

    public static function beforeRestore($record): void
    {
        static::$hookLog[] = 'beforeRestore';
    }

    public static function afterRestore($record): void
    {
        static::$hookLog[] = 'afterRestore';
    }

    public static function beforeForceDelete($record): void
    {
        static::$hookLog[] = 'beforeForceDelete';
    }

    public static function afterForceDelete($record): void
    {
        static::$hookLog[] = 'afterForceDelete';
    }
}

function softDeleteController(): ApiResourceController
{
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => SoftDeleteResource::class,
        'model_class'    => SoftDeleteModel::class,
        'slug'           => 'soft-deletes',
        'plural_label'   => 'Soft Deletes',
        'api_config'     => SoftDeleteResource::apiConfig(),
    ]);
    $mock->shouldReceive('isMethodAllowed')->andReturnUsing(
        fn (array $resource, string $method) => in_array($method, $resource['api_config']['allowed_methods'])
    );
    $mock->shouldReceive('getAllowedFilters')->andReturn([]);
    $mock->shouldReceive('getAllowedSorts')->andReturn([]);
    $mock->shouldReceive('getAllowedFields')->andReturn([]);
    $mock->shouldReceive('getAllowedIncludes')->andReturn([]);

    return new ApiResourceController($mock);
}

beforeEach(function () {
    if (! Schema::hasTable('soft_delete_models')) {
        Schema::create('soft_delete_models', function ($table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    SoftDeleteModel::withTrashed()->forceDelete();
    SoftDeleteResource::$hookLog = [];
    config()->set('filament-api-forge.auth.enabled', false);
});

it('restore endpoint restores a trashed record with hooks and events', function () {
    Event::fake();

    $record = SoftDeleteModel::create(['title' => 'Trashed']);
    $record->delete();

    expect(SoftDeleteModel::count())->toBe(0);

    $request = Request::create('/api/v1/admin/soft-deletes/1/restore', 'POST');
    $response = softDeleteController()->restore($request, 'admin', 'soft-deletes', (string) $record->id);

    expect(SoftDeleteModel::count())->toBe(1)
        ->and(SoftDeleteResource::$hookLog)->toBe(['beforeRestore', 'afterRestore']);

    Event::assertDispatched(ApiResourceRestored::class);
});

it('force delete endpoint permanently removes a record', function () {
    Event::fake();

    $record = SoftDeleteModel::create(['title' => 'Doomed']);
    $record->delete();

    $request = Request::create('/api/v1/admin/soft-deletes/1/force', 'DELETE');
    $response = softDeleteController()->forceDestroy($request, 'admin', 'soft-deletes', (string) $record->id);

    expect($response->getData(true)['deleted'])->toBeTrue()
        ->and(SoftDeleteModel::withTrashed()->count())->toBe(0)
        ->and(SoftDeleteResource::$hookLog)->toBe(['beforeForceDelete', 'afterForceDelete']);

    Event::assertDispatched(ApiResourceForceDeleted::class);
});

it('index supports trashed=only and trashed=with filters', function () {
    SoftDeleteModel::create(['title' => 'Alive']);
    SoftDeleteModel::create(['title' => 'Dead'])->delete();

    $controller = softDeleteController();

    $make = function (string $query) {
        $request = Request::create('/api/v1/admin/soft-deletes' . $query, 'GET');
        app()->instance('request', $request);
        return $request;
    };

    $default = $controller->index($make(''), 'admin', 'soft-deletes');
    $only    = $controller->index($make('?trashed=only'), 'admin', 'soft-deletes');
    $with    = $controller->index($make('?trashed=with'), 'admin', 'soft-deletes');

    $count = fn ($res, $req) => count($res->toResponse($req)->getData(true)['data']);

    expect($count($default, request()))->toBe(1)
        ->and($count($only, request()))->toBe(1)
        ->and($count($with, request()))->toBe(2);
});

it('restore returns 404 for a record that is not trashed', function () {
    $record = SoftDeleteModel::create(['title' => 'Alive']);

    $request = Request::create('/api/v1/admin/soft-deletes/1/restore', 'POST');

    expect(fn () => softDeleteController()->restore($request, 'admin', 'soft-deletes', (string) $record->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('restore returns 405 when restore is not in allowed_methods', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => SoftDeleteResource::class,
        'model_class'    => SoftDeleteModel::class,
        'slug'           => 'soft-deletes',
        'api_config'     => ['allowed_methods' => ['index', 'show']],
    ]);
    $mock->shouldReceive('isMethodAllowed')->andReturnUsing(
        fn (array $resource, string $method) => in_array($method, $resource['api_config']['allowed_methods'])
    );

    $controller = new ApiResourceController($mock);
    $request = Request::create('/api/v1/admin/soft-deletes/1/restore', 'POST');

    $response = $controller->restore($request, 'admin', 'soft-deletes', '1');

    expect($response->getStatusCode())->toBe(405);
});
