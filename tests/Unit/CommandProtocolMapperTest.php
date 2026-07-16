<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Services\Tracking\CommandProtocolMapper;
use App\Support\Traccar\TraccarAppFields;
use Tests\TestCase;

class CommandProtocolMapperTest extends TestCase
{
    public function test_explicit_command_profile_wins(): void
    {
        $mapper = $this->mapperWithoutPositions();
        $device = $this->deviceWithAttributes([
            'command_profile' => 'teltonika',
            'protocol' => 'gt06',
        ]);

        $detected = $mapper->detectProfileDetailed($device);

        $this->assertSame('teltonika', $detected['profile']);
        $this->assertSame('command_profile', $detected['source']);
    }

    public function test_cached_protocol_maps_to_teltonika_profile(): void
    {
        $mapper = $this->mapperWithoutPositions();
        $device = $this->deviceWithAttributes([
            TraccarAppFields::KEY_TRACCAR_PROTOCOL => 'teltonika',
        ], name: 'كيا ارسل', model: null);

        $detected = $mapper->detectProfileDetailed($device);

        $this->assertSame('teltonika', $detected['profile']);
        $this->assertSame('teltonika', $detected['protocol']);
        $this->assertSame('cached_protocol', $detected['source']);
    }

    public function test_position_protocol_selects_teltonika_when_model_empty(): void
    {
        $mapper = new class extends CommandProtocolMapper
        {
            protected function latestPositionProtocol(Device $device): ?string
            {
                return 'teltonika';
            }

            public function rememberProtocol(Device $device, string $protocol, ?array $attrs = null): void
            {
                // no-op in unit test
            }
        };

        $device = $this->deviceWithAttributes([], id: 3, name: 'كيا ارسل', model: null);
        $resolved = $mapper->resolve($device, 'engineStop');

        $this->assertSame('teltonika', $resolved['profile']);
        $this->assertSame('custom', $resolved['type']);
        $this->assertSame('setdigout 1', $resolved['wire_data']);
        $this->assertSame('position_protocol', $resolved['source']);
        $this->assertSame(['data' => 'setdigout 1'], $resolved['attributes']);
    }

    public function test_engine_resume_maps_to_setdigout_zero_for_teltonika(): void
    {
        $mapper = $this->mapperWithoutPositions();
        $device = $this->deviceWithAttributes([
            TraccarAppFields::KEY_TRACCAR_PROTOCOL => 'teltonika',
        ]);

        $resolved = $mapper->resolve($device, 'engineResume');

        $this->assertSame('teltonika', $resolved['profile']);
        $this->assertSame('custom', $resolved['type']);
        $this->assertSame('setdigout 0', $resolved['wire_data']);
    }

    public function test_protocol_to_profile_map_supports_future_aliases(): void
    {
        $mapper = app(CommandProtocolMapper::class);

        $this->assertSame('teltonika', $mapper->profileForProtocol('teltonika'));
        $this->assertSame('gt06', $mapper->profileForProtocol('h02'));
        $this->assertSame('gt06', $mapper->profileForProtocol('gt06'));
    }

    public function test_hardware_model_keyword_still_works(): void
    {
        $mapper = $this->mapperWithoutPositions();
        $device = $this->deviceWithAttributes([], name: 'Truck 12', model: 'FMC120');

        $detected = $mapper->detectProfileDetailed($device);

        $this->assertSame('teltonika', $detected['profile']);
        $this->assertSame('hardware_model', $detected['source']);
    }

    public function test_falls_back_to_generic_when_no_signals(): void
    {
        $mapper = $this->mapperWithoutPositions();
        $device = $this->deviceWithAttributes([], name: 'كيا ارسل', model: null);

        $detected = $mapper->detectProfileDetailed($device);

        $this->assertSame('generic', $detected['profile']);
        $this->assertSame('default', $detected['source']);
    }

    private function mapperWithoutPositions(): CommandProtocolMapper
    {
        return new class extends CommandProtocolMapper
        {
            protected function latestPositionProtocol(Device $device): ?string
            {
                return null;
            }

            public function rememberProtocol(Device $device, string $protocol, ?array $attrs = null): void
            {
                // no-op in unit test
            }
        };
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function deviceWithAttributes(
        array $attrs,
        int $id = 1,
        ?string $name = 'Device',
        ?string $model = null,
    ): Device {
        $device = new Device;
        $device->forceFill([
            'id' => $id,
            'name' => $name,
            'model' => $model,
            'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
        ]);
        $device->syncOriginal();

        return $device;
    }
}
