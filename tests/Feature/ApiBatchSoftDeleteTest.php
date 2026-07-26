<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiBatchController;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;

class TestBatchSoftDeleteModel extends Model
{
    use SoftDeletes;

    protected $table = 'test_batch_soft_deletes';

    protected $fillable = ['title'];
}

it('supports restore and forceDelete operations in batch', function () {
    $request = Request::create('/api/v1/admin/posts/batch', 'POST', [
        'restore' => [1],
        'forceDelete' => [2],
    ]);

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\TestBatchSoftDeleteResource',
        'model_class' => TestBatchSoftDeleteModel::class,
        'slug' => 'soft-posts',
        'api_config' => [
            'batch' => [
                'allowed_operations' => ['restore', 'forceDelete'],
            ],
        ],
    ]);

    $controller = new ApiBatchController($mock);
    $response = $controller->batch($request, 'admin', 'soft-posts');

    expect($response->getStatusCode())->toBe(200);
    $data = $response->getData(true);

    expect($data)->toHaveKeys(['created', 'updated', 'deleted', 'restored', 'forceDeleted', 'failed']);
});
