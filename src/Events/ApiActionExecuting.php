<?php

namespace YusufGenc34\FilamentApiForge\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class ApiActionExecuting
{
    use Dispatchable;

    public function __construct(
        public readonly string $resourceClass,
        public readonly string $actionName,
        public readonly Model $record,
        public readonly array $data,
    ) {}
}
