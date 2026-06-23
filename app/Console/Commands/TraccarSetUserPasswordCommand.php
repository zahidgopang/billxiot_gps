<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Traccar\TraccarSyncService;
use App\Support\Traccar\TraccarPassword;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TraccarSetUserPasswordCommand extends Command
{
    protected $signature = 'traccar:set-user-password
                            {email : Laravel user email}
                            {password : Plain password for Traccar + optional Laravel update}
                            {--laravel-only : Update Traccar tc_users only, do not change Laravel password}';

    protected $description = 'Set Traccar PBKDF2 password (fixes bcrypt mistakenly stored in hashedPassword)';

    public function handle(TraccarSyncService $sync): int
    {
        if (! TraccarSchema::hasUsers()) {
            $this->error('Traccar users table not available or TRACCAR_ENABLED is false.');

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Laravel user not found.');

            return self::FAILURE;
        }

        $plain = $this->argument('password');
        $table = config('traccar.tables.users', 'tc_users');
        $hashCol = TraccarSchema::resolveColumn($table, 'hashedPassword');
        $saltCol = TraccarSchema::resolveColumn($table, 'salt');

        if (! $hashCol || ! $saltCol) {
            $this->error('tc_users missing hashedPassword or salt column.');

            return self::FAILURE;
        }

        $derived = TraccarPassword::createHash($plain);
        $traccarId = $sync->syncUser($user);

        if (! $traccarId) {
            $this->error('Could not resolve Traccar user id.');

            return self::FAILURE;
        }

        DB::table($table)->where('id', $traccarId)->update(
            TraccarSchema::filterColumns($table, [
                'hashedPassword' => $derived['hash'],
                'salt' => $derived['salt'],
            ])
        );

        if (! $this->option('laravel-only')) {
            $user->password = $plain;
            $user->save();
            $this->line('Laravel password updated (bcrypt via model cast).');
        }

        $this->info("Traccar password set for {$user->email} (tc_users.id={$traccarId}).");
        $this->line('Use the same plain password to log into Traccar web UI.');

        return self::SUCCESS;
    }
}
