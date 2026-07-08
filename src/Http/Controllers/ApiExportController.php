<?php

namespace YusufGenc34\FilamentApiForge\Http\Controllers;

use YusufGenc34\FilamentApiForge\Concerns\BuildsResourceQuery;
use YusufGenc34\FilamentApiForge\Concerns\ResolvesApiResource;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiExportController extends Controller
{
    use BuildsResourceQuery;
    use ResolvesApiResource;

    public function __construct(
        protected ResourceDiscoveryService $discoveryService,
    ) {}

    /**
     * GET /{panelId}/{resourceSlug}/export?format=csv|json
     *
     * Export the (filtered, sorted, searched) result set without pagination.
     * Requires 'export' in allowed_methods.
     */
    public function export(Request $request, string $panelId, string $resourceSlug): StreamedResponse|JsonResponse
    {
        if (! config('filament-api-forge.export.enabled', true)) {
            return response()->json([
                'message' => 'Export is disabled.',
                'error'   => 'export_disabled',
            ], 403);
        }

        $resource = $this->resolveResource($panelId, $resourceSlug, 'export');

        if ($resource instanceof JsonResponse) {
            return $resource;
        }

        $format  = strtolower($request->query('format', 'csv'));
        $allowed = config('filament-api-forge.export.formats', ['csv', 'json']);

        if (! in_array($format, $allowed)) {
            return response()->json([
                'message' => "Format '{$format}' is not supported.",
                'error'   => 'unsupported_format',
                'supported_formats' => array_values($allowed),
            ], 422);
        }

        $maxRows = (int) config('filament-api-forge.export.max_rows', 10000);
        $columns = $this->exportColumns($resource);

        $query = $this->buildListQuery($resource, $request)->limit($maxRows);

        $filename = $resource['slug'] . '-export-' . now()->format('Ymd_His') . '.' . $format;

        if ($format === 'json') {
            $rows = $query->get()->map(
                fn ($record) => collect($record->toArray())->only($columns)->all()
            );

            return response()->json([
                'data' => $rows,
                'meta' => [
                    'total'    => $rows->count(),
                    'max_rows' => $maxRows,
                    'resource' => $resource['plural_label'],
                ],
            ]);
        }

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            foreach ($query->cursor() as $record) {
                $row = [];
                foreach ($columns as $column) {
                    $value = $record->getAttribute($column);
                    $row[] = is_scalar($value) || $value === null
                        ? $value
                        : json_encode($value);
                }
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Columns included in the export: allowed_fields if declared,
     * otherwise id + fillable (+ timestamps).
     */
    protected function exportColumns(array $resource): array
    {
        $allowedFields = $resource['api_config']['allowed_fields'] ?? [];

        if (! empty($allowedFields)) {
            return $allowedFields;
        }

        $model = new ($resource['model_class'])();
        $columns = array_merge(['id'], $model->getFillable());

        if ($model->usesTimestamps()) {
            $columns[] = 'created_at';
            $columns[] = 'updated_at';
        }

        return array_values(array_unique($columns));
    }
}
