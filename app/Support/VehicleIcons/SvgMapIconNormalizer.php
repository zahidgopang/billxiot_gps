<?php

namespace App\Support\VehicleIcons;

/**
 * Crops oversized SVG canvas padding so side-view suggestion icons sit nearer the GPS point.
 * Uses absolute path movetos (M) only — relative curve numbers are not coordinates.
 */
final class SvgMapIconNormalizer
{
    private const PAD = 28.0;

    public static function normalizeFile(string $absolutePath): bool
    {
        if (! is_file($absolutePath)) {
            return false;
        }

        $raw = file_get_contents($absolutePath);
        if ($raw === false || $raw === '') {
            return false;
        }

        $next = self::normalizeContents($raw);
        if ($next === null || $next === $raw) {
            return false;
        }

        return file_put_contents($absolutePath, $next) !== false;
    }

    public static function normalizeContents(string $svg): ?string
    {
        $box = self::bboxFromAbsoluteMoves($svg);
        if ($box === null) {
            return null;
        }

        // Only crop when the canvas has lots of empty framing (typical SVG Repo packs).
        $canvas = self::readExistingViewBox($svg) ?? ['w' => 1024.0, 'h' => 1024.0];
        $fillRatio = ($box['w'] * $box['h']) / max(1.0, $canvas['w'] * $canvas['h']);
        if ($fillRatio >= 0.72) {
            return null;
        }

        $viewBox = sprintf(
            '%.0f %.0f %.0f %.0f',
            $box['x'],
            $box['y'],
            $box['w'],
            $box['h']
        );

        $next = $svg;
        if (preg_match('/\sviewBox=["\'][^"\']*["\']/', $next)) {
            $next = preg_replace('/\sviewBox=["\'][^"\']*["\']/', ' viewBox="'.$viewBox.'"', $next, 1) ?? $next;
        } else {
            $next = preg_replace('/<svg\b/', '<svg viewBox="'.$viewBox.'"', $next, 1) ?? $next;
        }

        $next = preg_replace('/\swidth=["\'][^"\']*["\']/', ' width="256"', $next, 1) ?? $next;
        $next = preg_replace('/\sheight=["\'][^"\']*["\']/', ' height="256"', $next, 1) ?? $next;

        return $next;
    }

    /**
     * @return array{w: float, h: float}|null
     */
    private static function readExistingViewBox(string $svg): ?array
    {
        if (! preg_match('/\sviewBox=["\']\s*([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s*["\']/', $svg, $m)) {
            return null;
        }

        return [
            'w' => (float) $m[3],
            'h' => (float) $m[4],
        ];
    }

    /**
     * @return array{x: float, y: float, w: float, h: float}|null
     */
    private static function bboxFromAbsoluteMoves(string $svg): ?array
    {
        $minX = INF;
        $minY = INF;
        $maxX = -INF;
        $maxY = -INF;

        if (preg_match_all('/M\s*([-\d.]+)[,\s]+([-\d.]+)/', $svg, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $x = (float) $m[1];
                $y = (float) $m[2];
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        // Wheels often use absolute start + elliptical arc: "160 684.992a101.632 96.832"
        if (preg_match_all('/([-\d.]+)\s+([-\d.]+)a([-\d.]+)\s+([-\d.]+)/i', $svg, $arcs, PREG_SET_ORDER)) {
            foreach ($arcs as $a) {
                $cx = (float) $a[1];
                $cy = (float) $a[2];
                $rx = abs((float) $a[3]);
                $ry = abs((float) $a[4]);
                if ($rx > 300 || $ry > 300) {
                    continue;
                }
                $minX = min($minX, $cx - $rx);
                $minY = min($minY, $cy - $ry);
                $maxX = max($maxX, $cx + $rx);
                $maxY = max($maxY, $cy + $ry);
            }
        }

        if (! is_finite($minX) || $maxX <= $minX || $maxY <= $minY) {
            return null;
        }

        return [
            'x' => $minX - self::PAD,
            'y' => $minY - self::PAD,
            'w' => ($maxX - $minX) + (self::PAD * 2),
            'h' => ($maxY - $minY) + (self::PAD * 2),
        ];
    }
}
