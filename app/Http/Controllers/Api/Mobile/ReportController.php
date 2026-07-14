<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Concerns\AppliesReportLocale;
use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Services\Tracking\Reports\ReportExportService;
use App\Services\Tracking\Reports\ReportService;
use App\Support\Tracking\HistoryRangeBounds;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use AppliesReportLocale;
    use ResolvesTrackingPanel;
    use RespondsWithMobileJson;

    public function __construct(
        private ReportService $reports,
        private ReportExportService $export,
    ) {}

    public function generate(Request $request)
    {
        $this->applyReportLocale($request);

        try {
            $range = $this->resolveReportRange($request);
            $type = (string) $request->query('type', 'summary');
            $ids = $this->parseTrackingIdList($request);
            ReportService::applyTimeLimit(max(1, count($ids)), $range['from'], $range['to']);

            // Large GPS payloads — raise memory for week windows.
            @ini_set('memory_limit', '512M');

            $report = $this->reports->generate(
                $request->user(),
                $type,
                $ids,
                $range['from'],
                $range['to'],
            );

            return $this->mobileSuccess($report)
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        } catch (\Throwable $e) {
            report($e);

            return $this->mobileError(
                (string) __('app.tracking.report_load_failed'),
                500,
                'report_failed',
            );
        }
    }

    public function export(Request $request): StreamedResponse|\Illuminate\Http\Response
    {
        $this->applyReportLocale($request);

        try {
            $range = $this->resolveReportRange($request);
            $type = (string) $request->query('type', 'summary');
            $format = (string) $request->query('format', 'csv');
            $ids = $this->parseTrackingIdList($request);
            ReportService::applyTimeLimit(max(1, count($ids)), $range['from'], $range['to'], forExport: true);
            @ini_set('memory_limit', '512M');

            $report = $this->reports->generate(
                $request->user(),
                $type,
                $ids,
                $range['from'],
                $range['to'],
                forExport: true,
            );

            return $this->export->export($report, $format);
        } catch (\Throwable $e) {
            report($e);

            abort(500, (string) __('app.tracking.report_export_failed'));
        }
    }

    /**
     * @return array{from: Carbon, to: Carbon|null}
     */
    private function resolveReportRange(Request $request): array
    {
        $fromInput = trim((string) $request->query('from', ''));
        $toInput = trim((string) $request->query('to', ''));
        $tz = config('app.timezone');

        if ($fromInput === '') {
            return ['from' => now()->subHours(24), 'to' => null];
        }

        $from = $this->parseReportDateTime($fromInput, $tz, true);
        $to = $toInput !== ''
            ? $this->parseReportDateTime($toInput, $tz, false)
            : $from->copy()->endOfDay();

        return HistoryRangeBounds::normalize($from, $to);
    }

    private function parseReportDateTime(string $value, string $tz, bool $start): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = Carbon::createFromFormat('Y-m-d', $value, $tz)->startOfDay();

            return $start ? $date : $date->copy()->endOfDay();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $value)) {
            return Carbon::parse($value, $tz);
        }

        return $start
            ? Carbon::parse($value, $tz)->startOfDay()
            : Carbon::parse($value, $tz)->endOfDay();
    }
}
