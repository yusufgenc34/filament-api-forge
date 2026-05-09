<?php

namespace YusufGenc34\FilamentApiForge\Attributes;

use Attribute;

/**
 * Set human-readable summaries for each CRUD operation.
 *
 * Every named parameter maps to one operation. You can pass either a plain
 * string (used as the summary) or an array with 'summary' and 'description'.
 *
 * Usage (simple):
 *   #[ApiOperations(
 *       index:   'List all news articles',
 *       store:   'Publish a new article',
 *       show:    'Get a single article by ID',
 *       update:  'Update an existing article',
 *       destroy: 'Permanently delete an article',
 *   )]
 *
 * Usage (with description):
 *   #[ApiOperations(
 *       index: ['summary' => 'List articles', 'description' => 'Returns paginated results. Use filter[] and sort[] params.'],
 *       store: ['summary' => 'Create article', 'description' => 'Requires write scope.'],
 *   )]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ApiOperations
{
    public function __construct(
        public readonly string|array|null $index   = null,
        public readonly string|array|null $show    = null,
        public readonly string|array|null $store   = null,
        public readonly string|array|null $update  = null,
        public readonly string|array|null $destroy = null,
    ) {}

    /**
     * Get the summary string for a given operation name.
     */
    public function getSummary(string $operation): ?string
    {
        $value = match ($operation) {
            'index'   => $this->index,
            'show'    => $this->show,
            'store'   => $this->store,
            'update'  => $this->update,
            'destroy' => $this->destroy,
            default   => null,
        };

        if (is_string($value)) return $value;
        if (is_array($value))  return $value['summary'] ?? null;
        return null;
    }

    /**
     * Get the longer description for a given operation name.
     */
    public function getDescription(string $operation): ?string
    {
        $value = match ($operation) {
            'index'   => $this->index,
            'show'    => $this->show,
            'store'   => $this->store,
            'update'  => $this->update,
            'destroy' => $this->destroy,
            default   => null,
        };

        if (is_array($value)) return $value['description'] ?? null;
        return null;
    }
}
