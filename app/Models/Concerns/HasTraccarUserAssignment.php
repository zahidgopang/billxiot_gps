<?php

namespace App\Models\Concerns;

use App\Models\Traccar\TcUserDevice;
use App\Models\User;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\DB;

trait HasTraccarUserAssignment
{
    /** Primary owner via tc_user_device (Traccar-compatible). */
    public function user(): HasOneThrough
    {
        $keys = TraccarSchema::userDevicePivotKeys();

        return $this->hasOneThrough(
            User::class,
            TcUserDevice::class,
            $keys['device'],
            'id',
            'id',
            $keys['user'],
        );
    }

    /** All users linked to this device (Traccar allows multiple; we usually keep one). */
    public function users(): BelongsToMany
    {
        $keys = TraccarSchema::userDevicePivotKeys();

        return $this->belongsToMany(
            User::class,
            $keys['table'],
            $keys['device'],
            $keys['user'],
        );
    }

    public function resolveTraccarOwnerUserId(): ?int
    {
        if ($this->relationLoaded('user')) {
            return $this->getRelation('user')?->id;
        }

        if (! isset($this->attributes['id'])) {
            return null;
        }

        $keys = TraccarSchema::userDevicePivotKeys();

        return DB::table($keys['table'])
            ->where($keys['device'], $this->attributes['id'])
            ->value($keys['user']);
    }

    /** Devices with no row in tc_user_device. */
    public function scopeWithoutTraccarOwner(Builder $query): Builder
    {
        $keys = TraccarSchema::userDevicePivotKeys();

        return $query->whereNotIn($query->qualifyColumn('id'), function ($sub) use ($keys) {
            $sub->select($keys['device'])->from($keys['table']);
        });
    }

    /** Filter by Laravel app status (maps to tc_devices.disabled). */
    public function scopeWhereAppStatus(Builder $query, string $status): Builder
    {
        if ($status === 'active') {
            return $query->where($query->qualifyColumn('disabled'), 0);
        }

        if (in_array($status, ['inactive', 'blocked'], true)) {
            return $query->where($query->qualifyColumn('disabled'), 1);
        }

        return $query;
    }

    public function scopeOrderByRecent(Builder $query): Builder
    {
        if (TraccarSchema::hasColumn($query->getModel()->getTable(), 'created_at')) {
            return $query->orderByDesc($query->qualifyColumn('created_at'));
        }

        return $query->orderByDesc($query->qualifyColumn('id'));
    }

    public function scopeRegisteredSince(Builder $query, mixed $date): Builder
    {
        if (TraccarSchema::hasColumn($query->getModel()->getTable(), 'created_at')) {
            return $query->where($query->qualifyColumn('created_at'), '>=', $date);
        }

        return $query;
    }

    public function scopeRegisteredBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        if (TraccarSchema::hasColumn($query->getModel()->getTable(), 'created_at')) {
            return $query->whereBetween($query->qualifyColumn('created_at'), [$from, $to]);
        }

        return $query;
    }
}
