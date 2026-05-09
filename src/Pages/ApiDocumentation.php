<?php

namespace YusufGenc34\FilamentApiForge\Pages;

use YusufGenc34\FilamentApiForge\Attributes\ApiTag;
use YusufGenc34\FilamentApiForge\Contracts\HasApi;
use YusufGenc34\FilamentApiForge\Http\Controllers\ApiDocumentationController;
use YusufGenc34\FilamentApiForge\Models\ApiForgeResourceSetting;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;

class ApiDocumentation extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
    protected static string | \UnitEnum | null $navigationGroup = 'Developer Center';
    protected static ?string $navigationLabel = 'API Docs';
    protected static ?string $title = 'API Documentation';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'developer/api-docs';
    protected string $view = 'filament-api-forge::api-documentation';

    public array  $groupedEndpoints    = [];
    public array  $schemas             = [];
    public array  $responseComponents  = [];
    public string $baseUrl             = '';
    public string $version             = '';
    public string $apiTitle            = '';
    public string $apiDescription      = '';
    public string $openApiUrl          = '';

    public array   $collapsedGroups    = [];
    public ?string $selectedEndpointId = null;
    public ?array  $selectedEndpoint   = null;
    public ?string $selectedSchemaName = null;

    public array $resourceStates = [];

    public bool   $tryPanelOpen   = false;
    public string $tryToken       = '';
    public string $tryUrl         = '';
    public string $tryBody        = '{}';
    public array  $tryQueryParams = [];

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $controller = app(ApiDocumentationController::class);
        $data = $controller->openApiSpec(request())->getData(true);

        $this->baseUrl            = $data['servers'][0]['url'] ?? url('/');
        $this->version            = $data['info']['version'] ?? 'v1';
        $this->apiTitle           = $data['info']['title'] ?? 'API Documentation';
        $this->apiDescription     = $data['info']['description'] ?? '';
        $this->schemas            = $data['components']['schemas'] ?? [];
        $this->responseComponents = $data['components']['responses'] ?? [];
        $this->openApiUrl         = route('api-forge.docs.openapi');

        $grouped = [];
        foreach ($data['paths'] ?? [] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $tag = $operation['tags'][0] ?? 'General';
                $id  = Str::slug($method . '-' . str_replace(['/', '{', '}'], ['-', '', ''], $path));
                $grouped[$tag][] = [
                    'id'        => $id,
                    'method'    => strtoupper($method),
                    'path'      => $path,
                    'summary'   => $operation['summary'] ?? '',
                    'operation' => $operation,
                ];
            }
        }
        $this->groupedEndpoints = $grouped;

        $this->loadResourceStates();

        if (!empty($grouped)) {
            $firstGroup = array_key_first($grouped);
            $this->selectEndpoint($grouped[$firstGroup][0]['id']);
        }
    }

    public function toggleResource(string $resourceClass): void
    {
        ApiForgeResourceSetting::forResource($resourceClass)->toggleEnabled();
        app(ResourceDiscoveryService::class)->flush();
        $this->reinitialize();
    }

    public function toggleMethod(string $resourceClass, string $method): void
    {
        ApiForgeResourceSetting::forResource($resourceClass)->toggleMethod($method);
        app(ResourceDiscoveryService::class)->flush();
        $this->reinitialize();
    }

    public function saveResourceSettings(string $resourceClass, ?int $rateLimit, string $allowedIps): void
    {
        $ips = array_values(array_filter(array_map('trim', explode("\n", $allowedIps))));
        ApiForgeResourceSetting::forResource($resourceClass)->saveSettings($rateLimit ?: null, $ips);
        $this->loadResourceStates();

        Notification::make()->title('Resource settings saved')->success()->send();
    }

    public function saveMethodSettings(string $resourceClass, string $method, ?int $rateLimit, string $allowedIps): void
    {
        $ips = array_values(array_filter(array_map('trim', explode("\n", $allowedIps))));
        ApiForgeResourceSetting::forResource($resourceClass)->saveMethodConfig($method, $rateLimit ?: null, $ips);
        $this->loadResourceStates();

        Notification::make()->title('Method settings saved')->success()->send();
    }

    private function reinitialize(): void
    {
        $controller = app(ApiDocumentationController::class);
        $data       = $controller->openApiSpec(request())->getData(true);

        $this->schemas            = $data['components']['schemas'] ?? [];
        $this->responseComponents = $data['components']['responses'] ?? [];

        $grouped = [];
        foreach ($data['paths'] ?? [] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $tag     = $operation['tags'][0] ?? 'General';
                $id      = Str::slug($method . '-' . str_replace(['/', '{', '}'], ['-', '', ''], $path));
                $grouped[$tag][] = [
                    'id'        => $id,
                    'method'    => strtoupper($method),
                    'path'      => $path,
                    'summary'   => $operation['summary'] ?? '',
                    'operation' => $operation,
                ];
            }
        }
        $this->groupedEndpoints = $grouped;
        $this->loadResourceStates();

        if ($this->selectedEndpointId) {
            $found = false;
            foreach ($grouped as $endpoints) {
                foreach ($endpoints as $ep) {
                    if ($ep['id'] === $this->selectedEndpointId) {
                        $this->selectEndpoint($ep['id']);
                        $found = true;
                        break 2;
                    }
                }
            }
            if (! $found) {
                $this->selectedEndpoint   = null;
                $this->selectedEndpointId = null;
            }
        }
    }

    public function toggleGroup(string $group): void
    {
        if (in_array($group, $this->collapsedGroups)) {
            $this->collapsedGroups = array_values(
                array_filter($this->collapsedGroups, fn ($g) => $g !== $group)
            );
        } else {
            $this->collapsedGroups[] = $group;
        }
    }

    public function selectEndpoint(string $id): void
    {
        foreach ($this->groupedEndpoints as $endpoints) {
            foreach ($endpoints as $ep) {
                if ($ep['id'] !== $id) continue;

                $this->selectedEndpointId = $id;
                $this->selectedSchemaName = null;
                $this->tryPanelOpen       = false;

                $ep['operation']['responses'] = $this->resolveResponses(
                    $ep['operation']['responses'] ?? []
                );

                $this->selectedEndpoint = $ep;
                $this->tryUrl           = $this->baseUrl . $ep['path'];
                $this->tryQueryParams   = [];

                foreach ($ep['operation']['parameters'] ?? [] as $param) {
                    if (($param['in'] ?? '') === 'query') {
                        $this->tryQueryParams[] = [
                            'key'      => $param['name'],
                            'value'    => '',
                            'type'     => $param['schema']['type'] ?? 'string',
                            'desc'     => $param['description'] ?? '',
                            'required' => $param['required'] ?? false,
                        ];
                    }
                }

                if (in_array($ep['method'], ['POST', 'PUT', 'PATCH'])) {
                    $ref        = $ep['operation']['requestBody']['content']['application/json']['schema']['$ref'] ?? '';
                    $schemaName = str_replace('#/components/schemas/', '', $ref);
                    $schema     = $this->schemas[$schemaName] ?? null;
                    if ($schema) {
                        $body = [];
                        foreach ($schema['properties'] ?? [] as $prop => $def) {
                            if ($def['readOnly'] ?? false) continue;
                            $body[$prop] = match ($def['type'] ?? 'string') {
                                'integer' => 0,
                                'boolean' => false,
                                default   => '',
                            };
                        }
                        $this->tryBody = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    } else {
                        $this->tryBody = '{}';
                    }
                } else {
                    $this->tryBody = '';
                }
                return;
            }
        }
    }

    public function updateQueryParam(int $index, string $value): void
    {
        if (isset($this->tryQueryParams[$index])) {
            $this->tryQueryParams[$index]['value'] = $value;
        }
    }

    public function selectSchema(string $name): void
    {
        $this->selectedSchemaName = $name;
        $this->selectedEndpointId = null;
        $this->selectedEndpoint   = null;
        $this->tryPanelOpen       = false;
    }

    public function getMethodColor(string $method): string
    {
        return match (strtoupper($method)) {
            'GET'          => 'success',
            'POST'         => 'info',
            'PUT', 'PATCH' => 'warning',
            'DELETE'       => 'danger',
            default        => 'gray',
        };
    }

    private function loadResourceStates(): void
    {
        $settings = ApiForgeResourceSetting::all()->keyBy('resource_class');

        $this->resourceStates = [];

        foreach (filament()->getPanels() as $panel) {
            foreach ($panel->getResources() as $resourceClass) {
                if (! is_subclass_of($resourceClass, HasApi::class)) {
                    continue;
                }

                $setting    = $settings->get($resourceClass);
                $tag        = $this->resolveResourceTag($resourceClass);
                $apiConfig  = $resourceClass::apiConfig();
                $allMethods = $apiConfig['allowed_methods'] ?? ['index', 'show', 'store', 'update', 'destroy'];

                $disabledMethods = $setting ? ($setting->disabled_methods ?? []) : [];

                // Per-method settings
                $methodSettings = [];
                foreach ($allMethods as $m) {
                    $mc = $setting ? $setting->getMethodConfig($m) : [];
                    $methodSettings[$m] = [
                        'rate_limit'  => $mc['rate_limit'] ?? null,
                        'allowed_ips' => $mc['allowed_ips'] ?? [],
                    ];
                }

                $this->resourceStates[$resourceClass] = [
                    'enabled'          => $setting ? $setting->enabled : true,
                    'tag'              => $tag,
                    'allowed_methods'  => $allMethods,
                    'disabled_methods' => $disabledMethods,
                    'rate_limit'       => $setting?->rate_limit,
                    'allowed_ips'      => $setting?->allowed_ips ?? [],
                    'method_settings'  => $methodSettings,
                    'model_fields'     => $this->resolveModelFields($resourceClass),
                    'api_config'       => $apiConfig,
                ];
            }
        }
    }

    private function resolveResourceTag(string $resourceClass): string
    {
        $ref   = new \ReflectionClass($resourceClass);
        $attrs = $ref->getAttributes(ApiTag::class);

        if (! empty($attrs)) {
            return $attrs[0]->newInstance()->name;
        }

        return $resourceClass::getPluralModelLabel();
    }

    private function resolveModelFields(string $resourceClass): array
    {
        try {
            $modelClass = $resourceClass::getModel();
            $model      = new $modelClass();
            return $model->getFillable();
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolveResponses(array $responses): array
    {
        $out = [];
        foreach ($responses as $code => $resp) {
            if (isset($resp['$ref'])) {
                $key  = str_replace('#/components/responses/', '', $resp['$ref']);
                $resp = $this->responseComponents[$key] ?? ['description' => $key];
            }
            $schema = $resp['content']['application/json']['schema'] ?? null;
            if ($schema !== null) {
                $resp['_example'] = $this->generateExampleFromSchema($schema);
            }
            $out[$code] = $resp;
        }
        return $out;
    }

    private function generateExampleFromSchema(array $schema): mixed
    {
        if (isset($schema['$ref'])) {
            $name     = str_replace('#/components/schemas/', '', $schema['$ref']);
            $resolved = $this->schemas[$name] ?? null;
            return $resolved ? $this->generateExampleFromSchema($resolved) : null;
        }
        if (array_key_exists('example', $schema)) {
            return $schema['example'];
        }
        $type = $schema['type'] ?? 'object';
        if ($type === 'object') {
            $obj = [];
            foreach ($schema['properties'] ?? [] as $prop => $def) {
                $obj[$prop] = $this->generateExampleFromSchema($def);
            }
            return $obj;
        }
        if ($type === 'array') {
            $item = $this->generateExampleFromSchema($schema['items'] ?? ['type' => 'string']);
            return $item !== null ? [$item] : [];
        }
        return match ($type) {
            'integer' => 1,
            'number'  => 1.0,
            'boolean' => true,
            'null'    => null,
            default   => '…',
        };
    }
}
