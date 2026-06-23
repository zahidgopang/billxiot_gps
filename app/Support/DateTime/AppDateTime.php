<?php

namespace App\Support\DateTime;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * FalconEyeGPS display/API timestamps — always Asia/Karachi (UTC+05:00).
 */
final class AppDateTime
{
    public const TZ = 'Asia/Karachi';

    public static function tz(): string
    {
        return (string) config('app.timezone', self::TZ);
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::tz());
    }

    public static function parse(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->timezone(self::tz());
    }

    public static function inAppTz(?CarbonInterface $dt): ?Carbon
    {
        if ($dt === null) {
            return null;
        }

        return Carbon::parse($dt)->timezone(self::tz());
    }

    /** ISO-8601 with +05:00 offset for API / mobile parsing. */
    public static function toApi(?CarbonInterface $dt): ?string
    {
        return self::inAppTz($dt)?->toIso8601String();
    }

    /**
     * User-facing formats (12-hour AM/PM).
     *
     * @param  'display'|'display_short'|'date'|'time'|'date_long'|'log'  $style
     */
    public static function format(?CarbonInterface $dt, string $style = 'display'): ?string
    {
        $c = self::inAppTz($dt);
        if ($c === null) {
            return null;
        }

        return match ($style) {
            'display' => $c->format('j M Y, g:i A'),
            'display_short' => $c->format('d-M-Y g:i A'),
            'date' => $c->format('j M Y'),
            'time' => $c->format('g:i A'),
            'date_long' => $c->format('l, F j, Y'),
            'log' => $c->format('Y-m-d H:i:s'),
            default => $c->format($style),
        };
    }

    /**
     * @return array{time: ?string, time_display: ?string}
     */
    public static function apiFields(?CarbonInterface $dt): array
    {
        return [
            'time' => self::toApi($dt),
            'time_display' => self::format($dt, 'display'),
        ];
    }
}
