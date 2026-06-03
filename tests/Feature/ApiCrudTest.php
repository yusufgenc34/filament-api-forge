<?php

use YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController;
use YusufGenc34\FilamentApiForge\Http\Resources\ApiForgeJsonResource;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

beforeEach(function () {
    if (! Schema::hasTable('test_models')) {
        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }
});

// ── Test model for CRUD operations ─────────────────────────────────────────

$testModelClass = new class extends Model
{
    protected $table = 'test_models';
    protected $fillable = ['title', 'body', 'status'];
};

it('creates a record via Eloquent', function () use ($testModelClass) {
    $modelClass = get_class($testModelClass);

    $record = $modelClass::create([
        'title'  => 'Hello World',
        'body'   => 'Test body content',
        'status' => 'draft',
    ]);

    expect($record->id)->not->toBeNull()
        ->and($record->title)->toBe('Hello World')
        ->and($record->status)->toBe('draft');

    $found = $modelClass::find($record->id);
    expect($found)->not->toBeNull();
});

it('updates a record via Eloquent', function () use ($testModelClass) {
    $modelClass = get_class($testModelClass);

    $record = $modelClass::create([
        'title'  => 'Original Title',
        'body'   => 'Original body',
        'status' => 'draft',
    ]);

    $record->update(['title' => 'Updated Title', 'status' => 'published']);
    $record->refresh();

    expect($record->title)->toBe('Updated Title')
        ->and($record->status)->toBe('published');
});

it('deletes a record via Eloquent', function () use ($testModelClass) {
    $modelClass = get_class($testModelClass);

    $record = $modelClass::create([
        'title'  => 'To Delete',
        'body'   => 'Will be deleted',
        'status' => 'draft',
    ]);

    $id = $record->id;
    $record->delete();

    expect($modelClass::find($id))->toBeNull();
});

// ── ApiForgeJsonResource tests ─────────────────────────────────────────────

it('ApiForgeJsonResource wraps single model with meta', function () use ($testModelClass) {
    $modelClass = get_class($testModelClass);

    $model = new $modelClass();
    $model->fill(['title' => 'Test', 'body' => 'Content', 'status' => 'published']);

    $resource = new ApiForgeJsonResource($model);
    $response = $resource->response()->getData(true);

    expect($response)->toHaveKey('data')
        ->and($response)->toHaveKey('meta')
        ->and($response['meta'])->toHaveKey('api_version')
        ->and($response['meta'])->toHaveKey('timestamp');
});

it('ApiForgeJsonResource collection wraps paginated results', function () use ($testModelClass) {
    $modelClass = get_class($testModelClass);

    $modelClass::create(['title' => 'Post 1', 'body' => 'Body 1', 'status' => 'draft']);
    $modelClass::create(['title' => 'Post 2', 'body' => 'Body 2', 'status' => 'published']);

    $models = $modelClass::paginate(15);

    $collection = ApiForgeJsonResource::collection($models)->additional([
        'meta' => ['api_version' => 'v1', 'resource' => 'TestModels'],
    ]);

    $response = $collection->response()->getData(true);

    expect($response)->toHaveKey('data')
        ->and($response)->toHaveKey('links')
        ->and($response)->toHaveKey('meta')
        ->and(count($response['data']))->toBe(2);
});

it('ApiForgeJsonResource includes loaded relations', function () use ($testModelClass) {
    $modelClass = get_class($testModelClass);

    $parent = new $modelClass();
    $parent->fill(['title' => 'With Relations']);

    $child = new $modelClass();
    $child->fill(['title' => 'Child']);
    $parent->setRelation('children', collect([$child]));

    $resource = new ApiForgeJsonResource($parent);
    $data = $resource->response()->getData(true);

    expect($data['data'])->toHaveKey('_loaded_relations');
    expect($data['data']['_loaded_relations'])->toContain('children');
});

// ── Controller scope map ───────────────────────────────────────────────────

it('controller scope map maps HTTP methods correctly', function () {
    $ref = new ReflectionClass(ApiResourceController::class);
    $scopeMap = $ref->getConstant('SCOPE_MAP');

    expect($scopeMap)->toBe([
        'index'   => 'read',
        'show'    => 'read',
        'store'   => 'write',
        'update'  => 'write',
        'destroy' => 'delete',
    ]);
});
