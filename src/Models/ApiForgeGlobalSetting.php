<?php

namespace YusufGenc34\FilamentApiForge\Models;

use Illuminate\Database\Eloquent\Model;

class ApiForgeGlobalSetting extends Model
{
    protected $table = 'api_forge_global_settings';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $record = static::where('key', $key)->first();

        if (! $record) {
            return $default;
        }

        $value = $record->value;

        if ($value === 'true')  return true;
        if ($value === 'false') return false;
        if ($value === 'null')  return null;

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        if (is_bool($value)) $value = $value ? 'true' : 'false';
        if (is_null($value))  $value = 'null';

        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}
