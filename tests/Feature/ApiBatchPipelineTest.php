<?php

use YusufGenc34\FilamentApiForge\Events\ApiResourceCreated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreating;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleted;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleting;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdating;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiBatchController;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use YusufGenc34\FilamentApiForge\Tests\Stubs\BatchTestModel;
use YusufGenc34\FilamentApiForge\Traits\ApiForgeHooks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

// ── Stub resource with hooks + validation rules ────────────────────────────

class BatchHookedResource
{
    use ApiForgeHooks;

    public static array $hookLog = [];

    public static function apiConfig(): array
    {
        return [
            'validation_rules' => [
                'title'    => ['required', 'string'],
                'status'   => ['sometimes', 'string'],
                'priority' => ['sometimes', 'integer'],
            ],
        ];
    }

    public static function beforeCreate(array $data): array
    {
        static::$hookLog[] = 'beforeCreate';
        $data['title'] = strtoupper($data['title']);

        return $data;
    }

    public static function afterCreate($record, array $data): void
    {
        static::$hookLog[] = 'afterCreate';
    }

    public static function beforeUpdate($record, array $data): array
    {
        static::$hookLog[] = 'beforeUpdate';

        return $data;
    }

    public static function afterUpdate($record, array $data): void
    {
        static::$hookLog[] = 'afterUpdate';
    }

    public static function beforeDelete($record): void
    {
        static::$hookLog[] = 'beforeDelete';
    }

    public static function afterDelete($record): void
    {
        static::$hookLog[] = 'afterDelete';
    }
}

function makeBatchRequest(array $body): Request
{
    $request = Request::create('/api/v1/admin/batch-test/batch', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($body));
    $request->headers->set('Accept', 'application/json');

    return $request;
}

function makeBatchController(): ApiBatchController
{
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => BatchHookedResource::class,
        'model_class'    => BatchTestModel::class,
        'slug'           => 'batch-test',
        'api_config'     => BatchHookedResource::apiConfig(),
    ]);

    return new ApiBatchController($mock);
}

beforeEach(function () {
    if (! Schema::hasTable('batch_test_models')) {
        Schema::create('batch_test_models', function ($table) {
            $table->id();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->integer('priority')->default(0);
            $table->timestamps();
        });
    }

    BatchTestModel::query()->delete();
    BatchHookedResource::$hookLog = [];
});

it('batch create goes through Eloquent and applies beforeCreate hook transformations', function () {
    $response = makeBatchController()->batch(makeBatchRequest([
        'create' => [['title' => 'hello', 'status' => 'draft']],
    ]), 'admin', 'batch-test');

    expect($response->getStatusCode())->toBe(200);

    $data = $response->getData(true);
    expect($data['created'])->toHaveCount(1)
        ->and(BatchTestModel::first()->title)->toBe('HELLO')
        ->and(BatchHookedResource::$hookLog)->toBe(['beforeCreate', 'afterCreate']);
});

it('batch create reports per-row validation failures without aborting the batch', function () {
    $response = makeBatchController()->batch(makeBatchRequest([
        'create' => [
            ['status' => 'draft'],            // missing required title
            ['title' => 'valid row'],
        ],
    ]), 'admin', 'batch-test');

    $data = $response->getData(true);

    expect($data['created'])->toHaveCount(1)
        ->and($data['failed'])->toHaveCount(1)
        ->and($data['failed'][0]['operation'])->toBe('create')
        ->and($data['failed'][0]['index'])->toBe(0)
        ->and($data['failed'][0]['errors'])->toHaveKey('title')
        ->and(BatchTestModel::count())->toBe(1);
});

it('batch update runs hooks and validates rows', function () {
    $record = BatchTestModel::create(['title' => 'A', 'status' => 'draft']);

    $response = makeBatchController()->batch(makeBatchRequest([
        'update' => [['id' => $record->id, 'status' => 'published']],
    ]), 'admin', 'batch-test');

    $data = $response->getData(true);

    expect($data['updated'])->toBe([$record->id])
        ->and($record->fresh()->status)->toBe('published')
        ->and(BatchHookedResource::$hookLog)->toBe(['beforeUpdate', 'afterUpdate']);
});

it('batch delete runs delete hooks and reports missing records as failed', function () {
    $record = BatchTestModel::create(['title' => 'A']);

    $response = makeBatchController()->batch(makeBatchRequest([
        'delete' => [$record->id, 9999],
    ]), 'admin', 'batch-test');

    $data = $response->getData(true);

    expect($data['deleted'])->toBe([$record->id])
        ->and($data['failed'])->toHaveCount(1)
        ->and($data['failed'][0]['reason'])->toBe('Record not found.')
        ->and(BatchHookedResource::$hookLog)->toBe(['beforeDelete', 'afterDelete'])
        ->and(BatchTestModel::count())->toBe(0);
});

it('batch dispatches lifecycle events for each operation', function () {
    Event::fake();

    $record = BatchTestModel::create(['title' => 'existing']);
    $target = BatchTestModel::create(['title' => 'to delete']);

    makeBatchController()->batch(makeBatchRequest([
        'create' => [['title' => 'new row']],
        'update' => [['id' => $record->id, 'status' => 'published']],
        'delete' => [$target->id],
    ]), 'admin', 'batch-test');

    Event::assertDispatched(ApiResourceCreating::class);
    Event::assertDispatched(ApiResourceCreated::class);
    Event::assertDispatched(ApiResourceUpdating::class);
    Event::assertDispatched(ApiResourceUpdated::class);
    Event::assertDispatched(ApiResourceDeleting::class);
    Event::assertDispatched(ApiResourceDeleted::class);
});

it('batch respects dispatch_events=false while still running hooks', function () {
    Event::fake();
    config()->set('filament-api-forge.events.dispatch_events', false);

    makeBatchController()->batch(makeBatchRequest([
        'create' => [['title' => 'hello']],
    ]), 'admin', 'batch-test');

    Event::assertNotDispatched(ApiResourceCreating::class);
    Event::assertNotDispatched(ApiResourceCreated::class);

    expect(BatchHookedResource::$hookLog)->toBe(['beforeCreate', 'afterCreate'])
        ->and(BatchTestModel::first()->title)->toBe('HELLO');
});

it('withoutHooks suppresses both before and after hooks for the whole batch', function () {
    BatchHookedResource::withoutHooks();

    makeBatchController()->batch(makeBatchRequest([
        'create' => [
            ['title' => 'row one'],
            ['title' => 'row two'],
        ],
    ]), 'admin', 'batch-test');

    expect(BatchHookedResource::$hookLog)->toBe([])
        ->and(BatchTestModel::pluck('title')->all())->toBe(['row one', 'row two']);
});

it('events.enabled=false disables hooks and events entirely', function () {
    Event::fake();
    config()->set('filament-api-forge.events.enabled', false);

    makeBatchController()->batch(makeBatchRequest([
        'create' => [['title' => 'plain']],
    ]), 'admin', 'batch-test');

    Event::assertNotDispatched(ApiResourceCreating::class);

    expect(BatchHookedResource::$hookLog)->toBe([])
        ->and(BatchTestModel::first()->title)->toBe('plain');
});
