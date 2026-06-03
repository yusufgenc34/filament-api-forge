<?php

use YusufGenc34\FilamentApiForge\Models\ApiForgeGlobalSetting;

it('returns default when key does not exist', function () {
    expect(ApiForgeGlobalSetting::get('nonexistent', 'fallback'))->toBe('fallback');
});

it('stores and retrieves a string value', function () {
    ApiForgeGlobalSetting::set('my_key', 'hello');

    expect(ApiForgeGlobalSetting::get('my_key'))->toBe('hello');
});

it('casts boolean true', function () {
    ApiForgeGlobalSetting::set('bool_true', true);

    expect(ApiForgeGlobalSetting::get('bool_true'))->toBeTrue();
});

it('casts boolean false', function () {
    ApiForgeGlobalSetting::set('bool_false', false);

    expect(ApiForgeGlobalSetting::get('bool_false'))->toBeFalse();
});

it('casts null', function () {
    ApiForgeGlobalSetting::set('null_key', null);

    expect(ApiForgeGlobalSetting::get('null_key'))->toBeNull();
});

it('updates existing key instead of creating duplicate', function () {
    ApiForgeGlobalSetting::set('updatable', 'first');
    ApiForgeGlobalSetting::set('updatable', 'second');

    expect(ApiForgeGlobalSetting::get('updatable'))->toBe('second')
        ->and(ApiForgeGlobalSetting::where('key', 'updatable')->count())->toBe(1);
});

it('stores docs_public setting', function () {
    ApiForgeGlobalSetting::set('docs_public', true);

    expect(ApiForgeGlobalSetting::get('docs_public'))->toBeTrue();

    ApiForgeGlobalSetting::set('docs_public', false);

    expect(ApiForgeGlobalSetting::get('docs_public'))->toBeFalse();
});

it('stores route_segment setting', function () {
    ApiForgeGlobalSetting::set('route_segment', 'filament');

    expect(ApiForgeGlobalSetting::get('route_segment'))->toBe('filament');
});