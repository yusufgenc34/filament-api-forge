<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;

class CustomPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'custom_title' => strtoupper($this->title),
            'formatted_by' => 'CustomPostResource',
        ];
    }
}

class TestCustomPostModel extends Model
{
    protected $table = 'test_custom_posts';

    protected $fillable = ['title'];
}

it('uses custom api_resource when declared in apiConfig', function () {
    $post = new TestCustomPostModel(['title' => 'hello world']);
    $post->id = 42;

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\TestCustomPostResource',
        'model_class' => TestCustomPostModel::class,
        'slug' => 'custom-posts',
        'plural_label' => 'Custom Posts',
        'api_config' => [
            'allowed_methods' => ['show'],
            'api_resource' => CustomPostResource::class,
        ],
    ]);
    $mock->shouldReceive('isMethodAllowed')->andReturn(true);

    $controller = new ApiResourceController($mock);

    $ref = new ReflectionMethod($controller, 'makeJsonResponse');
    $result = $ref->invoke($controller, [
        'plural_label' => 'Custom Posts',
        'api_config' => [
            'api_resource' => CustomPostResource::class,
        ],
    ], $post);

    expect($result)->toBeInstanceOf(CustomPostResource::class);

    $response = $result->response(Request::create('/', 'GET'))->getData(true);
    expect($response['data']['custom_title'])->toBe('HELLO WORLD');
    expect($response['data']['formatted_by'])->toBe('CustomPostResource');
});
