<?php

namespace YusufGenc34\FilamentApiForge\Models;

use Illuminate\Database\Eloquent\Model;

class ApiForgeRequestLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'api_forge_request_logs';

    protected $fillable = [
        'token_id',
        'resource_class',
        'action',
        'method',
        'path',
        'status',
        'duration_ms',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'status'      => 'integer',
        'duration_ms' => 'integer',
        'created_at'  => 'datetime',
    ];

    public function token()
    {
        return $this->belongsTo(ApiForgeToken::class, 'token_id');
    }
}
