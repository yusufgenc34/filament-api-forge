<?php

namespace YusufGenc34\FilamentApiForge\Concerns;

trait ExtractsApiValidationRules
{
    /**
     * Extract validation rules for a resource.
     *
     * Priority order:
     *   1. apiConfig()['validation_rules'] — use if the developer has explicitly defined them
     *   2. apiConfig()['allowed_fields']   — generate basic rules from the allowed fields
     *   3. Model $fillable                  — last resort fallback
     *
     * $modelClass short-circuits the fallback so callers that already know the
     * model (batch, nested) don't need a resolvable resource class.
     */
    protected function extractValidationRules(
        string $resourceClass,
        bool $isUpdate = false,
        ?array $apiConfig = null,
        ?string $modelClass = null,
    ): array {
        $apiConfig ??= $resourceClass::apiConfig();

        // 1. If the developer has explicitly defined validation_rules, use them directly
        if (! empty($apiConfig['validation_rules'])) {
            $rules = $apiConfig['validation_rules'];

            if ($isUpdate) {
                // Make all fields optional for updates
                return collect($rules)
                    ->mapWithKeys(function ($rule, $field) {
                        $ruleArray = is_array($rule) ? $rule : explode('|', $rule);
                        array_unshift($ruleArray, 'sometimes');
                        return [$field => $ruleArray];
                    })
                    ->toArray();
            }

            return $rules;
        }

        // 2. If allowed_fields is defined, use them as basic rules
        $allowedFields = $apiConfig['allowed_fields'] ?? [];

        if (! empty($allowedFields)) {
            return collect($allowedFields)
                ->mapWithKeys(fn (string $field) => [
                    $field => $isUpdate ? ['sometimes'] : ['nullable'],
                ])
                ->toArray();
        }

        // 3. Fallback from Model $fillable
        $modelClass ??= $resourceClass::getModel();
        $model      = new $modelClass();
        $fillable   = $model->getFillable();

        if (empty($fillable)) {
            return [];
        }

        return collect($fillable)
            ->mapWithKeys(fn (string $field) => [
                $field => $isUpdate ? ['sometimes'] : ['nullable'],
            ])
            ->toArray();
    }
}
