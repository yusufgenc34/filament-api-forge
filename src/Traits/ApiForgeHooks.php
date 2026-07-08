<?php

namespace YusufGenc34\FilamentApiForge\Traits;

use Illuminate\Database\Eloquent\Model;

trait ApiForgeHooks
{
    protected static bool $withoutHooksForNextCall = false;

    public static function withoutHooks(): void
    {
        static::$withoutHooksForNextCall = true;
    }

    public static function shouldSkipHooks(): bool
    {
        $result = static::$withoutHooksForNextCall;
        static::$withoutHooksForNextCall = false;
        return $result;
    }

    public static function beforeCreate(array $data): array
    {
        return $data;
    }

    public static function afterCreate(Model $record, array $data): void
    {
        //
    }

    public static function beforeUpdate(Model $record, array $data): array
    {
        return $data;
    }

    public static function afterUpdate(Model $record, array $data): void
    {
        //
    }

    public static function beforeDelete(Model $record): void
    {
        //
    }

    public static function afterDelete(Model $record): void
    {
        //
    }

    public static function beforeRestore(Model $record): void
    {
        //
    }

    public static function afterRestore(Model $record): void
    {
        //
    }

    public static function beforeForceDelete(Model $record): void
    {
        //
    }

    public static function afterForceDelete(Model $record): void
    {
        //
    }
}
