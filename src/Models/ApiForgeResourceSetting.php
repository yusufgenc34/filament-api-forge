<?php

namespace YusufGenc34\FilamentApiForge\Models;

use Illuminate\Database\Eloquent\Model;

class ApiForgeResourceSetting extends Model
{
    protected $table = 'api_forge_resource_settings';

    protected $fillable = [
        'resource_class',
        'enabled',
        'rate_limit',
        'allowed_ips',
        'disabled_methods',
        'method_settings',
    ];

    protected $casts = [
        'enabled'          => 'boolean',
        'allowed_ips'      => 'array',
        'disabled_methods' => 'array',
        'method_settings'  => 'array',
    ];

    public static function forResource(string $resourceClass): self
    {
        return static::firstOrCreate(
            ['resource_class' => $resourceClass],
            ['enabled' => true, 'allowed_ips' => [], 'disabled_methods' => [], 'method_settings' => []]
        );
    }

    public function isMethodDisabled(string $method): bool
    {
        return in_array($method, $this->disabled_methods ?? []);
    }

    public function toggleEnabled(): void
    {
        $this->update(['enabled' => ! $this->enabled]);
    }

    public function toggleMethod(string $method): bool
    {
        $disabled = $this->disabled_methods ?? [];

        if (in_array($method, $disabled)) {
            $disabled = array_values(array_filter($disabled, fn ($m) => $m !== $method));
            $this->update(['disabled_methods' => $disabled]);
            return true;
        }

        $disabled[] = $method;
        $this->update(['disabled_methods' => array_values($disabled)]);
        return false;
    }

    public function saveSettings(?int $rateLimit, array $allowedIps): void
    {
        $this->update([
            'rate_limit'  => $rateLimit ?: null,
            'allowed_ips' => $allowedIps,
        ]);
    }

    public function getMethodConfig(string $method): array
    {
        $settings = $this->method_settings ?? [];
        return $settings[$method] ?? ['rate_limit' => null, 'allowed_ips' => []];
    }

    public function saveMethodConfig(string $method, ?int $rateLimit, array $allowedIps): void
    {
        $settings            = $this->method_settings ?? [];
        $settings[$method]   = [
            'rate_limit'  => $rateLimit ?: null,
            'allowed_ips' => $allowedIps,
        ];
        $this->update(['method_settings' => $settings]);
    }
}
