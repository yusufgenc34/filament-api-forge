<?php

namespace YusufGenc34\FilamentApiForge\Attributes;

use Attribute;

/**
 * Exclude this resource from the OpenAPI spec and API docs entirely.
 *
 * The resource will still respond to API requests — it just won't appear
 * in the documentation or the spec export.
 *
 * Usage:
 *   #[ApiIgnore]
 *   class InternalResource extends Resource implements HasApi { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ApiIgnore {}
