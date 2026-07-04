<?php

namespace Tests\Unit;

use App\Support\Geo\GeoLocalityResolver;
use PHPUnit\Framework\TestCase;

class GeoLocalityResolverTest extends TestCase
{
    public function test_sanitize_label_strips_plus_code(): void
    {
        $resolver = new GeoLocalityResolver();

        $this->assertNull($resolver->sanitizeLabel('7Q8P+5F'));
        $this->assertSame('Makkah', $resolver->sanitizeLabel('7Q8P+5F Makkah, Saudi Arabia'));
        $this->assertSame('Madinah', $resolver->sanitizeLabel('Madinah, Saudi Arabia'));
    }

    public function test_from_address_components_prefers_locality(): void
    {
        $resolver = new GeoLocalityResolver();

        $name = $resolver->fromAddressComponents([
            ['long_name' => 'Makkah', 'types' => ['locality', 'political']],
            ['long_name' => 'Makkah Province', 'types' => ['administrative_area_level_1', 'political']],
        ]);

        $this->assertSame('Makkah', $name);
    }

    public function test_is_plus_code(): void
    {
        $resolver = new GeoLocalityResolver();

        $this->assertTrue($resolver->isPlusCode('7Q8P+5F'));
        $this->assertFalse($resolver->isPlusCode('Makkah'));
    }
}
