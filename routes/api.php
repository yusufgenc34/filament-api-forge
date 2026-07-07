<?php

use YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiActionController;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiBatchController;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiNestedResourceController;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiDocumentationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Forge Routes
|--------------------------------------------------------------------------
|
| Route ordering is critical: more specific routes must be registered
| before less specific ones to prevent wildcard parameter capture.
| Action routes carry a literal prefix segment, so they must precede the
| nested wildcard routes — otherwise nested {childSlug}/{childId} patterns
| capture GET/PUT/PATCH/DELETE action requests.
|
| Order: batch → actions → nested → standard CRUD
|
*/

$actionsPrefix = config('filament-api-forge.actions.prefix', 'actions');

// Batch operations (3 segments, literal 'batch')
Route::post('{panelId}/{resourceSlug}/batch', [ApiBatchController::class, 'batch'])
    ->name('api-forge.batch');

// Collection-level custom actions (4 segments, literal prefix)
Route::match(
    ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    '{panelId}/{resourceSlug}/' . $actionsPrefix . '/{actionName}',
    [ApiActionController::class, 'executeCollection']
)->name('api-forge.action.collection');

// Record-level custom actions (5 segments, literal prefix)
Route::match(
    ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    '{panelId}/{resourceSlug}/{recordId}/' . $actionsPrefix . '/{actionName}',
    [ApiActionController::class, 'execute']
)->name('api-forge.action');

// Nested resource routes (4-5 segments)
Route::get('{panelId}/{resourceSlug}/{recordId}/{childSlug}', [ApiNestedResourceController::class, 'index'])
    ->name('api-forge.nested.index');
Route::post('{panelId}/{resourceSlug}/{recordId}/{childSlug}', [ApiNestedResourceController::class, 'store'])
    ->name('api-forge.nested.store');
Route::get('{panelId}/{resourceSlug}/{recordId}/{childSlug}/{childId}', [ApiNestedResourceController::class, 'show'])
    ->name('api-forge.nested.show');
Route::put('{panelId}/{resourceSlug}/{recordId}/{childSlug}/{childId}', [ApiNestedResourceController::class, 'update'])
    ->name('api-forge.nested.update');
Route::patch('{panelId}/{resourceSlug}/{recordId}/{childSlug}/{childId}', [ApiNestedResourceController::class, 'update'])
    ->name('api-forge.nested.update.patch');
Route::delete('{panelId}/{resourceSlug}/{recordId}/{childSlug}/{childId}', [ApiNestedResourceController::class, 'destroy'])
    ->name('api-forge.nested.destroy');

// Standard CRUD routes (2-3 segments)
Route::get('{panelId}/{resourceSlug}', [ApiResourceController::class, 'index'])
    ->name('api-forge.index');

Route::post('{panelId}/{resourceSlug}', [ApiResourceController::class, 'store'])
    ->name('api-forge.store');

Route::get('{panelId}/{resourceSlug}/{recordId}', [ApiResourceController::class, 'show'])
    ->name('api-forge.show');

Route::put('{panelId}/{resourceSlug}/{recordId}', [ApiResourceController::class, 'update'])
    ->name('api-forge.update');

Route::patch('{panelId}/{resourceSlug}/{recordId}', [ApiResourceController::class, 'update'])
    ->name('api-forge.update.patch');

Route::delete('{panelId}/{resourceSlug}/{recordId}', [ApiResourceController::class, 'destroy'])
    ->name('api-forge.destroy');
