<?php

namespace YusufGenc34\FilamentApiForge\Contracts;

/**
 * Implement this interface on any Filament Resource class to
 * automatically expose it as a REST API endpoint.
 *
 * Usage:
 *   class PostResource extends Resource implements HasApi
 *   {
 *       public static function apiConfig(): array
 *       {
 *           return [
 *               'allowed_methods'   => ['index', 'show', 'store', 'update', 'destroy'],
 *               'allowed_filters'   => ['title', 'status', 'created_at'],
 *               'allowed_sorts'     => ['title', 'created_at'],
 *               'allowed_includes'  => ['author', 'tags'],
 *               'allowed_fields'    => ['id', 'title', 'slug', 'status', 'created_at'],
 *               'searchable'        => ['title', 'body'],
 *               'scopes'            => ['read', 'write', 'delete'],
 *           ];
 *       }
 *   }
 */
interface HasApi
{
    /**
     * Return the API configuration for this resource.
     *
     * @return array{
     *     allowed_methods?: string[],
     *     allowed_filters?: string[],
     *     allowed_sorts?: string[],
     *     allowed_includes?: string[],
     *     allowed_fields?: string[],
     *     searchable?: string[],
     *     scopes?: string[],
     * }
     */
    public static function apiConfig(): array;
}
