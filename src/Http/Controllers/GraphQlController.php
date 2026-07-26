<?php

namespace YusufGenc34\FilamentApiForge\Http\Controllers;

use GraphQL\Error\DebugFlag;
use GraphQL\GraphQL;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use YusufGenc34\FilamentApiForge\Services\GraphQlSchemaService;

class GraphQlController extends Controller
{
    public function __construct(
        protected GraphQlSchemaService $schemaService,
    ) {}

    /**
     * POST /graphql  { query, variables?, operationName? }
     */
    public function execute(Request $request): JsonResponse
    {
        if (! config('filament-api-forge.graphql.enabled', false)) {
            return response()->json([
                'message' => 'GraphQL support is disabled.',
                'error' => 'graphql_disabled',
            ], 404);
        }

        if (! class_exists(GraphQL::class)) {
            return response()->json([
                'message' => 'GraphQL support requires the webonyx/graphql-php package. Install it with: composer require webonyx/graphql-php',
                'error' => 'graphql_unavailable',
            ], 501);
        }

        $validated = $request->validate([
            'query' => ['required', 'string'],
            'variables' => ['sometimes', 'nullable', 'array'],
            'operationName' => ['sometimes', 'nullable', 'string'],
        ]);

        $result = GraphQL::executeQuery(
            $this->schemaService->schema(),
            $validated['query'],
            null,
            null,
            $validated['variables'] ?? null,
            $validated['operationName'] ?? null,
        );

        $debug = config('app.debug')
            ? DebugFlag::INCLUDE_DEBUG_MESSAGE
            : DebugFlag::NONE;

        // GraphQL convention: HTTP 200 even for resolver errors (errors key in body)
        return response()->json($result->toArray($debug));
    }
}
