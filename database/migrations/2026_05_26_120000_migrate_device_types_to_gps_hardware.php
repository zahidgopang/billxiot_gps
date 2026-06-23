<?php

use App\Models\Device;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = Device::LEGACY_DEVICE_TYPE_MAP;

        Device::query()->orderBy('id')->chunkById(100, function ($devices) use ($map) {
            foreach ($devices as $device) {
                $current = $device->device_type;

                if (! $current || ! isset($map[$current])) {
                    continue;
                }

                $newType = $map[$current];
                $device->device_type = $newType;
                $device->save();
            }
        });

        $table = config('traccar.tables.devices', 'tc_devices');

        foreach ($map as $old => $new) {
            DB::table($table)->where('category', $old)->update(['category' => $new]);
        }
    }

    public function down(): void
    {
        // Non-reversible without losing precision.
    }
};
