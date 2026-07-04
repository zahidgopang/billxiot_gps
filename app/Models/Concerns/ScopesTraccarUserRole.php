<?php

namespace App\Models\Concerns;

use App\Enums\AppRole;
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

    /** Exclude legacy Traccar administrators (super admins). */
    public function scopeExcludeSuperAdmins(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where($q->qualifyColumn('administrator'), '!=', 1)
                ->orWhereNull($q->qualifyColumn('administrator'));
        });
    }

    /** Match Laravel app role stored in tc_users.attributes (excludes super admins). */
    public function scopeWhereAppRole(Builder $query, AppRole $role): Builder
    {
        if ($role === AppRole::SuperAdmin) {
            return $query->whereRaw('0 = 1');
        }

        $table = $query->getModel()->getTable();

        if (! TraccarSchema::hasColumn($table, 'attributes')) {
            return $role === AppRole::EndUser
                ? $query->excludeSuperAdmins()
                : $query->whereRaw('0 = 1');
        }

        $path = '$.' . TraccarAppFields::KEY_ROLE;

        return $query->excludeSuperAdmins()->where(function (Builder $q) use ($role, $path) {
            if ($role === AppRole::EndUser) {
                $q->whereRaw(
                    'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(' . $q->qualifyColumn('attributes') . ', ?)), ?) = ?',
                    [$path, AppRole::EndUser->value, AppRole::EndUser->value]
                );

                return;
            }

            $q->whereRaw(
                'JSON_UNQUOTE(JSON_EXTRACT(' . $q->qualifyColumn('attributes') . ', ?)) = ?',
                [$path, $role->value]
            );
        });
    }
}
