# Filament API Forge — Documentation

Welcome to the detailed documentation for **Filament API Forge**. This guide covers every feature, configuration option, and extension point.

---

## Table of Contents

- [1. Architecture & Design Principles](#1-architecture--design-principles)
- [2. Installation & Setup](#2-installation--setup)
- [3. Exposing Resources](#3-exposing-resources)
- [4. Authentication & Security](#4-authentication--security)
- [5. Policy Authorization](#5-policy-authorization)
- [6. REST API Endpoints & Query Building](#6-rest-api-endpoints--query-building)
- [7. Custom Response Formatting](#7-custom-response-formatting)
- [8. Relationship Syncing](#8-relationship-syncing)
- [9. Lifecycle Hooks & Events](#9-lifecycle-hooks--events)
- [10. File Uploads](#10-file-uploads)
- [11. Custom Action Endpoints](#11-custom-action-endpoints)
- [12. Nested Resources](#12-nested-resources)
- [13. Batch Operations](#13-batch-operations)
- [14. Soft Deletes](#14-soft-deletes)
- [15. Data Export](#15-data-export)
- [16. Multi-Tenancy](#16-multi-tenancy)
- [17. API Versioning](#17-api-versioning)
- [18. Webhooks & Audit Logging](#18-webhooks--audit-logging)
- [19. GraphQL Support](#19-graphql-support)
- [20. Developer Center Panel Pages](#20-developer-center-panel-pages)

---

## 1. Architecture & Design Principles

Filament API Forge brings Filament's Server-Driven UI principles to API development. Instead of writing custom controllers, form requests, and route definitions for every model, you simply implement the `HasApi` interface on your Filament Resource.

Key design principles:
- **Zero Route Boilerplate:** Automatic discovery of `HasApi` resources.
- **Hash-Based Token Auth:** Fast, secure, `forge_`-prefixed tokens (hashed at rest with SHA-256) without requiring Laravel Sanctum.
- **Spatie Query Builder:** Industry-standard URL filtering, sorting, field selection, and relation loading.
- **Enterprise Security:** Built-in rate limiting, IP whitelisting, scope control, and Laravel Policy enforcement.

---

## 2. Installation & Setup

### Requirements
- PHP 8.2+
- Laravel 12+ / 13+
- Filament 5.x

```bash
composer require yusufgenc/filament-api-forge
```

Publish and run migrations:
```bash
php artisan vendor:publish --tag="filament-api-forge-migrations"
php artisan migrate
```

Optionally publish the configuration file:
```bash
php artisan vendor:publish --tag="filament-api-forge-config"
```

### Registering the Plugin
In your Filament Panel Provider (`AdminPanelProvider.php`):
```php
use YusufGenc34\FilamentApiForge\FilamentApiForgePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            FilamentApiForgePlugin::make()
                ->apiKeys()     // Enable API Key Management
                ->docs()        // Enable OpenAPI Docs & Settings
                ->dashboard()   // Enable Developer Center Dashboard
        );
}
```

---

## 3. Exposing Resources

To expose any Filament Resource as a REST endpoint, implement `HasApi` and define `apiConfig()`:

```php
namespace App\Filament\Resources;

use Filament\Resources\Resource;
use YusufGenc34\FilamentApiForge\Contracts\HasApi;

class PostResource extends Resource implements HasApi
{
    public static function apiConfig(): array
    {
        return [
            'allowed_methods'   => ['index', 'show', 'store', 'update', 'destroy'],
            'allowed_filters'   => ['title', 'status', 'category_id'],
            'allowed_sorts'     => ['title', 'created_at'],
            'allowed_includes'  => ['author', 'category'],
            'allowed_fields'    => ['id', 'title', 'slug', 'body', 'status', 'created_at'],
            'searchable'        => ['title', 'body'],
            'scopes'            => ['read', 'write', 'delete'],
            'validation_rules'  => [
                'title'  => ['required', 'string', 'max:255'],
                'body'   => ['required', 'string'],
                'status' => ['required', 'in:draft,published'],
            ],
        ];
    }
}
```

---

## 4. Authentication & Security

### Bearer Token Header
Include the token in all requests:
```bash
Authorization: Bearer forge_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Token Scopes
Tokens can be restricted with specific scopes:
- `read`: Allows `GET` index, show, and export requests.
- `write`: Allows `POST`, `PUT`, `PATCH` requests.
- `delete`: Allows `DELETE` requests.
- `*`: Grants full access across all operations.

### Rate Limiting & IP Whitelisting
Configure limits in `config/filament-api-forge.php` or dynamically per resource/method in the Developer Center Access Control panel page:
- IP rules support CIDR notation (`192.168.1.0/24`), wildcards (`10.0.*.*`), or exact IPs.

---

## 5. Policy Authorization

Filament API Forge integrates with Laravel Model Policies.

### Enabling Policy Checks
Enable globally in `config/filament-api-forge.php`:
```php
'policies' => [
    'enabled' => true,
],
```

Or enable per resource in `apiConfig()`:
```php
'use_policies' => true,
```

### Ability Mapping
When enabled, requests automatically evaluate against the model's registered Policy:
- `index` / `export` → `viewAny`
- `show` → `view`
- `store` → `create`
- `update` → `update`
- `destroy` → `delete`
- `restore` → `restore`
- `forceDelete` → `forceDelete`

If policy authorization fails, the API returns a `403 Forbidden` response.

---

## 6. REST API Endpoints & Query Building

Base URL pattern: `/api/v1/{panel_id}/{resource_slug}`

### Query Parameters
- **Filtering:** `?filter[status]=published`
- **Sorting:** `?sort=-created_at` (prefix `-` for descending)
- **Eager Loading:** `?include=author,category`
- **Sparse Fieldsets:** `?fields[posts]=id,title`
- **Search:** `?search=laravel`
- **Pagination:** `?per_page=25&page=2`

---

## 7. Custom Response Formatting

By default, responses are formatted via `ApiForgeJsonResource`.

### Custom Eloquent Resource Wrapper
To return responses using a custom `Illuminate\Http\Resources\Json\JsonResource` class, declare `api_resource` in `apiConfig()`:

```php
public static function apiConfig(): array
{
    return [
        'api_resource' => App\Http\Resources\CustomPostResource::class,
    ];
}
```

### Inline Transformation
Alternatively, define an `apiTransform` method directly on your Resource class:
```php
public static function apiTransform(Model $record, array $data): array
{
    $data['title'] = strtoupper($data['title']);
    unset($data['internal_notes']);
    return $data;
}
```

---

## 8. Relationship Syncing

When creating or updating a record via `POST` or `PUT`/`PATCH`, Filament API Forge can automatically sync `BelongsToMany` or `MorphToMany` relationships if an array of IDs is supplied in the request payload:

```json
{
  "title": "Post with Tags",
  "tags": [1, 2, 5]
}
```

Disabling relation syncing per resource:
```php
'sync_relations' => false,
```

---

## 9. Lifecycle Hooks & Events

Add the `ApiForgeHooks` trait to your Resource class to intercept write operations:

```php
use YusufGenc34\FilamentApiForge\Traits\ApiForgeHooks;

class PostResource extends Resource implements HasApi
{
    use ApiForgeHooks;

    public static function beforeCreate(array $data): array
    {
        $data['slug'] = Str::slug($data['title']);
        return $data;
    }

    public static function afterCreate(Model $record, array $data): void
    {
        // Log, notify, or dispatch background jobs...
    }
}
```

Available hooks: `beforeCreate`, `afterCreate`, `beforeUpdate`, `afterUpdate`, `beforeDelete`, `afterDelete`, `beforeRestore`, `afterRestore`, `beforeForceDelete`, `afterForceDelete`.

---

## 10. File Uploads

Declare uploadable fields in `apiConfig()`:

```php
'uploads' => [
    'avatar' => [
        'disk'      => 'public',
        'directory' => 'avatars',
        'rules'     => 'image|max:2048',
    ],
],
```

Send `multipart/form-data` requests. Supports both standard Laravel Filesystem storage and Spatie Media Library.

---

## 11. Custom Action Endpoints

Expose custom domain methods using the `#[ApiAction]` attribute:

```php
use YusufGenc34\FilamentApiForge\Attributes\ApiAction;

#[ApiAction('publish', method: 'POST', scope: 'write')]
public static function publish(Model $record, array $data): array
{
    $record->update(['status' => 'published']);
    return ['status' => 'published'];
}
```

Endpoint: `POST /api/v1/admin/posts/1/actions/publish`

---

## 12. Nested Resources

Expose child relationships under parent URLs:

```php
'relations' => [
    'comments' => [
        'relation_name'   => 'comments',
        'allowed_methods' => ['index', 'show', 'store', 'update', 'destroy'],
    ],
],
```

Endpoint pattern: `/api/v1/admin/posts/1/comments`

---

## 13. Batch Operations

Execute bulk CRUD and soft-delete operations in a single database transaction:

```bash
POST /api/v1/admin/posts/batch
```

```json
{
  "create": [{ "title": "First" }],
  "update": [{ "id": 5, "title": "Updated" }],
  "delete": [10],
  "restore": [12],
  "forceDelete": [15]
}
```

---

## 14. Soft Deletes

For models with `SoftDeletes`, include `'restore'` and `'forceDelete'` in `allowed_methods`:

- `GET /api/v1/admin/posts?trashed=only`
- `POST /api/v1/admin/posts/1/restore`
- `DELETE /api/v1/admin/posts/1/force`

---

## 15. Data Export

Stream filtered data as CSV or JSON:

```bash
GET /api/v1/admin/posts/export?format=csv&filter[status]=published
```

---

## 16. Multi-Tenancy

Scope tokens to a specific tenant ID:
```php
'tenant_column' => 'tenant_id',
```

---

## 17. API Versioning

Support multi-version routing (`/api/v1/...`, `/api/v2/...`) with the `#[ApiVersion('v2')]` attribute.

---

## 18. Webhooks & Audit Logging

- **Audit Logs:** Every API request is recorded in `api_forge_request_logs`.
- **Webhooks:** Signed HTTP POST callbacks on write events with HMAC SHA-256 signatures.

---

## 19. GraphQL Support

Enable GraphQL in `config/filament-api-forge.php` to expose an auto-generated `/graphql` endpoint for queries and mutations.

---

## 20. Developer Center Panel Pages

Embedded in your Filament panel:
- **Dashboard:** Performance metrics, request logs, active tokens.
- **API Keys:** Issue, revoke, and manage scopes and resource restrictions.
- **API Docs:** Interactive Swagger UI & public documentation link.
- **Access Control:** Per-resource and per-method rate limits & IP rules.
