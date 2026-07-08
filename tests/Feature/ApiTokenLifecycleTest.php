<?php

use YusufGenc34\FilamentApiForge\Http\Controllers\ApiTokenController;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Notifications\ApiForgeTokenExpiringNotification;
use YusufGenc34\FilamentApiForge\Services\ApiForgeTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class LifecycleUser extends \Illuminate\Foundation\Auth\User
{
    use \Illuminate\Notifications\Notifiable;

    protected $table = 'users';
}

beforeEach(function () {
    // Other test files create tokens too — isolate the expiry-window scans
    ApiForgeToken::query()->delete();

    // Token->user relation resolves through this model (needs Notifiable)
    config()->set('auth.providers.users.model', LifecycleUser::class);

    $this->user = LifecycleUser::create([
        'name'     => 'Lifecycle User',
        'email'    => 'lifecycle-' . uniqid() . '@example.com',
        'password' => bcrypt('secret'),
    ]);

    $this->service = app(ApiForgeTokenService::class);
});

// ── Refresh tokens ──────────────────────────────────────────────────────────

it('issues a refresh token at creation when enabled', function () {
    config()->set('filament-api-forge.auth.refresh_tokens', true);

    $result = $this->service->create($this->user, ['name' => 'With refresh']);

    expect($result['plain_refresh_token'])->toStartWith('forge_refresh_')
        ->and($result['record']->refresh_token_hash)->toBe(hash('sha256', $result['plain_refresh_token']));
});

it('does not issue a refresh token by default', function () {
    $result = $this->service->create($this->user, ['name' => 'No refresh']);

    expect($result['plain_refresh_token'])->toBeNull()
        ->and($result['record']->refresh_token_hash)->toBeNull();
});

it('exchanges a refresh token for new access + refresh tokens', function () {
    config()->set('filament-api-forge.auth.refresh_tokens', true);

    $created  = $this->service->create($this->user, [
        'name'       => 'Refreshable',
        'expires_at' => now()->subDay(), // access token already expired
    ]);

    $oldHash = $created['record']->token_hash;

    $result = $this->service->refresh($created['plain_refresh_token']);

    expect($result)->not->toBeNull()
        ->and($result['plain_text_token'])->toStartWith('forge_')
        ->and($result['plain_refresh_token'])->toStartWith('forge_refresh_')
        ->and($result['record']->token_hash)->not->toBe($oldHash)
        ->and($result['record']->expires_at->isFuture())->toBeTrue();

    // Old refresh token can no longer be used (rotated)
    expect($this->service->refresh($created['plain_refresh_token']))->toBeNull();
});

it('rejects invalid refresh tokens', function () {
    expect($this->service->refresh('forge_refresh_invalid'))->toBeNull()
        ->and($this->service->refresh('not-a-refresh-token'))->toBeNull();
});

it('refresh endpoint returns 401 for an invalid token', function () {
    $controller = new ApiTokenController($this->service);

    $request = Request::create('/api/v1/auth/token/refresh', 'POST', [
        'refresh_token' => 'forge_refresh_bogus',
    ]);

    $response = $controller->refresh($request);

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true)['error'])->toBe('invalid_refresh_token');
});

// ── Rotation ────────────────────────────────────────────────────────────────

it('rotates a token in place, invalidating the old access token', function () {
    $created = $this->service->create($this->user, ['name' => 'Rotatable']);
    $oldPlain = $created['plain_text_token'];

    $result = $this->service->rotate($created['record']);

    expect($result['plain_text_token'])->not->toBe($oldPlain)
        ->and(ApiForgeToken::findByToken($oldPlain))->toBeNull()
        ->and(ApiForgeToken::findByToken($result['plain_text_token'])->id)->toBe($created['record']->id);
});

it('rotate endpoint returns the new token for the authenticated token', function () {
    $created = $this->service->create($this->user, ['name' => 'Endpoint rotate']);

    $controller = new ApiTokenController($this->service);
    $request = Request::create('/api/v1/auth/token/rotate', 'POST');
    $request->attributes->set('api_forge_token', $created['record']);

    $response = $controller->rotate($request);
    $data = $response->getData(true);

    expect($response->getStatusCode())->toBe(200)
        ->and($data['token'])->toStartWith('forge_')
        ->and(ApiForgeToken::findByToken($data['token'])->id)->toBe($created['record']->id);
});

// ── Expiry notifications ────────────────────────────────────────────────────

it('notifies owners of tokens expiring within the window once', function () {
    Notification::fake();

    $expiring = $this->service->create($this->user, [
        'name'       => 'Expiring soon',
        'expires_at' => now()->addDays(3),
    ])['record'];

    $this->service->create($this->user, [
        'name'       => 'Far future',
        'expires_at' => now()->addDays(300),
    ]);

    $this->artisan('api-forge:notify-expiring', ['--days' => 7])
        ->expectsOutputToContain('Notified 1 token owner(s)')
        ->assertExitCode(0);

    Notification::assertSentTo($this->user, ApiForgeTokenExpiringNotification::class, function ($notification) use ($expiring) {
        return $notification->token->id === $expiring->id;
    });

    expect($expiring->fresh()->expiry_notified_at)->not->toBeNull();

    // Second run: already notified — no duplicate
    Notification::fake();
    $this->artisan('api-forge:notify-expiring', ['--days' => 7])
        ->expectsOutputToContain('Notified 0 token owner(s)');
    Notification::assertNothingSent();
});
