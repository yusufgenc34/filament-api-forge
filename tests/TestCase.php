<?php

namespace YusufGenc34\FilamentApiForge\Tests;

use YusufGenc34\FilamentApiForge\FilamentApiForgeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Model;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::unguard();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Spatie\QueryBuilder\QueryBuilderServiceProvider::class,
            FilamentApiForgeServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filament-api-forge', require __DIR__ . '/../config/filament-api-forge.php');
        $app['config']->set('filament-api-forge.auth.enabled', true);
        $app['config']->set('filament-api-forge.api_prefix', 'api/v1');
        $app['config']->set('filament-api-forge.rate_limit', 60);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
    }

    protected function setUpDatabase(): void
    {
        if (! Schema::hasTable('api_forge_tokens')) {
            Schema::create('api_forge_tokens', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('tenant_id')->nullable()->index();
                $table->string('name');
                $table->string('token_hash', 64)->unique();
                $table->string('token_prefix', 16);
                $table->string('refresh_token_hash', 64)->nullable()->unique();
                $table->json('scopes')->nullable();
                $table->json('allowed_resources')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expiry_notified_at')->nullable();
                $table->unsignedBigInteger('request_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('api_forge_request_logs')) {
            Schema::create('api_forge_request_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('token_id')->nullable()->index();
                $table->string('resource_class')->nullable()->index();
                $table->string('action', 64)->nullable();
                $table->string('method', 10);
                $table->string('path', 2048);
                $table->unsignedSmallInteger('status');
                $table->unsignedInteger('duration_ms');
                $table->string('ip', 45)->nullable();
                $table->timestamp('created_at')->index();
            });
        }

        if (! Schema::hasTable('api_forge_webhooks')) {
            Schema::create('api_forge_webhooks', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('url', 2048);
                $table->string('secret')->nullable();
                $table->json('events');
                $table->string('resource_class')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('failure_count')->default(0);
                $table->timestamp('last_triggered_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('api_forge_resource_settings')) {
            Schema::create('api_forge_resource_settings', function (Blueprint $table) {
                $table->id();
                $table->string('resource_class')->unique()->index();
                $table->boolean('enabled')->default(true);
                $table->unsignedSmallInteger('rate_limit')->nullable();
                $table->json('allowed_ips')->nullable();
                $table->json('disabled_methods')->nullable();
                $table->json('method_settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('api_forge_global_settings')) {
            Schema::create('api_forge_global_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique()->index();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
    }
}
