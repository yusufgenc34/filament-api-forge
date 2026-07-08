<?php

namespace YusufGenc34\FilamentApiForge\Models;

use Illuminate\Database\Eloquent\Model;

class ApiForgeWebhook extends Model
{
    protected $table = 'api_forge_webhooks';

    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'resource_class',
        'is_active',
        'failure_count',
        'last_triggered_at',
    ];

    protected $casts = [
        'events'            => 'array',
        'is_active'         => 'boolean',
        'failure_count'     => 'integer',
        'last_triggered_at' => 'datetime',
    ];

    protected $hidden = [
        'secret',
    ];

    public function listensTo(string $event, string $resourceClass): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->resource_class !== null && $this->resource_class !== $resourceClass) {
            return false;
        }

        $events = $this->events ?? [];

        return in_array('*', $events) || in_array($event, $events);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
