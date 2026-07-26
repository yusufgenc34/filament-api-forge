<?php

namespace YusufGenc34\FilamentApiForge\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use YusufGenc34\FilamentApiForge\Concerns\ExecutesApiHooks;
use YusufGenc34\FilamentApiForge\Concerns\ExtractsApiValidationRules;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceCreating;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleted;
use YusufGenc34\FilamentApiForge\Events\ApiResourceDeleting;
use YusufGenc34\FilamentApiForge\Events\ApiResourceForceDeleted;
use YusufGenc34\FilamentApiForge\Events\ApiResourceForceDeleting;
use YusufGenc34\FilamentApiForge\Events\ApiResourceRestored;
use YusufGenc34\FilamentApiForge\Events\ApiResourceRestoring;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdated;
use YusufGenc34\FilamentApiForge\Events\ApiResourceUpdating;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;

class ApiBatchController extends Controller
{
    use ExecutesApiHooks;
    use ExtractsApiValidationRules;

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
        $resourceClass = $resource['resource_class'];
        $apiConfig = $resource['api_config'];
        $batchConfig = $apiConfig['batch'] ?? [];
        $maxSize = $batchConfig['max_size'] ?? config('filament-api-forge.batch.max_size', 100);

        $allowedOps = $batchConfig['allowed_operations']
            ?? config('filament-api-forge.batch.allowed_operations', ['create', 'update', 'delete']);

        $request->validate([
            'create' => 'sometimes|array|max:'.$maxSize,
            'create.*' => 'array',
            'update' => 'sometimes|array|max:'.$maxSize,
            'update.*.id' => 'required|int',
            'delete' => 'sometimes|array|max:'.$maxSize,
            'delete.*' => 'int',
            'restore' => 'sometimes|array|max:'.$maxSize,
            'restore.*' => 'int',
            'forceDelete' => 'sometimes|array|max:'.$maxSize,
            'forceDelete.*' => 'int',
        ]);

        // Read rows from the raw input: validated() strips row attributes
        // that have no explicit element rules (excludeUnvalidatedArrayKeys),
        // which would silently drop every update payload except 'id'.
        $validated = [
            'create' => $request->input('create', []),
            'update' => $request->input('update', []),
            'delete' => $request->input('delete', []),
            'restore' => $request->input('restore', []),
            'forceDelete' => $request->input('forceDelete', []),
        ];

        $storeRules = $this->extractValidationRules($resourceClass, false, $apiConfig, $modelClass);
        $updateRules = $this->extractValidationRules($resourceClass, true, $apiConfig, $modelClass);

        $eventsEnabled = $this->eventsEnabled();

        $created = $updated = $deleted = $restored = $forceDeleted = [];
        $failed = [];

