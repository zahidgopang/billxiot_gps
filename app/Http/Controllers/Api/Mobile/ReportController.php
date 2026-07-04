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
        $range = $this->resolveReportRange($request);
        $type = (string) $request->query('type', 'summary');
        $ids = $this->parseTrackingIdList($request);

        $report = $this->reports->generate(
            $request->user(),
            $type,
            $ids,
            $range['from'],
            $range['to'],
        );

        return $this->mobileSuccess($report)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function export(Request $request): StreamedResponse|\Illuminate\Http\Response
    {
        $this->applyReportLocale($request);
        $range = $this->resolveReportRange($request);
        $type = (string) $request->query('type', 'summary');
        $format = (string) $request->query('format', 'csv');
        $ids = $this->parseTrackingIdList($request);

        $report = $this->reports->generate(
            $request->user(),
            $type,
            $ids,
            $range['from'],
            $range['to'],
        );

        return $this->export->export($report, $format);
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
