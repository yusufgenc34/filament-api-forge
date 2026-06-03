<?php

use YusufGenc34\FilamentApiForge\Http\Controllers\ApiNestedResourceController;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

// ── Test models ────────────────────────────────────────────────────────────

class ParentModel extends Model
{
    protected $table = 'parent_models';
    protected $fillable = ['title'];

    public function children()
    {
        return $this->hasMany(ChildModel::class, 'parent_id');
    }
}

class ChildModel extends Model
{
    protected $table = 'child_models';
    protected $fillable = ['parent_id', 'name', 'status'];
}

beforeEach(function () {
    Schema::create('parent_models', function ($table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    Schema::create('child_models', function ($table) {
        $table->id();
        $table->foreignId('parent_id')->constrained('parent_models')->cascadeOnDelete();
        $table->string('name');
        $table->string('status')->default('draft');
        $table->timestamps();
    });

    $this->parent = ParentModel::create(['title' => 'Parent']);
    $this->child1 = ChildModel::create(['parent_id' => $this->parent->id, 'name' => 'Child 1']);
    $this->child2 = ChildModel::create(['parent_id' => $this->parent->id, 'name' => 'Child 2']);
});

it('lists children via relation', function () {
    $children = $this->parent->children()->get();
    expect($children)->toHaveCount(2);
});

it('creates child scoped to parent', function () {
    $child = $this->parent->children()->create(['name' => 'New Child', 'status' => 'active']);

    expect($child->parent_id)->toBe($this->parent->id)
        ->and($child->name)->toBe('New Child');
});

it('finds child scoped to parent', function () {
    $child = $this->parent->children()->findOrFail($this->child1->id);

    expect($child->id)->toBe($this->child1->id);
});

it('child not found when scoped to wrong parent', function () {
    $otherParent = ParentModel::create(['title' => 'Other']);
    $otherChild = ChildModel::create(['parent_id' => $otherParent->id, 'name' => 'Other Child']);

    // Query child from wrong parent
    $found = $this->parent->children()->find($otherChild->id);

    expect($found)->toBeNull();
});

it('nested controller resolveParent finds parent record', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\ParentResource',
        'model_class'    => ParentModel::class,
        'slug'           => 'parents',
        'api_config'     => [
            'relations' => [
                'children' => ['allowed_methods' => ['index', 'show']],
            ],
        ],
    ]);

    $controller = new ApiNestedResourceController($mock);
    $ref = new ReflectionMethod($controller, 'resolveParent');
    $result = $ref->invoke($controller, 'admin', 'parents', (string) $this->parent->id);

    expect($result)->toBeArray()
        ->and($result)->toHaveKey('_record')
        ->and($result['_record']->id)->toBe($this->parent->id);
});

it('nested controller returns 404 for unknown relation', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\ParentResource',
        'model_class'    => ParentModel::class,
        'slug'           => 'parents',
        'api_config'     => [],
    ]);

    $controller = new ApiNestedResourceController($mock);
    $ref = new ReflectionMethod($controller, 'resolveRelation');

    $result = $ref->invoke($controller, ['api_config' => []], 'unknown', 'index');

    expect($result)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($result->getStatusCode())->toBe(404);
});

it('nested controller returns 405 for disabled method on relation', function () {
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\ParentResource',
        'model_class'    => ParentModel::class,
        'slug'           => 'parents',
        'api_config'     => [
            'relations' => [
                'children' => ['allowed_methods' => ['index']],
            ],
        ],
    ]);

    $controller = new ApiNestedResourceController($mock);

    // Resolve parent first
    $parentRef = new ReflectionMethod($controller, 'resolveParent');
    $parent = $parentRef->invoke($controller, 'admin', 'parents', (string) $this->parent->id);

    $relationRef = new ReflectionMethod($controller, 'resolveRelation');
    $result = $relationRef->invoke($controller, $parent, 'children', 'store');

    expect($result)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    expect($result->getStatusCode())->toBe(405);
});
