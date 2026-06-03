<?php

namespace YusufGenc34\FilamentApiForge\Tests\Stubs;

use YusufGenc34\FilamentApiForge\Attributes\ApiOperations;

#[ApiOperations(
    index: ['summary' => 'List items', 'description' => 'Returns paginated results.'],
    store: 'Create an item',
)]
class OperationsStub {}
