<?php

use YusufGenc34\FilamentApiForge\Events\ApiResourceCreated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdated;
use YusufGenc34\FilamentApiForge\Jobs\SendApiForgeWebhook;
use YusufGenc34\FilamentApiForge\Models\ApiForgeWebhook;
use YusufGenc34\FilamentApiForge\Support\ResponseCacheManager;
use YusufGenc34\FilamentApiForge\Tests\Stubs\TestModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::hasTable('test_models')) {
        Schema::create('test_models', function ($table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    TestModel::query()->delete();
    ApiForgeWebhook::query()->delete();
    cache()->flush();
});

// ── Response cache ──────────────────────────────────────────────────────────

it('response cache stores and returns payloads until the resource version bumps', function () {
    config()->set('filament-api-forge.cache.enabled', true);

    $key = ResponseCacheManager::key('App\\R', 'http://x/api/posts', 'token-1');

    ResponseCacheManager::put($key, ['content' => '{"a":1}', 'status' => 200]);

    expect(ResponseCacheManager::get($key))->toBe(['content' => '{"a":1}', 'status' => 200]);

    // A write bumps the version → same inputs now produce a different key
    ResponseCacheManager::bump('App\\R');

    $newKey = ResponseCacheManager::key('App\\R', 'http://x/api/posts', 'token-1');

    expect($newKey)->not->toBe($key)
        ->and(ResponseCacheManager::get($newKey))->toBeNull();
});

it('cache keys differ per token and per url', function () {
    config()->set('filament-api-forge.cache.enabled', true);

    $a = ResponseCacheManager::key('App\\R', 'http://x/api/posts', 'token-1');
    $b = ResponseCacheManager::key('App\\R', 'http://x/api/posts', 'token-2');
    $c = ResponseCacheManager::key('App\\R', 'http://x/api/posts?page=2', 'token-1');

    expect($a)->not->toBe($b)
        ->and($a)->not->toBe($c);
});

it('write events invalidate the response cache via the subscriber', function () {
    config()->set('filament-api-forge.cache.enabled', true);

    $before = ResponseCacheManager::version('App\\PostResource');

    $record = TestModel::create(['title' => 'x']);
    ApiResourceUpdated::dispatch('App\\PostResource', $record, []);

    expect(ResponseCacheManager::version('App\\PostResource'))->toBeGreaterThan($before);
});

// ── Webhooks ────────────────────────────────────────────────────────────────

it('webhook listensTo matches events, wildcard and resource filters', function () {
    $hook = new ApiForgeWebhook([
        'events'         => ['created', 'deleted'],
        'resource_class' => 'App\\PostResource',
        'is_active'      => true,
    ]);

    expect($hook->listensTo('created', 'App\\PostResource'))->toBeTrue()
        ->and($hook->listensTo('updated', 'App\\PostResource'))->toBeFalse()
        ->and($hook->listensTo('created', 'App\\OtherResource'))->toBeFalse();

    $wildcard = new ApiForgeWebhook(['events' => ['*'], 'resource_class' => null, 'is_active' => true]);

    expect($wildcard->listensTo('anything', 'App\\AnyResource'))->toBeTrue();

    $inactive = new ApiForgeWebhook(['events' => ['*'], 'resource_class' => null, 'is_active' => false]);

    expect($inactive->listensTo('created', 'App\\PostResource'))->toBeFalse();
});

it('dispatches webhook jobs for matching write events', function () {
    Queue::fake();

    ApiForgeWebhook::create([
        'name'   => 'Created hook',
        'url'    => 'https://example.com/hook',
        'events' => ['created'],
    ]);

    ApiForgeWebhook::create([
        'name'           => 'Other resource only',
        'url'            => 'https://example.com/other',
        'events'         => ['created'],
        'resource_class' => 'App\\OtherResource',
    ]);

    $record = TestModel::create(['title' => 'Webhook target']);

    ApiResourceCreated::dispatch('App\\PostResource', $record, ['title' => 'Webhook target']);

    Queue::assertPushed(SendApiForgeWebhook::class, 1);
    Queue::assertPushed(SendApiForgeWebhook::class, function (SendApiForgeWebhook $job) use ($record) {
        return $job->payload['event'] === 'created'
            && $job->payload['record']['id'] === $record->id;
    });
});

it('does not dispatch webhooks when disabled', function () {
    Queue::fake();
    config()->set('filament-api-forge.webhooks.enabled', false);

    ApiForgeWebhook::create([
        'name' => 'Any', 'url' => 'https://example.com/hook', 'events' => ['*'],
    ]);

    $record = TestModel::create(['title' => 'Silent']);
    ApiResourceCreated::dispatch('App\\PostResource', $record, []);

    Queue::assertNothingPushed();
});

it('webhook job posts a signed payload', function () {
    Http::fake(['example.com/*' => Http::response('ok', 200)]);

    $webhook = ApiForgeWebhook::create([
        'name'   => 'Signed',
        'url'    => 'https://example.com/hook',
        'secret' => 'shh',
        'events' => ['*'],
    ]);

    $payload = ['event' => 'created', 'record' => ['id' => 1]];

    (new SendApiForgeWebhook($webhook->id, $payload))->handle();

    Http::assertSent(function ($request) use ($payload) {
        return $request->url() === 'https://example.com/hook'
            && $request->header('X-ApiForge-Event')[0] === 'created'
            && $request->header('X-ApiForge-Signature')[0] === SendApiForgeWebhook::sign(json_encode($payload), 'shh');
    });

    expect($webhook->fresh()->last_triggered_at)->not->toBeNull()
        ->and($webhook->fresh()->failure_count)->toBe(0);
});

it('webhook job increments failure_count on failed responses', function () {
    Http::fake(['example.com/*' => Http::response('nope', 500)]);

    $webhook = ApiForgeWebhook::create([
        'name'   => 'Failing',
        'url'    => 'https://example.com/hook',
        'events' => ['*'],
    ]);

    $job = Mockery::mock(SendApiForgeWebhook::class, [$webhook->id, ['event' => 'created']])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('release')->once();
    $job->shouldReceive('attempts')->andReturn(1);

    $job->handle();

    expect($webhook->fresh()->failure_count)->toBe(1);
});
