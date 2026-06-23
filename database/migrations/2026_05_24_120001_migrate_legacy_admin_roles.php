<?php

use App\Enums\AppRole;
use App\Models\User;
use App\Support\Traccar\TraccarAppFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $usersTable = config('traccar.tables.users', 'tc_users');

        if (! \Illuminate\Support\Facades\Schema::hasTable($usersTable)) {
            return;
        }

        $rows = DB::table($usersTable)->where('administrator', 1)->get(['id', 'attributes']);

        foreach ($rows as $row) {
            $role = TraccarAppFields::get($row->attributes, TraccarAppFields::KEY_ROLE);

            if ($role === 'admin' || $role === null || $role === '') {
                $user = User::query()->find($row->id);

                if ($user) {
                    $user->role = AppRole::SuperAdmin->value;
                    $user->save();
                }
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: leave migrated roles in place.
    }
};
