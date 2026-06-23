<?php

namespace App\Auth;

use App\Models\User;
use App\Support\Traccar\TraccarPassword;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;

/**
 * Laravel login uses attributes.laravel_password (bcrypt).
 * Traccar UI creates users with PBKDF2 on hashedPassword only — accept those credentials
 * once and persist a Laravel hash for future logins.
 */
class TcAwareUserProvider extends EloquentUserProvider
{
    public function __construct(Hasher $hasher, string $model)
    {
        parent::__construct($hasher, $model);
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        if (! $this->usersTableReady()) {
            return null;
        }

        try {
            return parent::retrieveById($identifier);
        } catch (\Throwable) {
            return null;
        }
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        if (! $this->usersTableReady()) {
            return null;
        }

        try {
            return parent::retrieveByToken($identifier, $token);
        } catch (\Throwable) {
            return null;
        }
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        if (! $this->usersTableReady()) {
            return null;
        }

        try {
            return parent::retrieveByCredentials($credentials);
        } catch (\Throwable) {
            return null;
        }
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        $plain = (string) ($credentials['password'] ?? '');

        if ($plain === '') {
            return false;
        }

        $laravelHash = (string) $user->getAuthPassword();

        if ($laravelHash !== '' && $this->hasher->check($plain, $laravelHash)) {
            return true;
        }

        if (! TraccarSchema::hasUsers() || ! $user instanceof User || ! $user->id) {
            return false;
        }

        if (! $this->traccarPasswordMatches($user, $plain)) {
            return false;
        }

        $user->password = $plain;
        $user->save();

        return true;
    }

    private function traccarPasswordMatches(User $user, string $plain): bool
    {
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

    private function usersTableReady(): bool
    {
        $model = $this->createModel();

        return TraccarSchema::hasTable($model->getTable());
    }
}
