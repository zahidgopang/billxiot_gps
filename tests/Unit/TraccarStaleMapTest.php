<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\TraccarEntityMap;
use App\Models\User;
use App\Services\Traccar\TraccarIdMap;
use App\Services\Traccar\TraccarSyncService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class TraccarStaleMapTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_repair_skips_user_device_link_when_mapped_device_missing_in_tc_devices(): void
    {
        Config::set('traccar.tables.devices', 'tc_devices');
        Config::set('traccar.tables.users', 'tc_users');
        Config::set('traccar.tables.user_device', 'tc_user_device');

        Schema::shouldReceive('hasTable')->andReturn(true);

        $idMap = Mockery::mock(TraccarIdMap::class);
        $idMap->shouldReceive('get')
            ->with(TraccarEntityMap::TYPE_USER, 10)
            ->andReturn(3);
        $idMap->shouldReceive('get')
            ->with(TraccarEntityMap::TYPE_DEVICE, 20)
            ->andReturn(1);
        $idMap->shouldReceive('forget')
            ->once()
            ->with(TraccarEntityMap::TYPE_DEVICE, 20);

        DB::shouldReceive('table')->with('tc_devices')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(false);

        DB::shouldReceive('table')->with('tc_users')->andReturnSelf();
        DB::shouldReceive('where')->with('id', 3)->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(true);

        DB::shouldReceive('table')->with('tc_user_device')->never();

        $linker = Mockery::mock(\App\Services\Traccar\TraccarUserDeviceLinker::class);
        $sync = new TraccarSyncService($idMap, $linker);

        $owner = new User(['id' => 10, 'email' => 'a@b.com']);
        $device = new Device(['user_id' => 10]);
        $device->id = 20;
        $owner->setRelation('devices', collect([$device]));

        $reflection = new \ReflectionClass($sync);
        $method = $reflection->getMethod('resolveTraccarDeviceIdForLink');
        $method->setAccessible(true);

        $result = $method->invoke($sync, $owner->devices->first(), false);

        $this->assertNull($result);
    }
}
