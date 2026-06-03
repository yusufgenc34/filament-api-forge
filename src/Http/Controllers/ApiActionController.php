<?php

namespace YusufGenc34\FilamentApiForge\Http\Controllers;

use YusufGenc34\FilamentApiForge\Attributes\ApiAction;
use YusufGenc34\FilamentApiForge\Events\ApiActionExecuting;
use YusufGenc34\FilamentApiForge\Events\ApiActionExecuted;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ReflectionMethod;

class ApiActionController extends Controller
{
    public function __construct(
        protected ResourceDiscoveryService $discoveryService,
    ) {}

    public function execute(Request $request, string $panelId, string $resourceSlug, string $recordId, string $actionName): JsonResponse
    {
        $resource = $this->discoveryService->findResource($panelId, $resourceSlug);

        if (! $resource) {
            return response()->json([
                'message' => 'Resource not found.',
                'error'   => 'not_found',
            ], 404);
        }

        $modelClass = $resource['model_class'];
        $resourceClass = $resource['resource_class'];

        $record = $modelClass::find($recordId);

        if (! $record) {
            return response()->json([
                'message' => 'Record not found.',
                'error'   => 'not_found',
            ], 404);
        }

        $actionMethod = $this->findActionMethod($resourceClass, $actionName);

        if (! $actionMethod) {
            return response()->json([
                'message' => "Action '{$actionName}' is not defined on this resource.",
                'error'   => 'action_not_found',
            ], 404);
        }

        $apiAction = $this->getApiActionAttribute($actionMethod, $actionName);

        if (! $apiAction) {
            return response()->json([
                'message' => "Action '{$actionName}' is not defined on this resource.",
                'error'   => 'action_not_found',
            ], 404);
        }

        // Validate HTTP method
        if (strtoupper($request->method()) !== strtoupper($apiAction->method)) {
            return response()->json([
                'message' => "Action '{$actionName}' requires HTTP {$apiAction->method}.",
                'error'   => 'method_not_allowed',
            ], 405);
        }

        // Validate token scope
        $token = $request->attributes->get('api_forge_token');

        if ($token && ! $token->hasScope($apiAction->scope)) {
            return response()->json([
                'message' => "This token does not have the required '{$apiAction->scope}' scope for action '{$actionName}'.",
                'error'   => 'insufficient_scope',
                'required_scope' => $apiAction->scope,
            ], 403);
        }

        $data = $request->all();

        // Dispatch executing event
        if (config('filament-api-forge.events.enabled', true)) {
            ApiActionExecuting::dispatch($resourceClass, $actionName, $record, $data);
        }

        $result = $actionMethod->invoke(null, $record, $data);

        // Dispatch executed event
        if (config('filament-api-forge.events.enabled', true)) {
            ApiActionExecuted::dispatch($resourceClass, $actionName, $record, $result);
        }

        return response()->json([
            'message' => "Action '{$actionName}' executed successfully.",
            'action'  => $actionName,
            'result'  => $result,
        ]);
    }

    protected function findActionMethod(string $resourceClass, string $actionName): ?ReflectionMethod
    {
        $ref = new \ReflectionClass($resourceClass);

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC) as $method) {
            $apiAction = $this->getApiActionAttribute($method, $actionName);

            if ($apiAction && $apiAction->name === $actionName) {
                return $method;
            }
        }

        return null;
    }

    protected function getApiActionAttribute(ReflectionMethod $method, string $actionName): ?ApiAction
    {
        $attrs = $method->getAttributes(ApiAction::class);

        foreach ($attrs as $attr) {
            $instance = $attr->newInstance();

            if ($instance->name === $actionName) {
                return $instance;
            }
        }

        return null;
    }
}
