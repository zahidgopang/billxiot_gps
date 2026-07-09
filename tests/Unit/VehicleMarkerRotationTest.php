<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Services\Tracking\SharedMapIconService;
use Tests\TestCase;

class VehicleMarkerRotationTest extends TestCase
{
    public function test_normalize_rotation_offset_degrees(): void
    {
        $this->assertSame(0, Device::normalizeRotationOffsetDegrees(0));
        $this->assertSame(-90, Device::normalizeRotationOffsetDegrees(-90));
        $this->assertSame(-90, Device::normalizeRotationOffsetDegrees(270));
        $this->assertSame(180, Device::normalizeRotationOffsetDegrees(180));
        $this->assertSame(90, Device::normalizeRotationOffsetDegrees(90));
        $this->assertSame(0, Device::normalizeRotationOffsetDegrees(360));
    }

    public function test_shared_icon_service_normalizes_quarter_turns(): void
    {
        $svc = app(SharedMapIconService::class);

        $this->assertSame(0, $svc->normalizeRotationOffset(0));
        $this->assertSame(-90, $svc->normalizeRotationOffset(270));
        $this->assertSame(-90, $svc->normalizeRotationOffset(-90));
        $this->assertSame(180, $svc->normalizeRotationOffset(180));
        $this->assertSame(90, $svc->normalizeRotationOffset(90));
        $this->assertSame(0, $svc->normalizeRotationOffset(null));
    }

    public function test_final_rotation_formula_examples(): void
    {
        // Final = (heading + offset) mod 360
        $this->assertSame(90, $this->finalRotation(90, 0));
        $this->assertSame(0, $this->finalRotation(90, -90));
        $this->assertSame(270, $this->finalRotation(0, -90));
        $this->assertSame(180, $this->finalRotation(0, 180));
        $this->assertSame(5, $this->finalRotation(350, 15));
    }

    private function finalRotation(float $heading, float $offset): int
    {
        $n = fmod($heading + $offset, 360.0);
        if ($n < 0) {
            $n += 360.0;
        }

        return (int) round($n);
    }
}
