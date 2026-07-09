<?php

namespace Tests\Unit;

use App\Support\VehicleIcons\BuiltinMapIconStorage;
use App\Support\VehicleIcons\VehicleIconLibrary;
use Tests\TestCase;

class VehicleIconCatalogTest extends TestCase
{
    public function test_builtin_catalog_files_still_exist_on_disk(): void
    {
        $required = [
            'car', 'bicycle', 'motorcycle', 'tractor', 'suv', 'pickup_truck', 'van', 'mini_van', 'cargo_van',
            'taxi', 'bus', 'school_bus', 'truck', 'semi_truck', 'trailer_truck', 'flatbed_trailer',
            'container_truck', 'tanker_truck', 'dump_truck', 'refrigerated_truck', 'cement_mixer',
            'garbage_truck', 'tow_truck', 'forklift', 'excavator', 'bulldozer', 'crane', 'loader',
            'helicopter', 'airplane', 'hot_air_balloon', 'drone', 'private_jet',
            'sail_boat', 'speed_boat', 'yacht', 'cargo_ship', 'fishing_boat', 'ferry',
            'container_red', 'container_blue', 'container_green', 'container_yellow',
            'package', 'pallet', 'fuel_tank', 'generator', 'gps_device', 'radio_device',
            'dog', 'cat', 'horse', 'camel', 'cow', 'turtle', 'zebra', 'sheep', 'goat',
            'person', 'walking_person', 'security_guard', 'worker',
        ];

        foreach ($required as $key) {
            $path = BuiltinMapIconStorage::relativePathForType($key);
            $this->assertTrue(
                BuiltinMapIconStorage::isValidRelativePath($path),
                "Missing built-in SVG file for: {$key} ({$path})"
            );
        }
    }

    public function test_picker_registry_is_shared_uploads_only(): void
    {
        $registry = VehicleIconLibrary::clientRegistry();

        $this->assertTrue($registry['shared_only'] ?? false);
        $this->assertIsArray($registry['icons']);
        $this->assertArrayNotHasKey('car', $registry['icons']);
        $this->assertArrayNotHasKey('truck', $registry['icons']);

        $catIds = collect($registry['categories'])->pluck('id')->all();
        foreach (['vehicles', 'aircraft', 'marine', 'assets', 'animals', 'people'] as $cat) {
            $this->assertContains($cat, $catIds);
        }
        $this->assertNotContains('shared', $catIds);
    }

    public function test_aliases_resolve_to_valid_icons(): void
    {
        $this->assertSame('pickup_truck', VehicleIconLibrary::resolveType('pickup'));
        $this->assertSame('sail_boat', VehicleIconLibrary::resolveType('boat'));
        $this->assertSame('dog', VehicleIconLibrary::resolveType('pet'));
        $this->assertSame('container_red', VehicleIconLibrary::resolveType('shipping_container'));
    }
}
