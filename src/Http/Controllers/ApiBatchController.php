<?php

namespace YusufGenc34\FilamentApiForge\Http\Controllers;

use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ApiBatchController extends Controller
{
    public function __construct(
        protected ResourceDiscoveryService $discoveryService,
    ) {}

    public function batch(Request $request, string $panelId, string $resourceSlug): JsonResponse
    {
        $resource = $this->discoveryService->findResource($panelId, $resourceSlug);

        if (! $resource) {
            return response()->json(['message' => 'Resource not found.', 'error' => 'not_found'], 404);
        }

        $modelClass = $resource['model_class'];
        $table = (new $modelClass())->getTable();
        $fillable = (new $modelClass())->getFillable();
        $batchConfig = $resource['api_config']['batch'] ?? [];
        $maxSize = $batchConfig['max_size'] ?? config('filament-api-forge.batch.max_size', 100);

        $allowedOps = $batchConfig['allowed_operations']
            ?? config('filament-api-forge.batch.allowed_operations', ['create', 'update', 'delete']);

        $validated = $request->validate([
            'create' => 'sometimes|array|max:' . $maxSize,
            'create.*' => 'array',
            'update' => 'sometimes|array|max:' . $maxSize,
            'update.*.id' => 'required|int',
            'delete' => 'sometimes|array|max:' . $maxSize,
            'delete.*' => 'int',
        ]);

        $created = $updated = $deleted = [];
        $failed  = [];
        $now     = now()->toDateTimeString();

        DB::transaction(function () use ($validated, $table, $fillable, $allowedOps, $now, $modelClass, &$created, &$updated, &$deleted, &$failed) {
            // Create
            if (in_array('create', $allowedOps) && ! empty($validated['create'])) {
                foreach ($validated['create'] as $i => $row) {
                    $filtered = array_intersect_key($row, array_flip($fillable));
                    if (empty($filtered)) { $failed[] = ['index' => $i, 'reason' => 'No fillable fields.']; continue; }
                    $filtered['created_at'] = $now;
                    $filtered['updated_at'] = $now;
                    try { $created[] = DB::table($table)->insertGetId($filtered); }
                    catch (\Throwable $e) { $failed[] = ['index' => $i, 'reason' => $e->getMessage()]; }
                }
            }

            // Update
            if (in_array('update', $allowedOps) && ! empty($validated['update'])) {
                foreach ($validated['update'] as $i => $row) {
                    $id = $row['id']; unset($row['id']);
                    $filtered = array_intersect_key($row, array_flip($fillable));
                    if (empty($filtered)) { $failed[] = ['index' => $i, 'reason' => 'No fillable fields.']; continue; }
                    $filtered['updated_at'] = $now;
                    try {
                        $record = $modelClass::find($id);
                        if ($record) {
                            $record->fill($filtered)->save();
                            // Verify the save actually persisted
                            $record->refresh();
                            $updated[] = (int) $id;
                        } else {
                            $failed[] = ['index' => $i, 'reason' => 'Record not found.'];
                        }
                    } catch (\Throwable $e) { $failed[] = ['index' => $i, 'reason' => $e->getMessage()]; }
                }
            }

            // Delete
            if (in_array('delete', $allowedOps) && ! empty($validated['delete'])) {
                $ids = $validated['delete'];
                try {
                    $affected = DB::table($table)->whereIn('id', $ids)->delete();
                    $deleted = $ids;
                } catch (\Throwable $e) {
                    $failed[] = ['reason' => $e->getMessage()];
                }
            }
        });

        return response()->json([
            'message' => 'Batch operation completed.',
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'failed'  => $failed,
        ]);
    }
}
