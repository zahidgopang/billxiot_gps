<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\Geofence;
use App\Models\TraccarEntityMap;
use App\Models\User;
use App\Services\Traccar\TraccarIdMap;
use App\Services\Traccar\TraccarSyncService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class TraccarGeofenceRemovalTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_remove_geofence_only_deletes_junction_and_geofence_rows(): void
    {
        Config::set('traccar.enabled', true);
        Config::set('traccar.mode', 'dual_write');
        Config::set('traccar.delete_geofence_row_on_remove', true);

        Schema::shouldReceive('hasTable')->andReturn(true);
        Schema::shouldReceive('getColumnListing')->andReturnUsing(function (string $table) {
            return match ($table) {
                'tc_device_geofence' => ['deviceid', 'geofenceid'],
                'tc_user_geofence' => ['userid', 'geofenceid'],
                'tc_geofences' => ['id', 'name', 'area'],
                default => [],
            };
        });

        $idMap = Mockery::mock(TraccarIdMap::class);
        $idMap->shouldReceive('get')->andReturnUsing(function (string $type, int $laravelId): ?int {
            return match ([$type, $laravelId]) {
                [TraccarEntityMap::TYPE_GEOFENCE, 100] => 100,
                [TraccarEntityMap::TYPE_DEVICE, 2] => 200,
                [TraccarEntityMap::TYPE_USER, 3] => 300,
                default => null,
            };
        });

        $user = new User;
        $user->id = 3;

        $device = new Device;
        $device->id = 2;
        $device->user_id = 3;
        $device->setRelation('user', $user);

        $geofence = new Geofence;
        $geofence->id = 100;
        $geofence->device_id = 2;
        $geofence->setRelation('device', $device);

        $idMap->shouldReceive('forget')->once()->with(TraccarEntityMap::TYPE_GEOFENCE, 100);

        $linker = Mockery::mock(\App\Services\Traccar\TraccarUserDeviceLinker::class);
        $linker->shouldReceive('upsert')->zeroOrMoreTimes();

        $queries = DB::pretend(function () use ($geofence, $idMap, $linker) {
            (new TraccarSyncService($idMap, $linker))->removeGeofence($geofence);
        });

        $logged = collect($queries)->map(function (array $entry) {
            return $entry['query'].' | '.json_encode($entry['bindings'] ?? []);
        })->implode("\n");

        $this->assertStringContainsString('tc_geofences', $logged);
        $this->assertMatchesRegularExpression('/delete\s+from\s+[`"]?tc_geofences/is', $logged);
        $this->assertDoesNotMatchRegularExpression('/delete\s+from\s+[`"]?tc_user_device/is', $logged);
        $this->assertDoesNotMatchRegularExpression('/delete\s+from\s+[`"]?tc_devices/is', $logged);
        $this->assertStringNotContainsString('device_locations', $logged);
    }
}
