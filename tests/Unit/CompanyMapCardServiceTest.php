<?php

namespace Tests\Unit;

use App\Models\AppSetting;
use App\Services\Tracking\CompanyMapCardService;
use Tests\TestCase;

class CompanyMapCardServiceTest extends TestCase
{
    public function test_should_display_requires_checkbox_and_company_data(): void
    {
        $service = app(CompanyMapCardService::class);

        $this->assertFalse($service->shouldDisplay([
            'show_on_map' => false,
            'company_name' => 'Acme Transport',
        ]));

        $this->assertFalse($service->shouldDisplay([
            'show_on_map' => true,
            'company_name' => '',
            'company_number' => '',
            'operation_card' => '',
            'support' => '',
        ]));

        $this->assertTrue($service->shouldDisplay([
            'show_on_map' => true,
            'company_name' => 'Acme Transport',
        ]));
    }

    public function test_settings_load_from_global_app_settings(): void
    {
        AppSetting::putValue(CompanyMapCardService::STORAGE_KEY, [
            'company_name' => 'Global Co',
            'company_number' => '123',
            'operation_card' => '',
            'support' => '',
            'show_on_map' => true,
        ]);

        $service = app(CompanyMapCardService::class);
        $payload = $service->mapPayload();

        $this->assertTrue($payload['enabled']);
        $this->assertSame('Global Co', $payload['company_name']);
        $this->assertSame('123', $payload['company_number']);
    }
}
