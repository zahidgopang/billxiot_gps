<?php

namespace App\Support\Pdf;

use ArPHP\I18N\Arabic;

/**
 * Pre-shape Arabic for PDF engines (dompdf) that lack OpenType Arabic joining.
 */
final class ArabicPdfText
{
    private static ?Arabic $engine = null;

    public static function shape(?string $text): string
    {
        $text = (string) $text;
        if ($text === '' || ! self::containsArabic($text)) {
            return $text;
        }

        try {
            return self::engine()->utf8Glyphs($text, 1000);
        } catch (\Throwable) {
            return $text;
        }
    }

    public static function containsArabic(string $text): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $text);
    }

    /**
     * @param  list<list<mixed>>  $rows
     * @return list<list<string>>
     */
    public static function shapeTableRows(array $rows): array
    {
        return array_map(
            fn (array $row) => array_map(
                fn (mixed $cell) => self::shapeCell($cell),
                $row,
            ),
            $rows,
        );
    }

    public static function shapeCell(mixed $cell): string
    {
        $text = (string) $cell;
        if ($text === '' || ! self::containsArabic($text)) {
            return $text;
        }

        return self::shape($text);
    }

    private static function engine(): Arabic
    {
        if (self::$engine === null) {
            self::$engine = new Arabic;
        }

        return self::$engine;
    }
}
