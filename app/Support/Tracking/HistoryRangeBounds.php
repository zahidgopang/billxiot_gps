<?php

namespace App\Support\Tracking;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Normalizes Y-m-d history filters to full calendar days in app timezone (PKT)
 * and builds UTC bounds for Traccar tc_positions.fixtime.
 */
final class HistoryRangeBounds
{
    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public static function normalize(Carbon $from, ?Carbon $to): array
    {
        $tz = (string) config('app.timezone', 'Asia/Karachi');

        $from = $from->copy()->timezone($tz)->startOfDay();
        $toDay = ($to ?? $from)->copy()->timezone($tz)->startOfDay();

        if ($toDay->lessThan($from)) {
            [$from, $toDay] = [$toDay->copy()->startOfDay(), $from->copy()->startOfDay()];
        }

        $to = $toDay->copy()->endOfDay();

        return ['from' => $from, 'to' => $to];
    }

    /** Inclusive lower bound for Traccar fixtime (UTC) — calendar-day start in app TZ. */
    public static function traccarFromUtc(Carbon $from): string
    {
        $tz = (string) config('app.timezone', 'Asia/Karachi');

        return $from->copy()->timezone($tz)->startOfDay()->utc()->format('Y-m-d H:i:s');
    }

    /** Inclusive lower bound for an exact instant (e.g. last 24 hours). */
    public static function traccarInstantUtc(Carbon $instant): string
    {
        return $instant->copy()->utc()->format('Y-m-d H:i:s');
    }

    public static function isCalendarDayStart(Carbon $dt): bool
    {
        $tz = (string) config('app.timezone', 'Asia/Karachi');
        $local = $dt->copy()->timezone($tz);

        return $local->format('H:i:s') === '00:00:00';
    }

    public static function isCalendarDayEnd(Carbon $dt): bool
    {
        $tz = (string) config('app.timezone', 'Asia/Karachi');
        $local = $dt->copy()->timezone($tz);

        return $local->gte($local->copy()->startOfDay()->setTime(23, 59, 59));
    }

    /**
     * Exclusive upper bound — start of the day after $to in app timezone, as UTC.
     * Includes every fixtime on the last calendar day (through 23:59:59 PKT).
     */
    public static function traccarToExclusiveUtc(Carbon $to): string
    {
        $tz = (string) config('app.timezone', 'Asia/Karachi');

        return $to->copy()->timezone($tz)->startOfDay()->addDay()->utc()->format('Y-m-d H:i:s');
    }

    public static function debugLog(
        ?int $deviceId,
        string $fromInput,
        string $toInput,
        Carbon $from,
        Carbon $to,
        int $pointCount,
        bool $enabled = true
    ): void {
        if (! $enabled) {
            return;
        }

        Log::debug('gps.history.range', [
            'device_id' => $deviceId,
            'from_input' => $fromInput,
            'to_input' => $toInput,
            'from_pkt' => $from->toIso8601String(),
            'to_pkt' => $to->toIso8601String(),
            'traccar_from_utc' => self::traccarFromUtc($from),
            'traccar_to_exclusive_utc' => self::traccarToExclusiveUtc($to),
            'points' => $pointCount,
        ]);
    }
}
