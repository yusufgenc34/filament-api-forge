# Filament API Forge

Automatically expose your **Filament Resources** as fully-featured REST APIs — with hash-based authentication, OpenAPI documentation, per-resource access control, rate limiting, and IP restrictions. No Sanctum required.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/yusufgenc/filament-api-forge.svg?style=flat-square)](https://packagist.org/packages/yusufgenc/filament-api-forge)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg?style=flat-square)](https://www.php.net)
[![Filament Version](https://img.shields.io/badge/Filament-5.x-orange.svg?style=flat-square)](https://filamentphp.com)
[![License](https://img.shields.io/packagist/l/yusufgenc/filament-api-forge.svg?style=flat-square)](LICENSE)

---

## Features

| Feature | Description |
|---------|-------------|
| **Auto-Discovery** | Automatically detects any Resource implementing `HasApi` — zero manual route registration |
| **Hash-Based Auth** | `forge_` prefix tokens (SHA-256 hashed at rest), Stripe/OpenAI style |
| **CRUD Endpoints** | `index`, `show`, `store`, `update`, `destroy` — enable only what you need |
| **Spatie Query Builder** | Filtering, sorting, field selection, eager loading, full-text search out of the box |
| **Scope Enforcement** | Per-token `read` / `write` / `delete` scopes, plus `*` for full access |
| **Access Control UI** | Enable/disable individual methods or entire resources from the Filament panel |
| **Rate Limiting** | Global, per-resource, and per-method rate limits — enforced via middleware |
| **IP Restrictions** | Whitelist IPs per resource or per method (CIDR, wildcard, exact) |
| **OpenAPI Docs** | Dynamically generated OpenAPI 3.0 spec + Stoplight Elements UI |
| **Developer Center** | Dashboard, API key management, and documentation embedded in your panel |

---

## Requirements

- PHP **8.2+**
- Laravel **12+**
- Filament **5.x**
- Spatie Laravel Query Builder **5+**

---

## Installation

```bash
composer require yusufgenc/filament-api-forge
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="filament-api-forge-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="filament-api-forge-config"
```

---

## Setup

### 1. Register the Plugin

Add `FilamentApiForgePlugin` to your panel provider:

```php
use YusufGenc34\FilamentApiForge\FilamentApiForgePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugin(
            FilamentApiForgePlugin::make()
                ->apiKeys()     // API key management page
                ->docs()        // OpenAPI documentation page
                ->dashboard()   // Developer Center dashboard
        );
}
```

All three are enabled by default. You can disable any section:

```php
FilamentApiForgePlugin::make()
    ->apiKeys()
    ->docs(false)       // hide the docs page
    ->dashboard(false)  // hide the dashboard
```

### 2. Expose a Resource

Implement `HasApi` on any Filament Resource and define `apiConfig()`:

```php
use YusufGenc34\FilamentApiForge\Contracts\HasApi;

class PostResource extends Resource implements HasApi
{
    public static function apiConfig(): array
    {
        return [
            'allowed_methods'   => ['index', 'show', 'store', 'update', 'destroy'],
            'allowed_filters'   => ['title', 'status', 'category_id'],
            'allowed_sorts'     => ['title', 'created_at', 'published_at'],
            'allowed_includes'  => ['author', 'category'],
            'allowed_fields'    => ['id', 'title', 'slug', 'body', 'status', 'published_at'],
            'searchable'        => ['title', 'body'],
            'scopes'            => ['read', 'write', 'delete'],
            'validation_rules'  => [           // optional — overrides auto-detected rules
                'title' => ['required', 'string', 'max:255'],
                'body'  => ['required', 'string'],
                'status'=> ['required', 'in:draft,published,archived'],
            ],
        ];
    }
}
```

#### `apiConfig()` Reference

| Key | Type | Description |
|-----|------|-------------|
| `allowed_methods` | `string[]` | Which CRUD operations to expose: `index`, `show`, `store`, `update`, `destroy` |
| `allowed_filters` | `string[]` | Columns clients can filter by (`?filter[title]=foo`) |
| `allowed_sorts` | `string[]` | Columns clients can sort by (`?sort=-created_at`) |
| `allowed_includes` | `string[]` | Eloquent relations clients can eager-load (`?include=author`) |
| `allowed_fields` | `string[]` | Columns clients can select (`?fields[posts]=id,title`) |
| `searchable` | `string[]` | Columns searched when `?search=query` is provided |
| `scopes` | `string[]` | Scopes a token must have to call this resource (`read`, `write`, `delete`) |
| `validation_rules` | `array` | Explicit validation rules for `store`/`update`. Falls back to `allowed_fields` → `$fillable` |

### 3. Enrich the OpenAPI Docs (optional)

Decorate your Resource class with attributes to improve generated documentation:

```php
use YusufGenc34\FilamentApiForge\Attributes\ApiTag;
use YusufGenc34\FilamentApiForge\Attributes\ApiDescription;
use YusufGenc34\FilamentApiForge\Attributes\ApiOperations;

#[ApiTag('Posts')]
#[ApiDescription('Manage blog posts and articles.')]
#[ApiOperations(
    index:   'List all posts with filtering and sorting',
    show:    'Retrieve a single post by ID',
    store:   ['summary' => 'Create a post', 'description' => 'Requires **write** scope.'],
    update:  ['summary' => 'Update a post', 'description' => 'Partial updates via PATCH.'],
    destroy: ['summary' => 'Delete a post', 'description' => 'Requires **delete** scope.'],
)]
class PostResource extends Resource implements HasApi
{
    // ...
}
```

| Attribute | Description |
|-----------|-------------|
| `#[ApiTag('Name')]` | Groups endpoints under a tag in the OpenAPI spec |
| `#[ApiDescription('...')]` | Sets the resource description in the docs |
| `#[ApiOperations(...)]` | Per-method summaries and descriptions |
| `#[ApiIgnore]` | Excludes the resource from the OpenAPI spec entirely |

---

## Authentication

All API requests must include a Bearer token:

```bash
curl -H "Authorization: Bearer forge_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
     https://yourapp.com/api/v1/admin/posts
```

### Token Format

Tokens use the `forge_` prefix followed by 40 random characters (238 bits of entropy). The **plain-text token is shown only once** at creation time — only a SHA-256 hash is stored in the database.

```
forge_<40-char random>
```

### Scopes

| Scope | Allowed operations |
|-------|--------------------|
| `read` | `GET` (index, show) |
| `write` | `POST`, `PUT`, `PATCH` (store, update) |
| `delete` | `DELETE` (destroy) |
| `*` | Full access — all of the above |

Tokens can also be restricted to specific resources by setting **Resource Access** when creating them.

---

## Making API Requests

The base URL pattern is:

```
{APP_URL}/api/v1/{panel_id}/{resource_slug}
```

### List resources

```bash
GET /api/v1/admin/posts
```

```bash
# With filtering, sorting, includes, and pagination
GET /api/v1/admin/posts?filter[status]=published&sort=-created_at&include=author&per_page=25
```

### Get a single record

```bash
GET /api/v1/admin/posts/1
```

### Create a record

```bash
POST /api/v1/admin/posts
Content-Type: application/json

{
  "title": "Hello World",
  "body": "Article body here...",
  "status": "draft"
}
```

### Update a record

```bash
PUT /api/v1/admin/posts/1
Content-Type: application/json

{
  "status": "published"
}
```

### Delete a record

```bash
DELETE /api/v1/admin/posts/1
```

### Query Parameters

| Parameter | Example | Description |
|-----------|---------|-------------|
| `filter[field]` | `?filter[status]=published` | Filter by field value (partial match) |
| `sort` | `?sort=-created_at` | Sort ascending or descending (prefix `-`) |
| `include` | `?include=author,category` | Eager-load relations |
| `fields[resource]` | `?fields[posts]=id,title` | Select specific fields |
| `search` | `?search=laravel` | Full-text search across `searchable` columns |
| `per_page` | `?per_page=50` | Results per page (max defined in config) |

---

## Access Control

The **Access Control** tab in the Developer Center lets you manage per-resource settings without touching code.

### Enable / Disable a Resource

Toggle the entire resource on or off. When disabled, all requests return `404`.

### Enable / Disable Individual Methods

Toggle specific HTTP methods per resource. Disabled methods return `405`.

### Rate Limiting

Set a request limit (requests per minute) at the resource level or per method. Method-level limits override resource-level limits.

```
Resource: 60 req/min  →  applies to all methods
Method (index): 10 req/min  →  overrides resource limit for GET /resource
```

Rate limit responses return HTTP `429` with headers:

```
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 0
Retry-After: 47
```

### IP Restrictions

Whitelist allowed IP addresses at the resource or method level. Supports:

| Format | Example |
|--------|---------|
| Exact IP | `192.168.1.100` |
| CIDR range | `10.0.0.0/8` |
| Wildcard | `192.168.1.*` |

Blocked requests return HTTP `403`.

---

## Response Format

All responses follow a consistent JSON structure.

### Collection (index)

```json
{
  "data": [
    { "id": 1, "title": "Hello World", "status": "published" }
  ],
  "links": {
    "first": "https://yourapp.com/api/v1/admin/posts?page=1",
    "last":  "https://yourapp.com/api/v1/admin/posts?page=5",
    "prev":  null,
    "next":  "https://yourapp.com/api/v1/admin/posts?page=2"
  },
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73,
    "api_version": "v1",
    "resource": "Posts"
  }
}
```

### Single record (show / store / update)

```json
{
  "data": {
    "id": 1,
    "title": "Hello World",
    "status": "published",
    "created_at": "2026-01-01T00:00:00.000000Z"
  }
}
```

### Error responses

| Status | `error` key | Cause |
|--------|------------|-------|
| `401` | `unauthenticated` | Missing or invalid token |
| `403` | `insufficient_scope` | Token lacks required scope |
| `403` | `resource_not_allowed` | Token restricted to other resources |
| `403` | `ip_not_allowed` | Client IP is not whitelisted |
| `404` | `not_found` | Resource or record not found / disabled |
| `405` | `method_not_allowed` | Method is disabled for this resource |
| `422` | _(validation)_ | Request data failed validation |
| `429` | `rate_limit_exceeded` | Too many requests |

---

## Configuration

```php
// config/filament-api-forge.php

return [
    'api_prefix'  => 'api/v1',
    'api_version' => 'v1',

    'auth' => [
        'enabled' => true,   // set false to disable auth entirely (dev only)
    ],

    'discovery' => [
        'middleware' => ['api'],
        'panels'     => [],  // restrict discovery to specific panels, empty = all
    ],

    'pagination' => [
        'default_per_page' => 15,
        'max_per_page'     => 100,
    ],

    'rate_limiting' => [
        'enabled'           => true,
        'requests_per_minute' => 60,
    ],
];
```

---

## Developer Center

The Developer Center is automatically embedded in your Filament panel under the **Developer Center** navigation group.

| Page | URL | Description |
|------|-----|-------------|
| Dashboard | `/admin/developer/dashboard` | Stats, resource list, endpoint reference, quick-start curl examples |
| API Keys | `/admin/developer/api-keys` | Create, view and revoke tokens |
| API Docs | `/admin/developer/api-docs` | Interactive OpenAPI documentation (Stoplight Elements) |

---

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

---

## Credits

Built by [Yusuf Genc](https://github.com/yusufgenc34).  
Powered by [Filament](https://filamentphp.com) and [Spatie Laravel Query Builder](https://github.com/spatie/laravel-query-builder).
