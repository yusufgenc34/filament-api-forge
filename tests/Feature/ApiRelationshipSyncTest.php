<?php

use Illuminate\Database\Eloquent\Model;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;

class TestSyncRelation
{
    public array $synced = [];

    public function sync(array $ids)
    {
        $this->synced = $ids;

        return ['attached' => $ids, 'detached' => [], 'updated' => []];
    }
}

class TestSyncPostModel extends Model
{
    protected $table = 'test_sync_posts';

    protected $fillable = ['title'];

    public ?TestSyncRelation $mockRelation = null;

    public function tags()
    {
        return $this->mockRelation ??= new TestSyncRelation;
    }
}

it('syncs relationships when array values match a relationship method', function () {
    $post = new TestSyncPostModel(['title' => 'Post with Tags']);
    $relation = $post->tags();

    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $controller = new ApiResourceController($mock);

    // Call syncRelationships
    $ref = new ReflectionMethod($controller, 'syncRelationships');
    $ref->invoke($controller, $post, ['sync_relations' => true], [
        'title' => 'Post with Tags',
        'tags' => [10, 20, 30],
    ]);

    expect($relation->synced)->toBe([10, 20, 30]);
});
