<?php

namespace YusufGenc34\FilamentApiForge\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ApiResourceCreating
{
    use Dispatchable;

    public function __construct(
        public readonly string $resourceClass,
        public array $data,
    ) {}
}
