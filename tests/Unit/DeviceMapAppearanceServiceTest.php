<?php

namespace Tests\Unit;

use App\Services\Tracking\DeviceMapAppearanceService;
use App\Services\Tracking\DeviceMapIconAuthorization;
use App\Services\Tracking\DeviceVehicleIconService;
use Mockery;
use Tests\TestCase;

class DeviceMapAppearanceServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_mobile_upload_options_do_not_expose_default_icon_choices(): void
    {
        $service = new DeviceMapAppearanceService(
            Mockery::mock(DeviceVehicleIconService::class),
            Mockery::mock(DeviceMapIconAuthorization::class),
        );

        $options = $service->mobileUploadOptions();

        $this->assertSame('custom_upload_only', $options['mode']);
        $this->assertArrayNotHasKey('vehicle_types', $options);
        $this->assertArrayNotHasKey('marker_styles', $options);
        $this->assertArrayNotHasKey('icon_sources', $options);
        $this->assertFalse($options['actions']['choose_default_icon']);
        $this->assertFalse($options['actions']['remove_custom_icon']);
        $this->assertTrue($options['navigation']['back_enabled']);
        $this->assertArrayHasKey('marker_sizes', $options);
        $this->assertArrayHasKey('upload', $options);
    }
}
