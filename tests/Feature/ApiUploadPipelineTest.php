<?php

use YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController;
use YusufGenc34\FilamentApiForge\Http\Resources\ApiForgeJsonResource;
use YusufGenc34\FilamentApiForge\Services\FileUploadService;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

// ── A model that represents a Filament Resource's backing model ────────────

class UploadDemoModel extends Model
{
    protected $table = 'upload_demo';
    protected $fillable = ['title', 'body', 'avatar', 'gallery'];
    protected $casts = ['gallery' => 'array'];
}

beforeEach(function () {
    Storage::fake('public');

    Schema::create('upload_demo', function ($table) {
        $table->id();
        $table->string('title');
        $table->text('body')->nullable();
        $table->string('avatar')->nullable();
        $table->json('gallery')->nullable();
        $table->timestamps();
    });

    $this->user = User::create([
        'name'     => 'Demo User',
        'email'    => 'demo@example.com',
        'password' => bcrypt('password'),
    ]);

    // Valid token with write scope
    $plain = 'forge_' . str_repeat('z', 40);
    $this->token = ApiForgeToken::create([
        'user_id'      => $this->user->id,
        'name'         => 'Upload Token',
        'token_hash'   => hash('sha256', $plain),
        'token_prefix' => substr($plain, 0, 16),
        'scopes'       => ['read', 'write'],
        'is_active'    => true,
    ]);
    $this->plainToken = $plain;
});

// ── Helper: Build a mock resource config (simulating what discovery produces) ─

function mockResourceConfig(string $modelClass, array $overrides = []): array
{
    return array_merge([
        'resource_class' => 'App\\Filament\\Resources\\UploadDemoResource',
        'model_class'    => $modelClass,
        'slug'           => 'upload-demos',
        'panel_id'       => 'admin',
        'label'          => 'Upload Demo',
        'plural_label'   => 'Upload Demos',
        'api_config'     => [
            'allowed_methods' => ['index', 'show', 'store', 'update', 'destroy'],
            'scopes'          => ['read', 'write', 'delete'],
            'allowed_fields'  => ['id', 'title', 'body', 'avatar', 'gallery'],
            // ★ This is what the Filament Resource defines in apiConfig()
            'uploads' => [
                'avatar' => [
                    'disk'       => 'public',
                    'rules'      => 'image|max:2048',
                ],
                'gallery' => [
                    'disk'       => 'public',
                    'rules'      => 'image|max:5120',
                    'multiple'   => true,
                ],
            ],
        ],
    ], $overrides);
}

// ═══════════════════════════════════════════════════════════════════════════
// TEST 1: Full pipeline — Store with single file upload
// ═══════════════════════════════════════════════════════════════════════════

it('store: discovers upload config, validates file, saves via FileUploadService', function () {
    // 1. Simulate discovery returning a resource with upload config
    $mockDiscovery = Mockery::mock(ResourceDiscoveryService::class);
    $mockDiscovery->shouldReceive('findResource')
        ->with('admin', 'upload-demos')
        ->andReturn(mockResourceConfig(UploadDemoModel::class));

    $mockDiscovery->shouldReceive('isMethodAllowed')->andReturn(true);

    // 2. Build a multipart request (simulating POST /api/v1/admin/upload-demos)
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600);
    $request = Request::create('/api/v1/admin/upload-demos', 'POST', [
        'title' => 'My Photo Post',
        'body'  => 'Check out this photo',
    ], [], [
        'avatar' => $file,
    ]);
    $request->headers->set('Accept', 'application/json');
    $request->attributes->set('api_forge_token', $this->token);
    $this->app->instance('request', $request);

    // 3. Controller handles the request
    $controller = new ApiResourceController($mockDiscovery);
    $response = $controller->store($request, 'admin', 'upload-demos');

    // 4. Assertions
    expect($response)->toBeInstanceOf(ApiForgeJsonResource::class);

    $data = $response->response()->getData(true);

    // Model was created
    expect($data['data']['title'])->toBe('My Photo Post');

    // File was stored
    expect($data)->toHaveKey('_uploads');
    expect($data['_uploads'])->toHaveKey('avatar');
    expect($data['_uploads']['avatar']['url'])->toStartWith('/storage/avatar/');

    // File exists on disk
    Storage::disk('public')->assertExists(
        str_replace('/storage/', '', parse_url($data['_uploads']['avatar']['url'], PHP_URL_PATH))
    );

    // DB record was created
    expect(UploadDemoModel::count())->toBe(1);
    expect(UploadDemoModel::first()->title)->toBe('My Photo Post');
});

