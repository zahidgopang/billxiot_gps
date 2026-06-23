<?php

namespace App\Services\Traccar;

use App\Models\User;
use App\Support\Traccar\TraccarPassword;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Facades\DB;

/**
 * Sets Traccar UI password (PBKDF2 on hashedPassword) when Laravel admin sets a password.
 */
class TraccarUserPasswordSync
{
    public function applyTraccarLoginPassword(User $user, ?string $plainPassword = null): void
    {
        if (! TraccarSchema::hasUsers() || ! $user->id) {
            return;
        }

        $plain = $plainPassword ?? (string) config('traccar.default_user_password', '12345678');

        if ($plain === '') {
            return;
        }

        $table = config('traccar.tables.users', 'tc_users');
        $hashColumn = TraccarSchema::resolveColumn($table, 'hashedPassword');
        $saltColumn = TraccarSchema::resolveColumn($table, 'salt');

        if (! $hashColumn) {
            return;
        }

        $derived = TraccarPassword::createHash($plain);
        $payload = [
            $hashColumn => $derived['hash'],
        ];

        if ($saltColumn) {
            $payload[$saltColumn] = $derived['salt'];
        }

        DB::table($table)->where('id', $user->id)->update(
            TraccarSchema::filterColumns($table, $payload)
        );
    }
}
