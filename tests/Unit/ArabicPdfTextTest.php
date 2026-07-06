<?php

namespace Tests\Unit;

use App\Support\Pdf\ArabicPdfText;
use Tests\TestCase;

class ArabicPdfTextTest extends TestCase
{
    public function test_shape_connects_arabic_letters(): void
    {
        $input = 'تقارير التتبع';
        $shaped = ArabicPdfText::shape($input);

        $this->assertNotSame($input, $shaped);
        $this->assertNotEmpty($shaped);
    }

    public function test_shape_leaves_english_unchanged(): void
    {
        $input = 'Vehicle A';
        $this->assertSame($input, ArabicPdfText::shape($input));
    }

    public function test_shape_table_rows_shapes_arabic_headers_only(): void
    {
        $rows = [
            ['المركبة', 'Distance (km)'],
            ['ب ص ع 5060', '12.5'],
        ];

        $shaped = ArabicPdfText::shapeTableRows($rows);

        $this->assertNotSame($rows[0][0], $shaped[0][0]);
        $this->assertSame('Distance (km)', $shaped[0][1]);
        $this->assertNotSame($rows[1][0], $shaped[1][0]);
        $this->assertSame('12.5', $shaped[1][1]);
    }

    public function test_contains_arabic_detection(): void
    {
        $this->assertTrue(ArabicPdfText::containsArabic('وقت الحركة'));
        $this->assertFalse(ArabicPdfText::containsArabic('2026-07-01T08:00:00+03:00'));
    }
}
