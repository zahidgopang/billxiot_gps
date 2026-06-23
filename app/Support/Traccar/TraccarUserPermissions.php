<?php

namespace App\Support\Traccar;

use App\Models\User;

/**
 * Maps Laravel user role/status to Traccar tc_users permission columns.
 *
 * @see https://www.traccar.org/user-management/
 */
final class TraccarUserPermissions
{
    /** Administrator: full server access (users, devices, settings). */
    public static function applyToModel(User $user): void
    {
        $table = $user->getTable();

        foreach (self::resolvedColumns($table, $user) as $column => $value) {
            $user->setAttribute($column, $value);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function syncPayload(User $user): array
    {
        $table = config('traccar.tables.users', 'tc_users');

        return self::resolvedColumns($table, $user);
    }

    /**
     * @return array<string, mixed> Actual DB column names => values
     */
    private static function resolvedColumns(string $table, User $user): array
    {
        return TraccarSchema::filterColumns($table, self::permissionValues($user));
    }

    /**
     * @return array<string, mixed>
     */
    private static function permissionValues(User $user): array
    {
        $isAdmin = $user->isAdmin();
        $isActive = ($user->status ?? 'active') === 'active';

        $values = [
            'administrator' => $isAdmin ? 1 : 0,
            'disabled' => $isActive ? 0 : 1,
            'readonly' => 0,
            'userLimit' => $isAdmin ? -1 : 0,
            'deviceLimit' => $isAdmin ? -1 : 0,
            'deviceReadonly' => 0,
        ];

        if ($isAdmin) {
            $values['limitCommands'] = 0;
        }

        return $values;
    }
}
