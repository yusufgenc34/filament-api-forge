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

    /**
     * Record-level action: {resource}/{id}/actions/{name}
     */
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

        $record = $modelClass::find($recordId);

        if (! $record) {
            return response()->json([
                'message' => 'Record not found.',
                'error'   => 'not_found',
            ], 404);
        }

        return $this->runAction($request, $resource['resource_class'], $actionName, $record);
    }

    /**
     * Collection-level action: {resource}/actions/{name}
     * Only matches actions declared with record: false.
     */
    public function executeCollection(Request $request, string $panelId, string $resourceSlug, string $actionName): JsonResponse
    {
        $resource = $this->discoveryService->findResource($panelId, $resourceSlug);

        if (! $resource) {
            return response()->json([
                'message' => 'Resource not found.',
                'error'   => 'not_found',
            ], 404);
        }

        return $this->runAction($request, $resource['resource_class'], $actionName, null);
    }

    protected function runAction(Request $request, string $resourceClass, string $actionName, mixed $record): JsonResponse
    {
        $expectsRecord = $record !== null;

        $actionMethod = $this->findActionMethod($resourceClass, $actionName, $expectsRecord);
        $apiAction = $actionMethod ? $this->getApiActionAttribute($actionMethod, $actionName) : null;

        if (! $actionMethod || ! $apiAction || $apiAction->record !== $expectsRecord) {
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

        $eventsEnabled = config('filament-api-forge.events.enabled', true)
            && config('filament-api-forge.events.dispatch_events', true);

        // Dispatch executing event
        if ($eventsEnabled) {
            ApiActionExecuting::dispatch($resourceClass, $actionName, $record, $data);
        }

        $result = $expectsRecord
            ? $actionMethod->invoke(null, $record, $data)
            : $actionMethod->invoke(null, $data);

        // Dispatch executed event
        if ($eventsEnabled) {
            ApiActionExecuted::dispatch($resourceClass, $actionName, $record, $result);
        }

        return response()->json([
            'message' => "Action '{$actionName}' executed successfully.",
            'action'  => $actionName,
            'result'  => $result,
        ]);
    }

    protected function findActionMethod(string $resourceClass, string $actionName, bool $expectsRecord = true): ?ReflectionMethod
    {
        $ref = new \ReflectionClass($resourceClass);

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC) as $method) {
            $apiAction = $this->getApiActionAttribute($method, $actionName);

            if ($apiAction && $apiAction->name === $actionName && $apiAction->record === $expectsRecord) {
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
