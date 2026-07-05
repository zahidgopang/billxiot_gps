<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Concerns\AppliesReportLocale;
use App\Http\Concerns\ResolvesHistoryDateRange;
use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\Reports\ReportExportService;
use App\Services\Tracking\Reports\ReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use AppliesReportLocale;
    use ResolvesHistoryDateRange;
    use ResolvesTrackingPanel;

    public function __construct(
        private GlobalTrackingService $tracking,
        private ReportService $reports,
        private ReportExportService $export,
    ) {}

    public function index(Request $request): View
    {
        $panel = $this->resolvePanel($request);

        return view('tracking.reports', $this->viewData($request, $panel, [
            'generateUrl' => route("{$panel}.tracking.reports.generate"),
            'exportUrl' => route("{$panel}.tracking.reports.export"),
        ]));
    }

    public function generate(Request $request): JsonResponse
    {
        $this->applyReportLocale($request);
        set_time_limit(120);

        $ids = $this->resolveReportDeviceIds($request);
        if ($ids === []) {
            return $this->noStoreJson([
                'success' => false,
                'message' => (string) __('app.tracking.report_select_vehicle'),
                'type' => (string) $request->input('type', $request->query('type', 'summary')),
                'devices' => [],
            ], 422);
        }

        try {
            $range = $this->resolveReportRange($request);
            $type = (string) $request->input('type', $request->query('type', 'summary'));

            $report = $this->reports->generate(
                $request->user(),
                $type,
                $ids,
                $range['from'],
                $range['to'],
            );

            return $this->noStoreJson(array_merge(['success' => true], $report));
        } catch (\Throwable $e) {
            report($e);

            return $this->noStoreJson([
                'success' => false,
                'message' => (string) __('app.tracking.report_load_failed'),
                'type' => (string) $request->input('type', $request->query('type', 'summary')),
                'devices' => [],
            ], 500);
        }
    }

    public function export(Request $request): StreamedResponse|\Illuminate\Http\Response
    {
        $this->applyReportLocale($request);
        set_time_limit(180);

        $ids = $this->resolveReportDeviceIds($request);
        if ($ids === []) {
            abort(422, (string) __('app.tracking.report_select_vehicle'));
        }

        $range = $this->resolveReportRange($request);
        $type = (string) $request->input('type', $request->query('type', 'summary'));
        $format = (string) $request->input('format', $request->query('format', 'csv'));

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
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function viewData(Request $request, string $panel, array $extra = []): array
    {
        return array_merge([
            'panel' => $panel,
            'layout' => $this->layoutForPanel($panel),
            'vehicles' => $this->tracking->listItemsForActor($request->user()),
            'hubRoutes' => $this->trackingHubRoutes($panel),
            'stateColors' => \App\Services\Mobile\VehicleStatusSpec::STATE_COLORS,
        ], $extra);
    }

    /**
     * @return array{from: Carbon, to: Carbon|null}
     */
    private function resolveReportRange(Request $request): array
    {
        $fromInput = trim((string) $request->input('from', $request->query('from', '')));
        $toInput = trim((string) $request->input('to', $request->query('to', '')));
        $tz = config('app.timezone');

        if ($fromInput === '') {
            return ['from' => now()->subHours(24), 'to' => null];
        }

        $from = $this->parseReportDateTime($fromInput, $tz, true);
        $to = $toInput !== ''
            ? $this->parseReportDateTime($toInput, $tz, false)
            : $from->copy()->endOfDay();

        return \App\Support\Tracking\HistoryRangeBounds::normalize($from, $to);
    }

    private function parseReportDateTime(string $value, string $tz, bool $start): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = Carbon::createFromFormat('Y-m-d', $value, $tz)->startOfDay();

            return $start ? $date : $date->copy()->endOfDay();
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $value)) {
            return Carbon::parse(str_replace('T', ' ', $value), $tz);
        }

        return $start
            ? Carbon::parse($value, $tz)->startOfDay()
            : Carbon::parse($value, $tz)->endOfDay();
    }
}
