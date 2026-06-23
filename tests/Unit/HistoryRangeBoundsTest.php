<?php

namespace Tests\Unit;

use App\Support\Tracking\HistoryRangeBounds;
use Carbon\Carbon;
use Tests\TestCase;

class HistoryRangeBoundsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-29 12:00:00', 'Asia/Karachi'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_same_day_normalizes_to_full_calendar_day(): void
    {
        $tz = 'Asia/Karachi';
        $from = Carbon::createFromFormat('Y-m-d', '2026-05-29', $tz);
        $to = Carbon::createFromFormat('Y-m-d', '2026-05-29', $tz);

        $range = HistoryRangeBounds::normalize($from, $to);

        $this->assertSame('2026-05-29 00:00:00', $range['from']->timezone($tz)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-29 23:59:59', $range['to']->timezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_same_day_traccar_bounds_cover_entire_pkt_day_in_utc(): void
    {
        $tz = 'Asia/Karachi';
        $from = Carbon::createFromFormat('Y-m-d', '2026-05-29', $tz)->startOfDay();
        $to = $from->copy()->endOfDay();

        $this->assertTrue(HistoryRangeBounds::isCalendarDayStart($from));
        $this->assertTrue(HistoryRangeBounds::isCalendarDayEnd($to));

        // 29 May 2026 00:00 PKT = 28 May 2026 19:00 UTC
        $this->assertSame('2026-05-28 19:00:00', HistoryRangeBounds::traccarFromUtc($from));
        // Exclusive upper: 30 May 2026 00:00 PKT = 29 May 2026 19:00 UTC
        $this->assertSame('2026-05-29 19:00:00', HistoryRangeBounds::traccarToExclusiveUtc($to));
    }

    public function test_instant_bounds_do_not_snap_to_calendar_day(): void
    {
        $tz = 'Asia/Karachi';
        $from = Carbon::parse('2026-05-29 10:15:30', $tz);
        $to = Carbon::parse('2026-05-29 15:45:00', $tz);

        $this->assertFalse(HistoryRangeBounds::isCalendarDayStart($from));
        $this->assertFalse(HistoryRangeBounds::isCalendarDayEnd($to));
        $this->assertSame('2026-05-29 05:15:30', HistoryRangeBounds::traccarInstantUtc($from));
        $this->assertSame('2026-05-29 10:45:00', HistoryRangeBounds::traccarInstantUtc($to));
    }

    public function test_multi_day_range_spans_inclusive_calendar_days(): void
    {
        $tz = 'Asia/Karachi';
        $from = Carbon::createFromFormat('Y-m-d', '2026-05-27', $tz);
        $to = Carbon::createFromFormat('Y-m-d', '2026-05-29', $tz);

        $range = HistoryRangeBounds::normalize($from, $to);

        $this->assertSame('2026-05-27 00:00:00', $range['from']->timezone($tz)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-29 23:59:59', $range['to']->timezone($tz)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-26 19:00:00', HistoryRangeBounds::traccarFromUtc($range['from']));
        $this->assertSame('2026-05-29 19:00:00', HistoryRangeBounds::traccarToExclusiveUtc($range['to']));
    }
}
