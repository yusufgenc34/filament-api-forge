<?php

namespace YusufGenc34\FilamentApiForge\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ApiAction
{
    public function __construct(
        public readonly string $name,
        public readonly string $method = 'POST',
        public readonly string $scope = 'write',
    ) {}
}
