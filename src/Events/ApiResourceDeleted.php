<?php

namespace YusufGenc34\FilamentApiForge\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class ApiResourceDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $resourceClass,
        public readonly Model $record,
    ) {}
}
