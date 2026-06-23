<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\Traccar\TraccarPassword;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Verifies a user's current password (Laravel bcrypt in attributes and legacy Traccar hash).
 */
class UserPasswordVerifier
{
    public function verify(User $user, string $plain): bool
    {
        if ($plain === '') {
            return false;
        }

        $laravelHash = (string) $user->getAuthPassword();

        if ($laravelHash !== '' && Hash::check($plain, $laravelHash)) {
            return true;
        }

        return $this->traccarPasswordMatches($user, $plain);
    }

    private function traccarPasswordMatches(User $user, string $plain): bool
    {
        if (! TraccarSchema::hasUsers() || ! $user->id) {
            return false;
        }

        $table = $user->getTable();
        $hashColumn = TraccarSchema::resolveColumn($table, 'hashedPassword');

        if (! $hashColumn) {
            return false;
        }

        $saltColumn = TraccarSchema::resolveColumn($table, 'salt');
        $columns = array_values(array_filter([$hashColumn, $saltColumn]));
        $row = DB::table($table)->where('id', $user->id)->first($columns);

        if (! $row) {
            return false;
        }

        $hashHex = (string) ($row->{$hashColumn} ?? '');

        if ($hashHex === '' || TraccarPassword::looksLikeLaravelBcrypt($hashHex)) {
            return false;
        }

        $saltHex = $saltColumn ? (string) ($row->{$saltColumn} ?? '') : '';

        return TraccarPassword::validate($plain, $hashHex, $saltHex);
    }
}
