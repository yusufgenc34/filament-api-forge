<?php

namespace YusufGenc34\FilamentApiForge;

use YusufGenc34\FilamentApiForge\Commands\NotifyExpiringTokensCommand;
use YusufGenc34\FilamentApiForge\Commands\PruneRequestLogsCommand;
use YusufGenc34\FilamentApiForge\Contracts\FilamentSchemaAdapter;
use YusufGenc34\FilamentApiForge\Filament\V5\SchemaAdapter;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiDocumentationController;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiTokenController;
use YusufGenc34\FilamentApiForge\Http\Controllers\GraphQlController;
use YusufGenc34\FilamentApiForge\Http\Middleware\ApiForgeAuthenticate;
use YusufGenc34\FilamentApiForge\Http\Middleware\CacheApiForgeResponse;
use YusufGenc34\FilamentApiForge\Http\Middleware\CheckResourceEnabled;
use YusufGenc34\FilamentApiForge\Http\Middleware\EnforceApiForgeRules;
use YusufGenc34\FilamentApiForge\Http\Middleware\ForceJsonResponse;
use YusufGenc34\FilamentApiForge\Http\Middleware\SetApiForgeVersion;
use YusufGenc34\FilamentApiForge\Listeners\ApiForgeEventSubscriber;
use YusufGenc34\FilamentApiForge\Services\ApiForgeTokenService;
use YusufGenc34\FilamentApiForge\Services\GraphQlSchemaService;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Support\Facades\Event;
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
                'add_v14_columns_to_api_forge_tokens_table',
                'create_api_forge_request_logs_table',
                'create_api_forge_webhooks_table',
            ])
            ->hasCommands([
                NotifyExpiringTokensCommand::class,
                PruneRequestLogsCommand::class,
            ])
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ResourceDiscoveryService::class);
        $this->app->singleton(ApiForgeTokenService::class);
        $this->app->singleton(GraphQlSchemaService::class);

        $this->app->singleton(FilamentSchemaAdapter::class, SchemaAdapter::class);
    }

    public function packageBooted(): void
    {
        Event::subscribe(ApiForgeEventSubscriber::class);

        $this->registerApiRoutes();
    }

    protected function registerApiRoutes(): void
    {
        $versions = config('filament-api-forge.versions');

        if (is_array($versions) && ! empty($versions)) {
            // Multi-version mode: {api_base}/{version}/... per configured version.
            // The first version keeps unprefixed route names for backwards compat.
            $base = rtrim(config('filament-api-forge.api_base', 'api'), '/');

            foreach (array_values($versions) as $index => $version) {
                $this->registerRouteSet(
                    prefix: "{$base}/{$version}",
                    namePrefix: $index === 0 ? '' : "{$version}.",
                    version: $version,
                );
            }

            return;
        }

        // Single-version mode (default)
        $this->registerRouteSet(
            prefix: config('filament-api-forge.api_prefix', 'api/v1'),
            namePrefix: '',
            version: null,
        );
    }

    protected function registerRouteSet(string $prefix, string $namePrefix, ?string $version): void
    {
        $versionMiddleware = $version !== null
            ? [SetApiForgeVersion::class . ':' . $version]
            : [];

        // Public routes: OpenAPI spec + public docs HTML + token refresh (no access-token auth)
        Route::prefix($prefix)
            ->name($namePrefix)
            ->middleware(array_merge(
                config('filament-api-forge.discovery.middleware', ['api']),
                $versionMiddleware,
            ))
            ->group(function () {
                Route::get('docs/openapi.json', [ApiDocumentationController::class, 'openApiSpec'])
                    ->name('api-forge.docs.openapi');

                Route::get('docs', [ApiDocumentationController::class, 'publicDocs'])
                    ->name('api-forge.docs.public');

                // Refresh must work with an expired access token — it is
                // authenticated by the forge_refresh_ token itself.
                Route::post('auth/token/refresh', [ApiTokenController::class, 'refresh'])
                    ->name('api-forge.token.refresh');
            });

        // Protected API routes
        $middleware = array_merge(
            [ForceJsonResponse::class, CheckResourceEnabled::class],
            config('filament-api-forge.discovery.middleware', ['api']),
            $versionMiddleware,
        );

        if (config('filament-api-forge.auth.enabled', true)) {
            $middleware[] = ApiForgeAuthenticate::class;
        }

        // EnforceApiForgeRules runs after auth so $request->user() is available.
        // CacheApiForgeResponse runs last so rate limiting + audit logging
        // still apply to cache hits.
        $middleware[] = EnforceApiForgeRules::class;
        $middleware[] = CacheApiForgeResponse::class;

        Route::prefix($prefix)
            ->name($namePrefix)
            ->middleware($middleware)
            ->group(function () {
                // GraphQL endpoint (feature-gated in the controller)
                Route::post('graphql', [GraphQlController::class, 'execute'])
                    ->name('api-forge.graphql');

                $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
            });
    }
}
