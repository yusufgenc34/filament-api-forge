<?php

use Illuminate\Support\Facades\RateLimiter;

it('rate limiter allows requests under the limit', function () {
    $key = 'api-forge-dynamic|TestResource|index|1';

    RateLimiter::clear($key);

    expect(RateLimiter::tooManyAttempts($key, 60))->toBeFalse();
    expect(RateLimiter::remaining($key, 60))->toBe(60);
});

it('rate limiter blocks after exceeding limit', function () {
    $key = 'api-forge-dynamic|TestResource|index|2';

    RateLimiter::clear($key);

    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit($key, 60);
    }

    expect(RateLimiter::tooManyAttempts($key, 5))->toBeTrue();
    expect(RateLimiter::remaining($key, 5))->toBe(0);
});

it('rate limiter key includes resource class, action, and identifier', function () {
    $key = implode('|', [
        'api-forge-dynamic',
        'App\\Filament\\Resources\\PostResource',
        'index',
        'user-123',
    ]);

    RateLimiter::clear($key);

    expect(RateLimiter::tooManyAttempts($key, 60))->toBeFalse();
    RateLimiter::hit($key, 60);
    expect(RateLimiter::tooManyAttempts($key, 1))->toBeTrue();
});

it('rate limiter resets after decay period', function () {
    $key = 'api-forge-dynamic|TestResource|index|timed';

    RateLimiter::clear($key);
    RateLimiter::hit($key, 60);

    expect(RateLimiter::tooManyAttempts($key, 1))->toBeTrue();

    // Fast-forward past the 60-second window
    $this->travel(61)->seconds();

    expect(RateLimiter::tooManyAttempts($key, 1))->toBeFalse();
});

it('rate limit config value can be customized', function () {
    config()->set('filament-api-forge.rate_limit', 120);
    expect(config('filament-api-forge.rate_limit'))->toBe(120);
});

it('availableIn returns seconds until reset', function () {
    $key = 'api-forge-dynamic|TestResource|index|retry';

    RateLimiter::clear($key);
    RateLimiter::hit($key, 60);

    $seconds = RateLimiter::availableIn($key);
    expect($seconds)->toBeGreaterThan(0);
    expect($seconds)->toBeLessThanOrEqual(60);
});
