<?php

use YusufGenc34\FilamentApiForge\Attributes\ApiTag;
use YusufGenc34\FilamentApiForge\Attributes\ApiDescription;
use YusufGenc34\FilamentApiForge\Attributes\ApiOperations;
use YusufGenc34\FilamentApiForge\Attributes\ApiIgnore;

it('creates ApiTag with name only', function () {
    $tag = new ApiTag('Posts');

    expect($tag->name)->toBe('Posts')
        ->and($tag->description)->toBeNull();
});

it('creates ApiTag with name and description', function () {
    $tag = new ApiTag('Breaking News', 'Latest breaking news articles');

    expect($tag->name)->toBe('Breaking News')
        ->and($tag->description)->toBe('Latest breaking news articles');
});

it('creates ApiDescription', function () {
    $desc = new ApiDescription('Manage blog posts and articles.');

    expect($desc->description)->toBe('Manage blog posts and articles.');
});

it('creates ApiIgnore attribute', function () {
    $ignore = new ApiIgnore();

    expect($ignore)->toBeInstanceOf(ApiIgnore::class);
});

it('resolves ApiOperations simple string summaries', function () {
    $ops = new ApiOperations(
        index:   'List all posts',
        show:    'Get a single post',
        store:   'Create a post',
        update:  'Update a post',
    );

    expect($ops->getSummary('index'))->toBe('List all posts')
        ->and($ops->getSummary('show'))->toBe('Get a single post')
        ->and($ops->getSummary('store'))->toBe('Create a post')
        ->and($ops->getSummary('update'))->toBe('Update a post')
        ->and($ops->getSummary('destroy'))->toBeNull()
        ->and($ops->getDescription('index'))->toBeNull();
});

it('resolves ApiOperations array with summary and description', function () {
    $ops = new ApiOperations(
        store: ['summary' => 'Create post', 'description' => 'Requires write scope.'],
        destroy: ['summary' => 'Delete post', 'description' => 'Requires delete scope.'],
    );

    expect($ops->getSummary('store'))->toBe('Create post')
        ->and($ops->getDescription('store'))->toBe('Requires write scope.')
        ->and($ops->getSummary('destroy'))->toBe('Delete post')
        ->and($ops->getDescription('destroy'))->toBe('Requires delete scope.')
        ->and($ops->getSummary('index'))->toBeNull()
        ->and($ops->getDescription('index'))->toBeNull();
});

it('returns null for invalid operation names', function () {
    $ops = new ApiOperations();

    expect($ops->getSummary('nonexistent'))->toBeNull()
        ->and($ops->getDescription('nonexistent'))->toBeNull();
});
