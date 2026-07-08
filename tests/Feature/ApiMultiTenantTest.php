<?php

use YusufGenc34\FilamentApiForge\Http\Controllers\ApiResourceController;
use YusufGenc34\FilamentApiForge\Models\ApiForgeToken;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

// ── Stubs ──────────────────────────────────────────────────────────────────

class TenantModel extends Model
{
    protected $table = 'tenant_models';
    protected $fillable = ['title', 'tenant_id'];
}

function tenantController(): ApiResourceController
{
    $mock = Mockery::mock(ResourceDiscoveryService::class);
    $mock->shouldReceive('findResource')->andReturn([
        'resource_class' => 'App\\Filament\\Resources\\TenantResource',
        'model_class'    => TenantModel::class,
        'slug'           => 'tenant-models',
        'plural_label'   => 'Tenant Models',
        'api_config'     => [
            'allowed_methods'  => ['index', 'show', 'store', 'update', 'destroy'],
            'tenant_column'    => 'tenant_id',
            'validation_rules' => ['title' => ['required', 'string']],
        ],
    ]);
    $mock->shouldReceive('isMethodAllowed')->andReturn(true);
    $mock->shouldReceive('getAllowedFilters')->andReturn([]);
    $mock->shouldReceive('getAllowedSorts')->andReturn([]);
    $mock->shouldReceive('getAllowedFields')->andReturn([]);
    $mock->shouldReceive('getAllowedIncludes')->andReturn([]);

    return new ApiResourceController($mock);
}

function actAsTenant(?string $tenantId, Request $request): void
{
    if ($tenantId !== null) {
        $token = new ApiForgeToken(['scopes' => ['*']]);
        $token->tenant_id = $tenantId;
        $request->attributes->set('api_forge_token', $token);
    }

    app()->instance('request', $request);
}

beforeEach(function () {
    if (! Schema::hasTable('tenant_models')) {
        Schema::create('tenant_models', function ($table) {
            $table->id();
            $table->string('title');
            $table->string('tenant_id')->nullable();
            $table->timestamps();
        });
    }

    TenantModel::query()->delete();
    config()->set('filament-api-forge.auth.enabled', false);

    TenantModel::create(['title' => 'Acme post', 'tenant_id' => 'acme']);
    TenantModel::create(['title' => 'Globex post', 'tenant_id' => 'globex']);
});

it('index only returns records of the token tenant', function () {
    $request = Request::create('/api/v1/admin/tenant-models', 'GET');
    actAsTenant('acme', $request);

    $response = tenantController()->index($request, 'admin', 'tenant-models');
    $data = $response->toResponse($request)->getData(true);

    expect($data['data'])->toHaveCount(1)
        ->and($data['data'][0]['title'])->toBe('Acme post');
});

it('index returns everything when the token has no tenant', function () {
    $request = Request::create('/api/v1/admin/tenant-models', 'GET');
    actAsTenant(null, $request);

    $response = tenantController()->index($request, 'admin', 'tenant-models');
    $data = $response->toResponse($request)->getData(true);

    expect($data['data'])->toHaveCount(2);
});

it('show cannot access another tenant record', function () {
    $other = TenantModel::where('tenant_id', 'globex')->first();

    $request = Request::create('/api/v1/admin/tenant-models/' . $other->id, 'GET');
    actAsTenant('acme', $request);

    expect(fn () => tenantController()->show($request, 'admin', 'tenant-models', (string) $other->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('store stamps the token tenant onto created records', function () {
    $request = Request::create('/api/v1/admin/tenant-models', 'POST', ['title' => 'Stamped']);
    actAsTenant('acme', $request);

    tenantController()->store($request, 'admin', 'tenant-models');

    expect(TenantModel::where('title', 'Stamped')->first()->tenant_id)->toBe('acme');
});

it('update cannot modify another tenant record', function () {
    $other = TenantModel::where('tenant_id', 'globex')->first();

    $request = Request::create('/api/v1/admin/tenant-models/' . $other->id, 'PUT', ['title' => 'Hijacked']);
    actAsTenant('acme', $request);

    expect(fn () => tenantController()->update($request, 'admin', 'tenant-models', (string) $other->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('tenant scoping is inert when multi_tenant is disabled', function () {
    config()->set('filament-api-forge.multi_tenant.enabled', false);

    $request = Request::create('/api/v1/admin/tenant-models', 'GET');
    actAsTenant('acme', $request);

    $response = tenantController()->index($request, 'admin', 'tenant-models');
    $data = $response->toResponse($request)->getData(true);

    expect($data['data'])->toHaveCount(2);
});
