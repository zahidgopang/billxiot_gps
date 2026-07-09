<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\User;
use App\Services\Authorization\RbacService;
use App\Services\Tracking\DeviceMapIconAuthorization;
use Mockery;
use Tests\TestCase;

class DeviceMapIconAuthorizationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_device_owner_without_permissions_cannot_edit_appearance_or_view_details(): void
    {
        $user = Mockery::mock(User::class);
        $device = new Device(['id' => 7]);
        $rbac = Mockery::mock(RbacService::class);
        $rbac->shouldReceive('hasPermission')->andReturnFalse();

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('id', 7)->andReturnSelf();
        $query->shouldReceive('exists')->andReturnTrue();
        $user->shouldReceive('trackerDevicesQuery')->andReturn($query);

        $auth = new DeviceMapIconAuthorization($rbac);

        $this->assertFalse($auth->canEditAppearance($user, $device));
        $this->assertFalse($auth->canChangeVehicleIcon($user, $device));
        $this->assertFalse($auth->canUploadCustomIcon($user, $device));
        $this->assertFalse($auth->canViewVehicleDetails($user, $device));
    }

    public function test_change_vehicle_icon_permission_allows_preset_icon_edits(): void
    {
        $user = Mockery::mock(User::class);
        $device = new Device(['id' => 3]);
        $rbac = Mockery::mock(RbacService::class);
        $rbac->shouldReceive('hasPermission')
            ->with($user, 'devices.manage')
            ->andReturnFalse();
        $rbac->shouldReceive('hasPermission')
            ->with($user, 'devices.manage_map_icons')
            ->andReturnTrue();
        $rbac->shouldReceive('hasPermission')
            ->with($user, Mockery::anyOf(
                'web.vehicles.change_icon_size',
                'web.vehicles.upload_custom_icon',
                'mobile.map.custom_icon',
                'web.vehicles.view_details',
            ))
            ->andReturnFalse();

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('id', 3)->andReturnSelf();
        $query->shouldReceive('exists')->andReturnTrue();
        $user->shouldReceive('trackerDevicesQuery')->andReturn($query);

        $auth = new DeviceMapIconAuthorization($rbac);

        $this->assertTrue($auth->canChangeVehicleIcon($user, $device));
        $this->assertTrue($auth->canEditAppearance($user, $device));
        $this->assertFalse($auth->canUploadCustomIcon($user, $device));
    }

    public function test_view_vehicle_details_permission_allows_label_edits(): void
    {
        $user = Mockery::mock(User::class);
        $device = new Device(['id' => 9]);
        $rbac = Mockery::mock(RbacService::class);
        $rbac->shouldReceive('hasPermission')
            ->with($user, 'devices.manage')
            ->andReturnFalse();
        $rbac->shouldReceive('hasPermission')
            ->with($user, 'web.vehicles.view_details')
            ->andReturnTrue();
        $rbac->shouldReceive('hasPermission')
            ->with($user, Mockery::not('web.vehicles.view_details'))
            ->andReturnFalse();

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('id', 9)->andReturnSelf();
        $query->shouldReceive('exists')->andReturnTrue();
        $user->shouldReceive('trackerDevicesQuery')->andReturn($query);

        $auth = new DeviceMapIconAuthorization($rbac);

        $this->assertTrue($auth->canViewVehicleDetails($user, $device));
        $this->assertTrue($auth->canEditVehicleLabel($user, $device));
        $this->assertFalse($auth->canEditAppearance($user, $device));
    }

    public function test_devices_manage_bypasses_granular_icon_permissions(): void
    {
        $user = Mockery::mock(User::class);
        $device = new Device(['id' => 1]);
        $rbac = Mockery::mock(RbacService::class);
        $rbac->shouldReceive('hasPermission')
            ->with($user, 'devices.manage')
            ->andReturnTrue();
        $rbac->shouldReceive('hasPermission')
            ->with($user, Mockery::not('devices.manage'))
            ->andReturnFalse();

        $auth = new DeviceMapIconAuthorization($rbac);

        $this->assertTrue($auth->canView($user, $device));
        $this->assertTrue($auth->canChangeVehicleIcon($user, $device));
        $this->assertTrue($auth->canUploadCustomIcon($user, $device));
        $this->assertTrue($auth->canViewVehicleDetails($user, $device));
    }
}
