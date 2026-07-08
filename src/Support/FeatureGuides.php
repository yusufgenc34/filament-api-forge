<?php

namespace YusufGenc34\FilamentApiForge\Support;

use YusufGenc34\FilamentApiForge\Models\ApiForgeGlobalSetting;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Support\Str;

/**
 * Feature guides (GraphQL, export, batch, soft deletes, custom actions,
 * token lifecycle, webhooks) rendered on the panel API Docs page.
 * Examples use the first discovered resource so they are copy-paste runnable.
 */
class FeatureGuides
{
    /**
     * @return array<string, array{title: string, badge: string, desc: string, blocks: array<int, array{label: string, code: string}>}>
     */
    public static function build(string $baseUrl): array
    {
        $resource = app(ResourceDiscoveryService::class)->discover()->first();

        $slug     = $resource['slug'] ?? 'posts';
        $segment  = ApiForgeGlobalSetting::get('route_segment') ?? ($resource['panel_id'] ?? 'admin');
        $base     = "{$baseUrl}/{$segment}/{$slug}";
        $plural   = Str::camel(Str::plural($slug));
        $singular = Str::studly(Str::singular($slug));
        $actions  = config('filament-api-forge.actions.prefix', 'actions');
        $auth     = "-H \"Authorization: Bearer forge_...\"";

        $guides = [
            'graphql' => [
                'title'  => 'GraphQL',
                'badge'  => config('filament-api-forge.graphql.enabled') ? 'enabled' : 'disabled',
                'desc'   => 'A single GraphQL endpoint generated from the same resources as the REST API. Queries need the read scope, mutations write/delete. '
                    . (config('filament-api-forge.graphql.enabled')
                        ? 'GraphQL is enabled on this application.'
                        : 'Currently disabled — set API_FORGE_GRAPHQL=true and install webonyx/graphql-php to enable it.'),
                'blocks' => [
                    ['label' => 'Endpoint', 'code' => "POST {$baseUrl}/graphql"],
                    ['label' => 'List query', 'code' => "query {\n  {$plural}(page: 1, perPage: 20, search: \"laravel\") {\n    total\n    currentPage\n    data { id title status }\n  }\n}"],
                    ['label' => 'Single record', 'code' => "query {\n  " . Str::camel(Str::singular($slug)) . "(id: 1) { id title status }\n}"],
                    ['label' => 'Mutations', 'code' => "mutation { create{$singular}(title: \"Via GraphQL\", status: \"draft\") { id } }\nmutation { update{$singular}(id: 1, status: \"published\") { status } }\nmutation { delete{$singular}(id: 1) }"],
                    ['label' => 'cURL', 'code' => "curl -X POST {$baseUrl}/graphql \\\n  {$auth} \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"query\": \"{ {$plural} { total data { id } } }\"}'"],
                ],
            ],
            'export' => [
                'title'  => 'Export (CSV / JSON)',
                'badge'  => 'GET',
                'desc'   => "Streams the filtered result set without pagination. Add 'export' to the resource's allowed_methods to expose it. Columns follow allowed_fields; rows are capped by export.max_rows (" . config('filament-api-forge.export.max_rows', 10000) . ').',
                'blocks' => [
                    ['label' => 'CSV download', 'code' => "curl -OJ {$auth} \\\n  \"{$base}/export\""],
                    ['label' => 'JSON with filters', 'code' => "curl {$auth} \\\n  \"{$base}/export?format=json&filter[status]=published&sort=-created_at\""],
                ],
            ],
            'batch' => [
                'title'  => 'Batch Operations',
                'badge'  => 'POST',
                'desc'   => 'Bulk create, update and delete in one transaction-wrapped request. Every row runs the full validation + hooks + events pipeline. Failed rows are reported per index without aborting the batch.',
                'blocks' => [
                    ['label' => 'Request', 'code' => "curl -X POST {$auth} \\\n  -H \"Content-Type: application/json\" \\\n  \"{$base}/batch\" \\\n  -d '{\n    \"create\": [{ \"title\": \"First\" }, { \"title\": \"Second\" }],\n    \"update\": [{ \"id\": 7, \"status\": \"published\" }],\n    \"delete\": [3, 4]\n  }'"],
                    ['label' => 'Response', 'code' => "{\n  \"message\": \"Batch operation completed.\",\n  \"created\": [12, 13],\n  \"updated\": [7],\n  \"deleted\": [3, 4],\n  \"failed\": []\n}"],
                ],
            ],
            'soft-deletes' => [
                'title'  => 'Soft Deletes',
                'badge'  => 'REST',
                'desc'   => "For models using the SoftDeletes trait. Expose by adding 'restore' and 'forceDelete' to allowed_methods. restore requires the write scope, forceDelete the delete scope.",
                'blocks' => [
                    ['label' => 'Query trashed records', 'code' => "GET {$base}?trashed=only    # only trashed\nGET {$base}?trashed=with    # trashed + live"],
                    ['label' => 'Restore', 'code' => "curl -X POST {$auth} \"{$base}/1/restore\""],
                    ['label' => 'Permanent delete', 'code' => "curl -X DELETE {$auth} \"{$base}/1/force\""],
                ],
            ],
            'custom-actions' => [
                'title'  => 'Custom Actions',
                'badge'  => 'ATTR',
                'desc'   => 'Expose domain operations beyond CRUD with the #[ApiAction] attribute on a public static Resource method. record: false makes the action collection-level (no record ID).',
                'blocks' => [
                    ['label' => 'Define on the Resource', 'code' => "#[ApiAction('publish', method: 'POST', scope: 'write')]\npublic static function publish(Model \$record, array \$data): array\n{\n    \$record->update(['status' => 'published']);\n    return ['published' => true];\n}\n\n#[ApiAction('sync', method: 'POST', scope: 'write', record: false)]\npublic static function sync(array \$data): array\n{\n    return ['synced' => true];\n}"],
                    ['label' => 'Call them', 'code' => "curl -X POST {$auth} \"{$base}/1/{$actions}/publish\"   # record-level\ncurl -X POST {$auth} \"{$base}/{$actions}/sync\"        # collection-level"],
                ],
            ],
            'token-lifecycle' => [
                'title'  => 'Token Refresh & Rotation',
                'badge'  => 'AUTH',
                'desc'   => 'Rotate replaces the current access token immediately. Refresh exchanges a forge_refresh_ token for a new pair — it works even after the access token expired (enable with API_FORGE_REFRESH_TOKENS=true).',
                'blocks' => [
                    ['label' => 'Rotate (authenticated)', 'code' => "curl -X POST {$auth} \\\n  {$baseUrl}/auth/token/rotate"],
                    ['label' => 'Refresh (by refresh token)', 'code' => "curl -X POST -H \"Content-Type: application/json\" \\\n  {$baseUrl}/auth/token/refresh \\\n  -d '{\"refresh_token\": \"forge_refresh_...\"}'"],
                ],
            ],
            'webhooks' => [
                'title'  => 'Webhooks',
                'badge'  => 'HMAC',
                'desc'   => 'Signed HTTP callbacks on every API write (created, updated, deleted, restored, force_deleted, action_executed). Manage endpoints under Developer Center → Webhooks. Deliveries are queued with 3 retries.',
                'blocks' => [
                    ['label' => 'Payload', 'code' => "{\n  \"event\": \"created\",\n  \"resource\": \"{$singular}Resource\",\n  \"timestamp\": \"2026-01-01T00:00:00+00:00\",\n  \"record\": { \"id\": 42, \"attributes\": { \"title\": \"Hello\" } }\n}"],
                    ['label' => 'Verify the signature (PHP)', 'code' => "\$signature = \$request->header('X-ApiForge-Signature');\n\$expected  = 'sha256=' . hash_hmac('sha256', \$request->getContent(), \$secret);\n\nabort_unless(hash_equals(\$expected, \$signature), 401);"],
                ],
            ],
        ];

        return $guides;
    }
}
