<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Push\DeviceConnectivityPushService;
use App\Services\Traccar\TraccarDeviceAccessService;
use Illuminate\Console\Command;

class CheckDeviceConnectivityCommand extends Command
{
    protected $signature = 'devices:check-connectivity {--user= : Limit to a single user id}';

    protected $description = 'Send device offline push notifications when GPS stops reporting';

    public function handle(DeviceConnectivityPushService $connectivity, TraccarDeviceAccessService $access): int
    {
        if (! config('firebase.enabled')) {
            $this->warn('Push notifications are disabled (PUSH_NOTIFICATIONS_ENABLED=false).');

            return self::SUCCESS;
        }

        $userId = $this->option('user') ? (int) $this->option('user') : null;

        Device::query()
            ->whereAppStatus('active')
            ->when($userId !== null, function ($query) use ($userId, $access) {
                $user = \App\Models\User::query()->find($userId);
                if (! $user) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $ids = $access->laravelDeviceIdsForUser($user);
                $query->whereIn('id', $ids !== [] ? $ids : [0]);
            })
            ->orderBy('id')
            ->chunkById(100, function ($devices) use ($connectivity) {
                foreach ($devices as $device) {
                    $connectivity->checkDevice($device);
                }
            });

        $this->info('Device connectivity check completed.');

        return self::SUCCESS;
    }
}
