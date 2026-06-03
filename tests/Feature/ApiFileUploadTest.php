<?php

use YusufGenc34\FilamentApiForge\Services\FileUploadService;
use YusufGenc34\FilamentApiForge\Http\Resources\ApiForgeJsonResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    if (! Schema::hasTable('test_uploads')) {
        Schema::create('test_uploads', function ($table) {
            $table->id();
            $table->string('title');
            $table->string('avatar')->nullable();
            $table->string('gallery')->nullable();
            $table->timestamps();
        });
    }
});

it('FileUploadService detects Spatie Media Library availability', function () {
    $service = new FileUploadService();

    // In test env without Spatie, it should return false
    expect($service->isSpatieMediaLibraryAvailable())->toBeFalse();
});

it('FileUploadService generates validation rules for single file', function () {
    $service = new FileUploadService();

    $config = [
        'avatar' => ['rules' => 'image|max:2048'],
    ];

    $rules = $service->getValidationRules($config);

    expect($rules)->toHaveKey('avatar')
        ->and($rules['avatar'])->toContain('file', 'image');
});

it('FileUploadService generates validation rules for multiple files', function () {
    $service = new FileUploadService();

    $config = [
        'gallery' => ['multiple' => true, 'rules' => 'image|max:5120'],
    ];

    $rules = $service->getValidationRules($config);

    expect($rules)->toHaveKey('gallery')
        ->and($rules)->toHaveKey('gallery.*')
        ->and($rules['gallery'])->toContain('array');
});

it('FileUploadService handles upload with Laravel filesystem fallback', function () {
    $service = new FileUploadService();

    $file = UploadedFile::fake()->image('avatar.jpg');

    $record = new class extends Model {
        protected $table = 'test_uploads';
        protected $fillable = ['title', 'avatar'];
    };

    $record->fill(['title' => 'Test']);
    $record->save();

    $request = \Illuminate\Http\Request::create('/', 'POST', [], [], [
        'avatar' => $file,
    ]);

    $config = ['avatar' => ['disk' => 'public']];
    $results = $service->handleUploads($record, $config, $request);

    expect($results)->toHaveKey('avatar')
        ->and($results['avatar'])->toHaveKey('url');

    Storage::disk('public')->assertExists('avatar/' . $file->hashName());
});

it('FileUploadService handles multiple file uploads', function () {
    $service = new FileUploadService();

    $file1 = UploadedFile::fake()->image('photo1.jpg');
    $file2 = UploadedFile::fake()->image('photo2.jpg');

    $record = new class extends Model {
        protected $table = 'test_uploads';
        protected $fillable = ['title', 'avatar', 'gallery'];
    };

    $record->fill(['title' => 'Gallery Test']);
    $record->save();

    $request = \Illuminate\Http\Request::create('/', 'POST', [], [], [
        'gallery' => [$file1, $file2],
    ]);

    $config = ['gallery' => ['disk' => 'public', 'multiple' => true]];
    $results = $service->handleUploads($record, $config, $request);

    expect($results)->toHaveKey('gallery')
        ->and($results['gallery'])->toHaveKey('urls')
        ->and(count($results['gallery']['urls']))->toBe(2);
});

it('FileUploadService returns empty results when no files in request', function () {
    $service = new FileUploadService();

    $record = new class extends Model {
        protected $table = 'test_uploads';
        protected $fillable = ['title', 'avatar'];
    };

    $record->fill(['title' => 'No Files']);
    $record->save();

    $request = \Illuminate\Http\Request::create('/', 'POST');

    $config = ['avatar' => ['disk' => 'public']];
    $results = $service->handleUploads($record, $config, $request);

    expect($results)->toBe([]);
});

it('FileUploadService returns empty getFileUrls without Spatie', function () {
    $service = new FileUploadService();

    $record = new class extends Model {
        protected $table = 'test_uploads';
        protected $fillable = ['title', 'avatar'];
    };

    $urls = $service->getFileUrls($record, ['avatar' => ['collection' => 'avatars']]);

    expect($urls)->toBe([]);
});

it('ApiForgeJsonResource includes _uploads at response root via additional', function () {
    $record = new class extends Model {
        protected $table = 'test_uploads';
        protected $fillable = ['title', 'avatar'];
    };

    $record->fill(['title' => 'With Uploads']);

    $resource = (new ApiForgeJsonResource($record))
        ->additional(['_uploads' => ['avatar' => ['url' => 'https://example.com/avatar.jpg']]]);

    $data = $resource->response()->getData(true);

    // _uploads is at the response root level (alongside data), not inside data
    expect($data)->toHaveKey('_uploads')
        ->and($data['_uploads']['avatar']['url'])->toBe('https://example.com/avatar.jpg');
    expect($data)->toHaveKey('data');
    expect($data['data']['title'])->toBe('With Uploads');
});

it('mergeUploadRules overrides generic model rules with file-specific rules', function () {
    $controller = new \YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController(
        Mockery::mock(\YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService::class)
    );

    $ref = new ReflectionMethod($controller, 'mergeUploadRules');

    // Model fallback gives ['avatar' => ['nullable']], but upload config specifies file rules
    $existingRules = ['avatar' => ['nullable']];
    $apiConfig = ['uploads' => ['avatar' => ['rules' => 'image|max:2048']]];

    $merged = $ref->invoke($controller, $existingRules, $apiConfig);

    // File rules should replace the generic nullable rule
    expect($merged['avatar'])->toContain('file', 'image');
});
