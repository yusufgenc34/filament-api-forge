<?php

namespace YusufGenc34\FilamentApiForge\Attributes;

use Attribute;

/**
 * Override the API group tag name for this resource.
 *
 * By default the tag is the resource's plural model label (e.g. "Posts").
 * Use this attribute to rename it in the OpenAPI spec and docs sidebar.
 *
 * Usage:
 *   #[ApiTag('News')]
 *   #[ApiTag(name: 'Breaking News', description: 'Latest breaking news articles')]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ApiTag
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
    ) {}
}
