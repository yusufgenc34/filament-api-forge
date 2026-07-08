<?php

use YusufGenc34\FilamentApiForge\Http\Middleware\EnforceApiForgeRules;
use YusufGenc34\FilamentApiForge\Models\ApiForgeRequestLog;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use YusufGenc34\FilamentApiForge\Tests\Stubs\TestModel;
use Illuminate\Http\Request;

function auditMiddleware(): EnforceApiForgeRules
{
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\AuditResource',
        'model_class'    => TestModel::class,
        'slug'           => 'audits',
        'api_config'     => [],
    ]);

    return new EnforceApiForgeRules($mock);
}

function runAuditRequest(string $uri = '/api/v1/admin/audits', string $method = 'GET'): void
{
    $request = Request::create($uri, $method);
    $request->setRouteResolver(function () use ($request) {
        $route = new \Illuminate\Routing\Route(['GET'], '{panelId}/{resourceSlug}', []);
        $route->bind($request);
        $route->setParameter('panelId', 'admin');
        $route->setParameter('resourceSlug', 'audits');

        return $route;
    });

    auditMiddleware()->handle($request, fn () => response()->json(['ok' => true], 200));
}

beforeEach(function () {
    ApiForgeRequestLog::query()->delete();
    \Illuminate\Support\Facades\RateLimiter::clear('api-forge-dynamic|App\\Filament\\Resources\\AuditResource|index|127.0.0.1');
});

it('records an audit log entry for each request', function () {
    runAuditRequest();

    $log = ApiForgeRequestLog::first();

    expect(ApiForgeRequestLog::count())->toBe(1)
        ->and($log->resource_class)->toBe('App\\Filament\\Resources\\AuditResource')
        ->and($log->action)->toBe('index')
        ->and($log->method)->toBe('GET')
        ->and($log->status)->toBe(200)
        ->and($log->ip)->toBe('127.0.0.1')
        ->and($log->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('does not record logs when audit is disabled', function () {
    config()->set('filament-api-forge.audit.enabled', false);

    runAuditRequest();

    expect(ApiForgeRequestLog::count())->toBe(0);
});

it('prune command deletes old logs only', function () {
    ApiForgeRequestLog::create([
        'method' => 'GET', 'path' => 'old', 'status' => 200,
        'duration_ms' => 5, 'created_at' => now()->subDays(60),
    ]);
    ApiForgeRequestLog::create([
        'method' => 'GET', 'path' => 'fresh', 'status' => 200,
        'duration_ms' => 5, 'created_at' => now()->subDays(2),
    ]);

    $this->artisan('api-forge:prune-logs', ['--days' => 30])
        ->expectsOutputToContain('Pruned 1 request log(s)')
        ->assertExitCode(0);

    expect(ApiForgeRequestLog::count())->toBe(1)
        ->and(ApiForgeRequestLog::first()->path)->toBe('fresh');
});
