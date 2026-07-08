<?php

namespace YusufGenc34\FilamentApiForge\Concerns;

use YusufGenc34\FilamentApiForge\Http\Resources\ApiForgeJsonResource;
use Illuminate\Http\JsonResponse;

trait ResolvesApiResource
{
    /**
     * HTTP method → required scope mapping.
     */
    protected const SCOPE_MAP = [
        'index'       => 'read',
        'show'        => 'read',
        'export'      => 'read',
        'store'       => 'write',
        'update'      => 'write',
        'restore'     => 'write',
        'destroy'     => 'delete',
        'forceDelete' => 'delete',
    ];

    /**
     * Resolve a resource from the discovery service and validate method + scope access.
     */
    protected function resolveResource(string $panelId, string $resourceSlug, string $method): array|JsonResponse
    {
        $resource = $this->discoveryService->findResource($panelId, $resourceSlug);

        if (! $resource) {
            return response()->json([
                'message' => 'Resource not found.',
                'error'   => 'not_found',
            ], 404);
        }

        if (! $this->discoveryService->isMethodAllowed($resource, $method)) {
            return response()->json([
                'message' => "Method '{$method}' is not allowed for this resource.",
                'error'   => 'method_not_allowed',
            ], 405);
        }

        // Scope enforcement: does the token have the required scope for this method?
        // apiConfig()['scope_map'] lets a resource require custom scopes per method.
        // With auth disabled there are no tokens, so scope checks cannot apply.
        $requiredScope = $resource['api_config']['scope_map'][$method]
            ?? self::SCOPE_MAP[$method]
            ?? null;

        if ($requiredScope && config('filament-api-forge.auth.enabled', true)) {
            /** @var \YusufGenc34\FilamentApiForge\Models\ApiForgeToken|null $token */
            $token = request()->attributes->get('api_forge_token');

            if (! $token || ! $token->hasScope($requiredScope)) {
                return response()->json([
                    'message' => "This token does not have the required '{$requiredScope}' scope for this operation.",
                    'error'   => 'insufficient_scope',
                    'required_scope' => $requiredScope,
                ], 403);
            }

            // Resource-level access check: verify if the token is restricted to specific resources
            $allowedResources = $token->allowed_resources;

            if (! empty($allowedResources) && ! in_array($resourceSlug, $allowedResources)) {
                return response()->json([
                    'message' => "This token is not authorized to access the '{$resourceSlug}' resource.",
                    'error'   => 'resource_not_allowed',
                ], 403);
            }
        }

        // Response transformation layer: apply the resource's apiTransform()
        // to every serialized record of this request (single + collection).
        $resourceClass = $resource['resource_class'];
        ApiForgeJsonResource::withTransformer(
            class_exists($resourceClass) && method_exists($resourceClass, 'apiTransform')
                ? [$resourceClass, 'apiTransform']
                : null
        );

        return $resource;
    }
}
