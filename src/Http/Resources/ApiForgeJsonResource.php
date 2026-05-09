<?php

namespace YusufGenc34\FilamentApiForge\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiForgeJsonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Returns all model attributes plus loaded relations.
     * Individual field filtering is handled by Spatie Query Builder.
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        // Add metadata about loaded relations
        $relations = [];
        foreach ($this->resource->getRelations() as $key => $value) {
            $relations[] = $key;
        }

        if (! empty($relations)) {
            $data['_loaded_relations'] = $relations;
        }

        return $data;
    }

    /**
     * Additional metadata attached to the resource response.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'api_version' => config('filament-api-forge.api_version', 'v1'),
                'timestamp'   => now()->toIso8601String(),
            ],
        ];
    }
}
