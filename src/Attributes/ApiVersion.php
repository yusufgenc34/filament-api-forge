<?php

namespace YusufGenc34\FilamentApiForge\Attributes;

use Attribute;

/**
 * Restrict a resource to specific API versions.
 *
 * #[ApiVersion('v1', 'v2')] — exposed on v1 and v2
 * No attribute — exposed on every configured version.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ApiVersion
{
    /** @var string[] */
    public readonly array $versions;

    public function __construct(string ...$versions)
    {
        $this->versions = $versions;
    }
}
