<?php

namespace YusufGenc34\FilamentApiForge;

use YusufGenc34\FilamentApiForge\Pages\ApiDocumentation;
use YusufGenc34\FilamentApiForge\Pages\DeveloperDashboard;
use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentApiForgePlugin implements Plugin
{
    protected bool $hasApiKeys   = true;
    protected bool $hasDocs      = true;
    protected bool $hasDashboard = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());
        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-api-forge';
    }

    public function apiKeys(bool $condition = true): static
    {
        $this->hasApiKeys = $condition;
        return $this;
    }

    public function docs(bool $condition = true): static
    {
        $this->hasDocs = $condition;
        return $this;
    }

    public function dashboard(bool $condition = true): static
    {
        $this->hasDashboard = $condition;
        return $this;
    }

    public function register(Panel $panel): void
    {
        $resources = [];
        $pages     = [];

        [$resourceClass] = $this->resolveClasses();

        if ($this->hasApiKeys)   $resources[] = $resourceClass;
        if ($this->hasDocs)      $pages[]     = ApiDocumentation::class;
        if ($this->hasDashboard) $pages[]     = DeveloperDashboard::class;

        $panel->resources($resources)->pages($pages);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    private function resolveClasses(): array
    {
        return ['YusufGenc34\\FilamentApiForge\\Filament\\V5\\Resources\\ApiKeyResource'];
    }
}