        DB::transaction(function () use (
            $validated, $allowedOps, $modelClass, $resourceClass,
            $storeRules, $updateRules, $eventsEnabled,
            &$created, &$updated, &$deleted, &$restored, &$forceDeleted, &$failed
        ) {
            // Create
            if (in_array('create', $allowedOps) && ! empty($validated['create'])) {
                foreach ($validated['create'] as $i => $row) {
                    try {
                        $validator = Validator::make($row, $storeRules);

                        if ($validator->fails()) {
                            $failed[] = ['operation' => 'create', 'index' => $i, 'reason' => 'Validation failed.', 'errors' => $validator->errors()->toArray()];

                            continue;
                        }

                        $data = $validator->validated();
                        $data = $this->executeBeforeHooks($resourceClass, 'beforeCreate', $data);

                        if ($eventsEnabled) {
                            ApiResourceCreating::dispatch($resourceClass, $data);
                        }

                        $record = new $modelClass;
                        $record->fill($data);
                        $record->save();

                        if ($eventsEnabled) {
                            ApiResourceCreated::dispatch($resourceClass, $record, $data);
                        }

                        $this->executeAfterHooks($resourceClass, 'afterCreate', $record, $data);

                        $created[] = $record->getKey();
                    } catch (\Throwable $e) {
                        $failed[] = ['operation' => 'create', 'index' => $i, 'reason' => $e->getMessage()];
                    }
                }
            }

            // Update
            if (in_array('update', $allowedOps) && ! empty($validated['update'])) {
                foreach ($validated['update'] as $i => $row) {
                    $id = $row['id'];
                    unset($row['id']);

                    try {
                        $record = $modelClass::find($id);

                        if (! $record) {
                            $failed[] = ['operation' => 'update', 'index' => $i, 'reason' => 'Record not found.'];

                            continue;
                        }

                        $validator = Validator::make($row, $updateRules);

                        if ($validator->fails()) {
                            $failed[] = ['operation' => 'update', 'index' => $i, 'reason' => 'Validation failed.', 'errors' => $validator->errors()->toArray()];

                            continue;
                        }

                        $data = $validator->validated();
                        $data = $this->executeBeforeHooks($resourceClass, 'beforeUpdate', $record, $data);

                        if ($eventsEnabled) {
                            ApiResourceUpdating::dispatch($resourceClass, $record, $data);
                        }

                        $record->fill($data);
                        $record->save();

                        if ($eventsEnabled) {
                            ApiResourceUpdated::dispatch($resourceClass, $record, $data);
                        }

                        $this->executeAfterHooks($resourceClass, 'afterUpdate', $record, $data);

                        $updated[] = (int) $id;
                    } catch (\Throwable $e) {
                        $failed[] = ['operation' => 'update', 'index' => $i, 'reason' => $e->getMessage()];
                    }
                }
            }

            // Delete
            if (in_array('delete', $allowedOps) && ! empty($validated['delete'])) {
                foreach ($validated['delete'] as $i => $id) {
                    try {
                        $record = $modelClass::find($id);

                        if (! $record) {
                            $failed[] = ['operation' => 'delete', 'index' => $i, 'reason' => 'Record not found.'];

                            continue;
                        }

                        $this->executeVoidHooks($resourceClass, 'beforeDelete', $record);

                        if ($eventsEnabled) {
                            ApiResourceDeleting::dispatch($resourceClass, $record);
                        }

                        $record->delete();

                        if ($eventsEnabled) {
                            ApiResourceDeleted::dispatch($resourceClass, $record);
                        }

                        $this->executeVoidHooks($resourceClass, 'afterDelete', $record);

                        $deleted[] = (int) $id;
                    } catch (\Throwable $e) {
                        $failed[] = ['operation' => 'delete', 'index' => $i, 'reason' => $e->getMessage()];
                    }
                }
            }

            // Restore
            if (in_array('restore', $allowedOps) && ! empty($validated['restore'])) {
                foreach ($validated['restore'] as $i => $id) {
                    try {
                        $record = $modelClass::onlyTrashed()->find($id);

                        if (! $record) {
                            $failed[] = ['operation' => 'restore', 'index' => $i, 'reason' => 'Record not found in trash.'];

                            continue;
                        }

                        $this->executeVoidHooks($resourceClass, 'beforeRestore', $record);

                        if ($eventsEnabled) {
                            ApiResourceRestoring::dispatch($resourceClass, $record);
                        }

                        $record->restore();

                        if ($eventsEnabled) {
                            ApiResourceRestored::dispatch($resourceClass, $record);
                        }

                        $this->executeVoidHooks($resourceClass, 'afterRestore', $record);

                        $restored[] = (int) $id;
                    } catch (\Throwable $e) {
                        $failed[] = ['operation' => 'restore', 'index' => $i, 'reason' => $e->getMessage()];
                    }
                }
            }

            // Force Delete
            if (in_array('forceDelete', $allowedOps) && ! empty($validated['forceDelete'])) {
                foreach ($validated['forceDelete'] as $i => $id) {
                    try {
                        $record = $modelClass::withTrashed()->find($id);

                        if (! $record) {
                            $failed[] = ['operation' => 'forceDelete', 'index' => $i, 'reason' => 'Record not found.'];

                            continue;
                        }

                        $this->executeVoidHooks($resourceClass, 'beforeForceDelete', $record);

                        if ($eventsEnabled) {
                            ApiResourceForceDeleting::dispatch($resourceClass, $record);
                        }

                        $record->forceDelete();

                        if ($eventsEnabled) {
                            ApiResourceForceDeleted::dispatch($resourceClass, $record);
                        }

                        $this->executeVoidHooks($resourceClass, 'afterForceDelete', $record);

                        $forceDeleted[] = (int) $id;
                    } catch (\Throwable $e) {
                        $failed[] = ['operation' => 'forceDelete', 'index' => $i, 'reason' => $e->getMessage()];
                    }
                }
            }
        });

        return response()->json([
            'message' => 'Batch operation completed.',
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'restored' => $restored,
            'forceDeleted' => $forceDeleted,
            'failed' => $failed,
        ]);
    }
}