// ═══════════════════════════════════════════════════════════════════════════
// TEST 2: Store with multiple file upload (gallery)
// ═══════════════════════════════════════════════════════════════════════════

it('store: handles multiple file uploads via gallery config', function () {
    $mockDiscovery = Mockery::mock(ResourceDiscoveryService::class);
    $mockDiscovery->shouldReceive('findResource')
        ->with('admin', 'upload-demos')
        ->andReturn(mockResourceConfig(UploadDemoModel::class));
    $mockDiscovery->shouldReceive('isMethodAllowed')->andReturn(true);

    $photo1 = UploadedFile::fake()->image('img1.jpg');
    $photo2 = UploadedFile::fake()->image('img2.jpg');

    $request = Request::create('/api/v1/admin/upload-demos', 'POST', [
        'title' => 'Gallery Post',
    ], [], [
        'gallery' => [$photo1, $photo2],
    ]);
    $request->headers->set('Accept', 'application/json');
    $request->attributes->set('api_forge_token', $this->token);
    $this->app->instance('request', $request);

    $controller = new ApiResourceController($mockDiscovery);
    $response = $controller->store($request, 'admin', 'upload-demos');

    $data = $response->response()->getData(true);

    expect($data)->toHaveKey('_uploads');
    expect($data['_uploads'])->toHaveKey('gallery');
    // Multiple files → 'urls' key (not 'url')
    expect($data['_uploads']['gallery'])->toHaveKey('urls');
    expect(count($data['_uploads']['gallery']['urls']))->toBe(2);
});

// ═══════════════════════════════════════════════════════════════════════════
// TEST 3: Store without any upload config (backward compatible)
// ═══════════════════════════════════════════════════════════════════════════

it('store: works normally when resource has no upload config', function () {
    $mockDiscovery = Mockery::mock(ResourceDiscoveryService::class);
    $mockDiscovery->shouldReceive('findResource')
        ->with('admin', 'upload-demos')
        ->andReturn(mockResourceConfig(UploadDemoModel::class, [
            'api_config' => [
                'allowed_methods' => ['index', 'show', 'store'],
                'scopes'          => ['read', 'write'],
                'allowed_fields'  => ['id', 'title', 'body'],
                // ★ NO uploads key
            ],
        ]));
    $mockDiscovery->shouldReceive('isMethodAllowed')->andReturn(true);

    $request = Request::create('/api/v1/admin/upload-demos', 'POST', [
        'title' => 'No Upload',
    ]);
    $request->headers->set('Accept', 'application/json');
    $request->attributes->set('api_forge_token', $this->token);
    $this->app->instance('request', $request);

    $controller = new ApiResourceController($mockDiscovery);
    $response = $controller->store($request, 'admin', 'upload-demos');

    $data = $response->response()->getData(true);

    expect($data['data']['title'])->toBe('No Upload');
    // No _uploads key when no files were uploaded (even if config exists)
    expect($data)->not->toHaveKey('_uploads');
    expect(UploadDemoModel::count())->toBe(1);
});

// ═══════════════════════════════════════════════════════════════════════════
// TEST 4: Update with file replacement
// ═══════════════════════════════════════════════════════════════════════════

