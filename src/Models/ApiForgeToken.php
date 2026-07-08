<?php

namespace YusufGenc34\FilamentApiForge\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string      $id
 * @property int         $user_id
 * @property string      $name
 * @property string      $token_hash     SHA-256 of the plain-text token (never exposed)
 * @property string      $token_prefix   First 16 chars of the plain-text token (for display)
 * @property array       $scopes
 * @property array|null  $allowed_resources
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_used_at
 * @property int         $request_count
 * @property bool        $is_active
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 */
class ApiForgeToken extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'api_forge_tokens';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'token_hash',
        'token_prefix',
        'refresh_token_hash',
        'scopes',
        'allowed_resources',
        'expires_at',
        'last_used_at',
        'expiry_notified_at',
        'request_count',
        'is_active',
    ];

    protected $casts = [
        'scopes'             => 'array',
        'allowed_resources'  => 'array',
        'expires_at'         => 'datetime',
        'last_used_at'       => 'datetime',
        'expiry_notified_at' => 'datetime',
        'is_active'          => 'boolean',
        'request_count'      => 'integer',
    ];

    protected $hidden = [
        'token_hash',
        'refresh_token_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    public static function findByToken(string $plainToken): ?self
    {
        return static::where('token_hash', hash('sha256', $plainToken))->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    public function recordUsage(): void
    {
        $this->increment('request_count');
        $this->update(['last_used_at' => now()]);
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];
        return in_array('*', $scopes) || in_array($scope, $scopes);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
