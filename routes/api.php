<?php

use YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiDocumentationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Forge Routes
|--------------------------------------------------------------------------
|
| These routes are dynamically registered for every Filament resource
| that implements the HasApi interface. The pattern is:
|
|   {panel_id}/{resource_slug}          → index / store
|   {panel_id}/{resource_slug}/{id}     → show / update / destroy
|
*/

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