it('update: handles file upload on existing record', function () {
    $existing = UploadDemoModel::create([
        'title'  => 'Old Title',
        'body'   => 'Old body',
        'avatar' => 'avatars/old.jpg',
    ]);

    $mockDiscovery = Mockery::mock(ResourceDiscoveryService::class);
    $mockDiscovery->shouldReceive('findResource')
        ->with('admin', 'upload-demos')
        ->andReturn(mockResourceConfig(UploadDemoModel::class));
    $mockDiscovery->shouldReceive('isMethodAllowed')->andReturn(true);

    $newFile = UploadedFile::fake()->image('new-avatar.jpg');

    $request = Request::create(
        "/api/v1/admin/upload-demos/{$existing->id}",
        'PUT',
        ['title' => 'Updated Title'],
        [],
        ['avatar' => $newFile]
    );
    $request->headers->set('Accept', 'application/json');
    $request->attributes->set('api_forge_token', $this->token);
    $this->app->instance('request', $request);

    $controller = new ApiResourceController($mockDiscovery);
    $response = $controller->update($request, 'admin', 'upload-demos', (string) $existing->id);

    $data = $response->response()->getData(true);

    expect($data['data']['title'])->toBe('Updated Title');
    expect($data)->toHaveKey('_uploads');
    expect($data['_uploads']['avatar']['url'])->toStartWith('/storage/avatar/');

    $existing->refresh();
});

// ═══════════════════════════════════════════════════════════════════════════
// TEST 5: Validation rejects invalid file type
// ═══════════════════════════════════════════════════════════════════════════

it('store: rejects non-image file when rules require image', function () {
    $mockDiscovery = Mockery::mock(ResourceDiscoveryService::class);
    $mockDiscovery->shouldReceive('findResource')
        ->with('admin', 'upload-demos')
        ->andReturn(mockResourceConfig(UploadDemoModel::class));
    $mockDiscovery->shouldReceive('isMethodAllowed')->andReturn(true);

    $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $request = Request::create('/api/v1/admin/upload-demos', 'POST', [
        'title' => 'Should Fail',
    ], [], [
        'avatar' => $pdf,
    ]);
    $request->headers->set('Accept', 'application/json');
    $request->attributes->set('api_forge_token', $this->token);
    $this->app->instance('request', $request);

    $controller = new ApiResourceController($mockDiscovery);

    try {
        $controller->store($request, 'admin', 'upload-demos');
        $this->fail('Expected validation exception was not thrown');
    } catch (\Illuminate\Validation\ValidationException $e) {
        expect($e->errors())->toHaveKey('avatar');
    }

    // Nothing was created
    expect(UploadDemoModel::count())->toBe(0);
});

// ═══════════════════════════════════════════════════════════════════════════
// TEST 6: Discovery pipeline visualization
// ═══════════════════════════════════════════════════════════════════════════

it('demonstrates the discovery → controller pipeline', function () {
    // STEP 1: Discovery produces this config from a Filament Resource's apiConfig()
    $discovered = mockResourceConfig(UploadDemoModel::class);

    expect($discovered['api_config'])->toHaveKey('uploads');
    expect($discovered['api_config']['uploads'])->toHaveKeys(['avatar', 'gallery']);

    // STEP 2: Controller reads the config
    $controller = new ApiResourceController(
        Mockery::mock(ResourceDiscoveryService::class)
    );

    // mergeUploadRules extracts validation rules from upload config
    $ref = new ReflectionMethod($controller, 'mergeUploadRules');
    $baseRules = ['title' => ['required', 'string']];
    $merged = $ref->invoke($controller, $baseRules, $discovered['api_config']);

    // File rules are merged
    expect($merged)->toHaveKey('avatar');
    expect($merged)->toHaveKey('gallery');
    expect($merged)->toHaveKey('title'); // original rules preserved
    // Gallery is multiple → gets array and wildcard rules
    expect($merged)->toHaveKey('gallery.*');

    // STEP 3: processFileUploads is called after save
    $processRef = new ReflectionMethod($controller, 'processFileUploads');
    $record = UploadDemoModel::create(['title' => 'Test']);
    $file = UploadedFile::fake()->image('photo.jpg');
    $request = Request::create('/', 'POST', [], [], ['avatar' => $file]);
    $request->headers->set('Accept', 'application/json');

    $results = $processRef->invoke($controller, $record, $discovered['api_config'], $request);

    // STEP 4: Upload results are structured with a URL
    expect($results)->toHaveKey('avatar');
    expect($results['avatar'])->toHaveKey('url');
    expect($results['avatar']['url'])->toStartWith('/storage/avatar/');

    Storage::disk('public')->assertExists(
        str_replace('/storage/', '', parse_url($results['avatar']['url'], PHP_URL_PATH))
    );
});
