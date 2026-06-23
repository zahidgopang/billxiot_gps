<?php

namespace App\Services\Traccar;

use App\Models\TraccarEntityMap;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy bridge (pre-unify). After unify migration, IDs are native tc_* ids (pass-through).
 */
class TraccarIdMap
{
    public function usesPassThrough(): bool
    {
        return config('traccar.unified_ids', true)
            || ! Schema::hasTable('traccar_entity_map');
    }

    public function get(string $entityType, int $laravelId): ?int
    {
        if ($this->usesPassThrough()) {
            return $laravelId;
        }

        return TraccarEntityMap::query()
            ->where('entity_type', $entityType)
            ->where('laravel_id', $laravelId)
            ->value('traccar_id');
    }

    public function put(string $entityType, int $laravelId, int $traccarId): void
    {
        if ($this->usesPassThrough()) {
            return;
        }

        TraccarEntityMap::query()->updateOrCreate(
            [
                'entity_type' => $entityType,
                'laravel_id' => $laravelId,
            ],
            ['traccar_id' => $traccarId]
        );
    }

    public function forget(string $entityType, int $laravelId): void
    {
        if ($this->usesPassThrough()) {
            return;
        }

        TraccarEntityMap::query()
            ->where('entity_type', $entityType)
            ->where('laravel_id', $laravelId)
            ->delete();
    }

    public function laravelId(string $entityType, int $traccarId): ?int
    {
        if ($this->usesPassThrough()) {
            return $traccarId;
        }

        return TraccarEntityMap::query()
            ->where('entity_type', $entityType)
            ->where('traccar_id', $traccarId)
            ->value('laravel_id');
    }
}
