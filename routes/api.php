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
|
| Order: batch → nested → actions → standard CRUD
|
*/

// Batch operations (3 segments, literal 'batch')
Route::post('{panelId}/{resourceSlug}/batch', [ApiBatchController::class, 'batch'])
    ->name('api-forge.batch');

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

// Custom action endpoints (5 segments, literal 'actions')
Route::match(
    ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    '{panelId}/{resourceSlug}/{recordId}/actions/{actionName}',
    [ApiActionController::class, 'execute']
)->name('api-forge.action');

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
