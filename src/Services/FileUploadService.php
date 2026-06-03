<?php

namespace YusufGenc34\FilamentApiForge\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    public function isSpatieMediaLibraryAvailable(): bool
    {
        return class_exists(\Spatie\MediaLibrary\HasMedia::class);
    }

    /**
     * @param  array<string, array{disk?: string, rules?: string|array, multiple?: bool, collection?: string}> $uploadConfig
     * @return array<string, array{uuids?: string[], urls: string[]}>
     */
    public function handleUploads(Model $record, array $uploadConfig, Request $request): array
    {
        $results = [];

        foreach ($uploadConfig as $field => $config) {
            if (! $request->hasFile($field)) {
                continue;
            }

            $files = $request->file($field);
            $files = is_array($files) ? $files : [$files];
            $isMultiple = $config['multiple'] ?? false;

            if (! $isMultiple && count($files) > 1) {
                $files = [reset($files)];
            }

            $urls  = [];
            $uuids = [];

            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                if ($this->isSpatieMediaLibraryAvailable() &&
                    in_array(\Spatie\MediaLibrary\InteractsWithMedia::class, class_uses($record))
                ) {
                    $collection = $config['collection'] ?? $field;
                    $media = $record->addMedia($file)->toMediaCollection($collection);
                    $uuids[] = $media->uuid;
                    $urls[]  = $media->getUrl();
                } else {
                    $disk      = $config['disk'] ?? config('filament-api-forge.uploads.default_disk', 'public');
                    $directory = $config['directory'] ?? $field;
                    $path      = $file->store($directory, $disk);
                    $urls[]    = Storage::disk($disk)->url($path);

                    // Persist the file path on the model so Filament can display it
                    if (! $isMultiple && $record->getConnection()->getSchemaBuilder()->hasColumn($record->getTable(), $field)) {
                        $record->setAttribute($field, $path);
                    }
                }
            }

            // Save updated model attributes for file fields
            if ($record->isDirty()) {
                $record->save();
            }

            $results[$field] = $isMultiple
                ? ['urls' => $urls, 'uuids' => $uuids]
                : ['url' => $urls[0] ?? null, 'uuid' => $uuids[0] ?? null];
        }

        return $results;
    }

    /**
     * @param  array<string, array{disk?: string, rules?: string|array, multiple?: bool, collection?: string}> $uploadConfig
     */
    public function getValidationRules(array $uploadConfig): array
    {
        $rules = [];

        foreach ($uploadConfig as $field => $config) {
            $isMultiple = $config['multiple'] ?? false;
            $fieldRules = $config['rules'] ?? [];

            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            if ($isMultiple) {
                $rules["{$field}"]   = ['array'];
                $rules["{$field}.*"] = array_merge(['file'], $fieldRules);
            } else {
                $rules[$field] = array_merge(['file'], $fieldRules);
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, array{disk?: string, rules?: string|array, multiple?: bool, collection?: string}> $uploadConfig
     */
    public function getFileUrls(Model $record, array $uploadConfig): array
    {
        $urls = [];

        if ($this->isSpatieMediaLibraryAvailable() &&
            in_array(\Spatie\MediaLibrary\InteractsWithMedia::class, class_uses($record))
        ) {
            foreach ($uploadConfig as $field => $config) {
                $collection = $config['collection'] ?? $field;
                $media = $record->getMedia($collection);

                if ($media->isEmpty()) {
                    continue;
                }

                $isMultiple = $config['multiple'] ?? false;
                if ($isMultiple) {
                    $urls[$field] = $media->map(fn ($m) => $m->getUrl())->toArray();
                } else {
                    $urls[$field] = $media->first()->getUrl();
                }
            }
        }

        return $urls;
    }
}
