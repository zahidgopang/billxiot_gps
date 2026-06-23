<?php

namespace App\Models\Concerns;

use App\Support\Traccar\TraccarAppFields;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query scopes for Laravel "role" on tc_users (administrator + attributes JSON).
 */
trait ScopesTraccarUserRole
{
    public function scopeAppAdmins(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $q) use ($table) {
            $q->where($q->qualifyColumn('administrator'), 1);

            if (TraccarSchema::hasColumn($table, 'attributes')) {
                $path = '$.' . TraccarAppFields::KEY_ROLE;
                $q->orWhereRaw(
                    'JSON_UNQUOTE(JSON_EXTRACT(' . $q->qualifyColumn('attributes') . ', ?)) = ?',
                    [$path, 'admin']
                );
            }
        });
    }

    /** Non-admin customers (matches {@see getRoleAttribute()}). */
    public function scopeAppCustomers(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $q) use ($table) {
            $q->where(function (Builder $inner) {
                $inner->where($inner->qualifyColumn('administrator'), '!=', 1)
                    ->orWhereNull($inner->qualifyColumn('administrator'));
            });

            if (TraccarSchema::hasColumn($table, 'attributes')) {
                $path = '$.' . TraccarAppFields::KEY_ROLE;
                $q->where(function (Builder $inner) use ($path) {
                    $inner->whereNull($inner->qualifyColumn('attributes'))
                        ->orWhereRaw(
                            'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(' . $inner->qualifyColumn('attributes') . ', ?)), ?) != ?',
                            [$path, 'user', 'admin']
                        );
                });
            }
        });
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

    public function scopeOrderByRecent(Builder $query): Builder
    {
        if (TraccarSchema::hasColumn($query->getModel()->getTable(), 'created_at')) {
            return $query->orderByDesc($query->qualifyColumn('created_at'));
        }

        return $query->orderByDesc($query->qualifyColumn('id'));
    }
}
