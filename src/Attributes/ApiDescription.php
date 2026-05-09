<?php

namespace YusufGenc34\FilamentApiForge\Attributes;

use Attribute;

/**
 * Set a description for this resource in the OpenAPI spec.
 *
 * The description appears in the API documentation under the resource's tag.
 * Supports Markdown.
 *
 * Usage:
 *   #[ApiDescription('Manage blog posts and articles. Supports filtering and full-text search.')]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ApiDescription
{
    public function __construct(
        public readonly string $description,
    ) {}
}
