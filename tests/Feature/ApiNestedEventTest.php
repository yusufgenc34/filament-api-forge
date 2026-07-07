<?php

use YusufGenc34\FilamentApiForge\Events\ApiResourceCreated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreating;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleted;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleting;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdating;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiNestedResourceController;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

// ── Test models ────────────────────────────────────────────────────────────

class NestedEventParent extends Model
{
    protected $table = 'nested_event_parents';
    protected $fillable = ['title'];

    public function items()
    {
        return $this->hasMany(NestedEventItem::class, 'parent_id');
    }
}

class NestedEventItem extends Model
{
    protected $table = 'nested_event_items';
    protected $fillable = ['parent_id', 'name', 'position'];
}

function makeNestedController(array $relationConfig): ApiNestedResourceController
{
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\NestedEventParentResource',
        'model_class'    => NestedEventParent::class,
        'slug'           => 'parents',
        'api_config'     => ['relations' => ['items' => $relationConfig]],
    ]);

    return new ApiNestedResourceController($mock);
}

beforeEach(function () {
    Schema::create('nested_event_parents', function ($table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    Schema::create('nested_event_items', function ($table) {
        $table->id();
        $table->foreignId('parent_id')->constrained('nested_event_parents')->cascadeOnDelete();
        $table->string('name');
        $table->integer('position')->default(0);
        $table->timestamps();
    });

    $this->parent = NestedEventParent::create(['title' => 'Parent']);
});

it('nested store dispatches creating and created events', function () {
    Event::fake();

    $controller = makeNestedController([
        'relation_name'    => 'items',
        'allowed_methods'  => ['store'],
        'validation_rules' => ['name' => ['required', 'string']],
    ]);

    $request = Request::create('/api/v1/admin/parents/1/items', 'POST', ['name' => 'New item']);

    $response = $controller->store($request, 'admin', 'parents', (string) $this->parent->id, 'items');

    expect($this->parent->items()->count())->toBe(1);

    Event::assertDispatched(ApiResourceCreating::class);
    Event::assertDispatched(ApiResourceCreated::class, function ($event) {
        return $event->record instanceof NestedEventItem
            && $event->record->name === 'New item';
    });
});

it('nested update dispatches updating and updated events', function () {
    Event::fake();

    $item = $this->parent->items()->create(['name' => 'Original']);

    $controller = makeNestedController([
        'relation_name'    => 'items',
        'allowed_methods'  => ['update'],
        'validation_rules' => ['name' => ['required', 'string']],
    ]);

    $request = Request::create('/api/v1/admin/parents/1/items/1', 'PUT', ['name' => 'Renamed']);

    $controller->update($request, 'admin', 'parents', (string) $this->parent->id, 'items', (string) $item->id);

    expect($item->fresh()->name)->toBe('Renamed');

    Event::assertDispatched(ApiResourceUpdating::class);
    Event::assertDispatched(ApiResourceUpdated::class);
});

it('nested destroy dispatches deleting and deleted events', function () {
    Event::fake();

    $item = $this->parent->items()->create(['name' => 'Doomed']);

    $controller = makeNestedController([
        'relation_name'   => 'items',
        'allowed_methods' => ['destroy'],
    ]);

    $request = Request::create('/api/v1/admin/parents/1/items/1', 'DELETE');

    $controller->destroy($request, 'admin', 'parents', (string) $this->parent->id, 'items', (string) $item->id);

    expect(NestedEventItem::count())->toBe(0);

    Event::assertDispatched(ApiResourceDeleting::class);
    Event::assertDispatched(ApiResourceDeleted::class);
});

it('nested store respects dispatch_events=false', function () {
    Event::fake();
    config()->set('filament-api-forge.events.dispatch_events', false);

    $controller = makeNestedController([
        'relation_name'    => 'items',
        'allowed_methods'  => ['store'],
        'validation_rules' => ['name' => ['required', 'string']],
    ]);

    $request = Request::create('/api/v1/admin/parents/1/items', 'POST', ['name' => 'Silent item']);

    $controller->store($request, 'admin', 'parents', (string) $this->parent->id, 'items');

    expect($this->parent->items()->count())->toBe(1);

    Event::assertNotDispatched(ApiResourceCreating::class);
    Event::assertNotDispatched(ApiResourceCreated::class);
});

it('nested index supports allowed_sorts from relation config', function () {
    $this->parent->items()->create(['name' => 'B item', 'position' => 2]);
    $this->parent->items()->create(['name' => 'A item', 'position' => 1]);

    $controller = makeNestedController([
        'relation_name'   => 'items',
        'allowed_methods' => ['index'],
        'allowed_sorts'   => ['position'],
    ]);

    $request = Request::create('/api/v1/admin/parents/1/items', 'GET', ['sort' => '-position']);

    // Spatie QueryBuilder reads from the bound request
    app()->instance('request', $request);

    $response = $controller->index($request, 'admin', 'parents', (string) $this->parent->id, 'items');
    $data = $response->toResponse($request)->getData(true);

    expect($data['data'][0]['position'])->toBe(2)
        ->and($data['data'][1]['position'])->toBe(1);
});
