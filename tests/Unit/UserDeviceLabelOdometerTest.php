<?php

namespace Tests\Unit;

use App\Http\Controllers\UserDevicesController;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class UserDeviceLabelOdometerTest extends TestCase
{
    public function test_update_vehicle_label_request_includes_odometer_field(): void
    {
        $controllerFile = (new ReflectionMethod(UserDevicesController::class, 'updateVehicleLabel'))
            ->getFileName();
        $source = file_get_contents($controllerFile) ?: '';

        $this->assertStringContainsString(
            "request->only(['vehicle_name', 'vehicle_number', 'odometer_base_km'])",
            $source,
            'End-user vehicle save must pass odometer_base_km through to UserDeviceLabelService.'
        );

        $request = Request::create('/user/devices/1/vehicle-label', 'POST', [
            'vehicle_name' => 'Truck',
            'vehicle_number' => 'ABC-1',
            'odometer_base_km' => '85000',
        ]);

        $this->assertSame(
            ['vehicle_name' => 'Truck', 'vehicle_number' => 'ABC-1', 'odometer_base_km' => '85000'],
            $request->only(['vehicle_name', 'vehicle_number', 'odometer_base_km'])
        );
    }
}
