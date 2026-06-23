<?php

use App\Support\Traccar\TraccarPassword;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('traccar.tables.users', 'tc_users');

        if (! Schema::hasTable($table)) {
            return;
        }

        $email = strtolower((string) env('ADMIN_EMAIL', 'admin@admin.com'));
        $plainPassword = (string) env('ADMIN_PASSWORD', '12345678');

        if ($email === '' || $plainPassword === '') {
            return;
        }

        if (DB::table($table)->where('email', $email)->exists()) {
            return;
        }

        $derived = TraccarPassword::createHash($plainPassword);
        $now = now()->toDateTimeString();

        $attributes = json_encode(array_filter([
            'laravel_role' => 'admin',
            'laravel_status' => 'active',
            'laravel_password' => Hash::make($plainPassword),
            'laravel_preferences' => ['locale' => 'en', 'map_tour' => 'dismiss'],
            'laravel_email_verified_at' => $now,
            'laravel_created_at' => $now,
        ]));

        $payload = [
            'name' => env('ADMIN_NAME', 'Admin'),
            'email' => $email,
            'login' => $email,
            'administrator' => 1,
            'disabled' => 0,
            'readonly' => 0,
            'userLimit' => -1,
            'deviceLimit' => -1,
            'deviceReadonly' => 0,
            'limitCommands' => 0,
            'hashedPassword' => $derived['hash'],
            'salt' => $derived['salt'],
            'attributes' => $attributes,
        ];

        DB::table($table)->insert(
            TraccarSchema::filterColumns($table, $payload)
        );
    }

    public function down(): void
    {
        $table = config('traccar.tables.users', 'tc_users');
        $email = strtolower((string) env('ADMIN_EMAIL', 'admin@admin.com'));

        if (! Schema::hasTable($table) || $email === '') {
            return;
        }

        DB::table($table)->where('email', $email)->delete();
    }
};
