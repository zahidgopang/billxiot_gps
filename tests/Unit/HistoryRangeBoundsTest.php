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
        Carbon::setTestNow(Carbon::parse('2026-05-29 12:00:00', 'Asia/Riyadh'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_same_day_normalizes_to_full_calendar_day(): void
    {
        $tz = 'Asia/Riyadh';
        $from = Carbon::createFromFormat('Y-m-d', '2026-05-29', $tz);
        $to = Carbon::createFromFormat('Y-m-d', '2026-05-29', $tz);

        $range = HistoryRangeBounds::normalize($from, $to);

        $this->assertSame('2026-05-29 00:00:00', $range['from']->timezone($tz)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-29 23:59:59', $range['to']->timezone($tz)->format('Y-m-d H:i:s'));
    }

    public function test_same_day_traccar_bounds_cover_entire_ast_day_in_utc(): void
    {
        $tz = 'Asia/Riyadh';
        $from = Carbon::createFromFormat('Y-m-d', '2026-05-29', $tz)->startOfDay();
        $to = $from->copy()->endOfDay();

        $this->assertTrue(HistoryRangeBounds::isCalendarDayStart($from));
        $this->assertTrue(HistoryRangeBounds::isCalendarDayEnd($to));

        // 29 May 2026 00:00 AST = 28 May 2026 21:00 UTC
        $this->assertSame('2026-05-28 21:00:00', HistoryRangeBounds::traccarFromUtc($from));
        // Exclusive upper: 30 May 2026 00:00 AST = 29 May 2026 21:00 UTC
        $this->assertSame('2026-05-29 21:00:00', HistoryRangeBounds::traccarToExclusiveUtc($to));
    }

    public function test_instant_bounds_do_not_snap_to_calendar_day(): void
    {
        $tz = 'Asia/Riyadh';
        $from = Carbon::parse('2026-05-29 10:15:30', $tz);
        $to = Carbon::parse('2026-05-29 15:45:00', $tz);

        $this->assertFalse(HistoryRangeBounds::isCalendarDayStart($from));
        $this->assertFalse(HistoryRangeBounds::isCalendarDayEnd($to));
        $this->assertSame('2026-05-29 07:15:30', HistoryRangeBounds::traccarInstantUtc($from));
        $this->assertSame('2026-05-29 12:45:00', HistoryRangeBounds::traccarInstantUtc($to));
    }

    public function test_multi_day_range_spans_inclusive_calendar_days(): void
    {
        $tz = 'Asia/Riyadh';
        $from = Carbon::createFromFormat('Y-m-d', '2026-05-27', $tz);
        $to = Carbon::createFromFormat('Y-m-d', '2026-05-29', $tz);

        $range = HistoryRangeBounds::normalize($from, $to);

        $this->assertSame('2026-05-27 00:00:00', $range['from']->timezone($tz)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-29 23:59:59', $range['to']->timezone($tz)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-26 21:00:00', HistoryRangeBounds::traccarFromUtc($range['from']));
        $this->assertSame('2026-05-29 21:00:00', HistoryRangeBounds::traccarToExclusiveUtc($range['to']));
    }
}
