<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Support\VehicleIcons\BuiltinMapIconStorage;
use App\Support\VehicleIcons\VehicleIconLibrary;
use Tests\TestCase;

class DeviceMapIconFallbackTest extends TestCase
{
    public function test_url_for_missing_shared_path_falls_back_to_car(): void
    {
        $url = VehicleIconLibrary::urlForIconPath('Shared/does_not_exist.svg');
        $this->assertStringContainsString('Vehicles/car.svg', $url);
    }

    public function test_fallback_map_icon_url_points_to_builtin_car(): void
    {
        $device = new Device;
        $url = $device->fallbackMapIconUrl();
        $this->assertSame(BuiltinMapIconStorage::urlForType('car'), $url);
    }

    public function test_builtin_car_file_exists_on_disk(): void
    {
        $path = BuiltinMapIconStorage::absolutePath('Vehicles/car.svg');
        $this->assertFileExists($path);
    }
}
