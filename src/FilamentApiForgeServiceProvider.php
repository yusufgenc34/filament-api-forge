<?php

namespace YusufGenc34\FilamentApiForge;

use YusufGenc34\FilamentApiForge\Contracts\FilamentSchemaAdapter;
use YusufGenc34\FilamentApiForge\Filament\V5\SchemaAdapter;
use YusufGenc34\FilamentApiForge\Http\Middleware\ApiForgeAuthenticate;
use YusufGenc34\FilamentApiForge\Http\Middleware\CheckResourceEnabled;
use YusufGenc34\FilamentApiForge\Http\Middleware\EnforceApiForgeRules;
use YusufGenc34\FilamentApiForge\Http\Middleware\ForceJsonResponse;
use YusufGenc34\FilamentApiForge\Services\ApiForgeTokenService;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentApiForgeServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-api-forge';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasMigrations([
                'create_api_forge_tokens_table',
                'add_allowed_resources_to_api_forge_tokens_table',
                'create_api_forge_resource_settings_table',
                'add_method_settings_to_api_forge_resource_settings_table',
                'refactor_api_forge_tokens_to_hash_based',
                'create_api_forge_global_settings_table',
            ])
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ResourceDiscoveryService::class);
        $this->app->singleton(ApiForgeTokenService::class);

        $this->app->singleton(FilamentSchemaAdapter::class, SchemaAdapter::class);
    }

    public function packageBooted(): void
    {
        $this->registerApiRoutes();
    }

    protected function registerApiRoutes(): void
    {
        $prefix = config('filament-api-forge.api_prefix', 'api/v1');

        // Public routes: OpenAPI spec + public docs HTML (no auth)
        Route::prefix($prefix)
            ->middleware(config('filament-api-forge.discovery.middleware', ['api']))
            ->group(function () {
                Route::get('docs/openapi.json', [\YusufGenc34\FilamentApiForge\Http\Controllers\ApiDocumentationController::class, 'openApiSpec'])
                    ->name('api-forge.docs.openapi');

                Route::get('docs', [\YusufGenc34\FilamentApiForge\Http\Controllers\ApiDocumentationController::class, 'publicDocs'])
                    ->name('api-forge.docs.public');
            });

        // Protected API routes
        $middleware = array_merge(
            [ForceJsonResponse::class, CheckResourceEnabled::class],
            config('filament-api-forge.discovery.middleware', ['api']),
        );

        if (config('filament-api-forge.auth.enabled', true)) {
            $middleware[] = ApiForgeAuthenticate::class;
        }

        // EnforceApiForgeRules runs after auth so $request->user() is available
        $middleware[] = EnforceApiForgeRules::class;

        Route::prefix($prefix)
            ->middleware($middleware)
            ->group(function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
            });
    }
}
