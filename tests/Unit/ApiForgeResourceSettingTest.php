<?php

use YusufGenc34\FilamentApiForge\Models\ApiForgeResourceSetting;

it('creates a new resource setting with defaults', function () {
    $setting = ApiForgeResourceSetting::forResource('App\\Filament\\Resources\\PostResource');

    expect($setting->enabled)->toBeTrue()
        ->and($setting->allowed_ips)->toBe([])
        ->and($setting->disabled_methods)->toBe([])
        ->and($setting->method_settings)->toBe([])
        ->and($setting->resource_class)->toBe('App\\Filament\\Resources\\PostResource');
});

it('returns existing setting instead of creating duplicate', function () {
    $first  = ApiForgeResourceSetting::forResource('App\\Filament\\Resources\\PostResource');
    $second = ApiForgeResourceSetting::forResource('App\\Filament\\Resources\\PostResource');

    expect($first->id)->toBe($second->id);
});

it('toggles enabled status', function () {
    $setting = ApiForgeResourceSetting::forResource('App\\Filament\\Resources\\PostResource');

    expect($setting->enabled)->toBeTrue();

    $setting->toggleEnabled();
    $setting->refresh();

    expect($setting->enabled)->toBeFalse();

    $setting->toggleEnabled();
    $setting->refresh();

    expect($setting->enabled)->toBeTrue();
});

it('toggles a method as enabled/disabled', function () {
    $setting = ApiForgeResourceSetting::forResource('App\\Filament\\Resources\\PostResource');

    $result = $setting->toggleMethod('store');
    $setting->refresh();

    expect($result)->toBeFalse()
        ->and($setting->disabled_methods)->toContain('store');

    $result = $setting->toggleMethod('store');
    $setting->refresh();

    expect($result)->toBeTrue()
        ->and($setting->disabled_methods)->not->toContain('store');
});

it('checks if a method is disabled', function () {
    $setting = ApiForgeResourceSetting::forResource('App\\Filament\\Resources\\PostResource');

    $setting->toggleMethod('destroy');
    $setting->refresh();

    expect($setting->isMethodDisabled('destroy'))->toBeTrue()
        ->and($setting->isMethodDisabled('index'))->toBeFalse();
});

it('saves rate limit and allowed IPs', function () {
    $setting = ApiForgeResourceSetting::forResource('App\\Filament\\Resources\\PostResource');

    $setting->saveSettings(30, ['192.168.1.1', '10.0.0.0/8']);
    $setting->refresh();

    expect($setting->rate_limit)->toBe(30)
        ->and($setting->allowed_ips)->toBe(['192.168.1.1', '10.0.0.0/8']);
});

it('saves null rate limit as null', function () {
    $setting = ApiForgeResourceSetting::forResource('App\\Filament\\Resources\\PostResource');

    $setting->saveSettings(null, ['10.0.0.1']);
    $setting->refresh();

    expect($setting->rate_limit)->toBeNull();
});

it('gets method-level config with defaults', function () {
    $setting = ApiForgeResourceSetting::forResource('App\\Filament\\Resources\\PostResource');

    $config = $setting->getMethodConfig('store');

    expect($config)->toBe(['rate_limit' => null, 'allowed_ips' => []]);
});

it('saves and retrieves method-level config', function () {
    $setting = ApiForgeResourceSetting::forResource('App\\Filament\\Resources\\PostResource');

    $setting->saveMethodConfig('store', 10, ['10.0.0.5']);
    $setting->refresh();

    $config = $setting->getMethodConfig('store');

    expect($config)->toBe(['rate_limit' => 10, 'allowed_ips' => ['10.0.0.5']]);
});

it('preserves other method configs when saving one', function () {
    $setting = ApiForgeResourceSetting::forResource('App\\Filament\\Resources\\PostResource');

    $setting->saveMethodConfig('store', 10, ['10.0.0.5']);
    $setting->saveMethodConfig('update', 20, ['10.0.0.6']);
    $setting->refresh();

    expect($setting->getMethodConfig('store')['rate_limit'])->toBe(10)
        ->and($setting->getMethodConfig('update')['rate_limit'])->toBe(20);
});