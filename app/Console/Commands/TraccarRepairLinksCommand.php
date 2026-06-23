<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Traccar\TraccarSyncService;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Console\Command;

class TraccarRepairLinksCommand extends Command
{
    protected $signature = 'traccar:repair-links
                            {--user= : Laravel user id or email (optional, default all users)}';

    protected $description = 'Rebuild tc_user_device from Laravel ownership (upsert only, never touches geofence tables)';

    public function handle(TraccarSyncService $sync): int
    {
        if (! TraccarMode::isActive()) {
            $this->error('Set TRACCAR_ENABLED=true in .env before running repair.');

            return self::FAILURE;
        }

        if (! TraccarSchema::isReady()) {
            $this->error('Traccar tables were not found.');

            return self::FAILURE;
        }

        $user = null;
        $filter = $this->option('user');

        if ($filter) {
            $user = is_numeric($filter)
                ? User::query()->find((int) $filter)
                : User::query()->where('email', $filter)->first();

            if (! $user) {
                $this->error('User not found.');

                return self::FAILURE;
            }
        }

        $pruned = $sync->pruneStaleEntityMaps();
        if ($pruned > 0) {
            $this->warn("Pruned {$pruned} stale traccar_entity_map row(s).");
        }

        $count = $sync->repairUserDeviceLinks($user);

        $this->info("Upserted {$count} tc_user_device link(s) from Laravel devices.");

        return self::SUCCESS;
    }
}
