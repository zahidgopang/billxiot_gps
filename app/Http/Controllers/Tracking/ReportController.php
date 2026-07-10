<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Concerns\AppliesReportLocale;
use App\Http\Concerns\ResolvesHistoryDateRange;
use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\Reports\ReportExportService;
use App\Services\Tracking\Reports\ReportService;
use App\Support\Tracking\HistoryRangeBounds;
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
            'generateUrl' => route('tracking.reports.generate'),
            'exportUrl' => route('tracking.reports.export'),
        ]));
    }

    public function generate(Request $request): JsonResponse
    {
        $this->applyReportLocale($request);

        $ids = $this->resolveReportDeviceIds($request);
        set_time_limit(min(300, 45 + count($ids) * 6));

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
            $nonce = (string) $request->input('_nonce', '');

            // Release session lock so parallel report requests (one device each) are not serialized.
            $request->session()->save();

            $report = $this->reports->generate(
                $request->user(),
                $type,
                $ids,
                $range['from'],
                $range['to'],
            );

            return $this->noStoreJson(array_merge(
                ['success' => true, '_nonce' => $nonce],
                $report,
            ));
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
        set_time_limit(300);

        $ids = $this->resolveReportDeviceIds($request);
        if ($ids === []) {
            abort(422, (string) __('app.tracking.report_select_vehicle'));
        }

        try {
            $range = $this->resolveReportRange($request);
            $type = (string) $request->input('type', $request->query('type', 'summary'));
            $format = (string) $request->input('format', $request->query('format', 'csv'));

            $request->session()->save();

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
            'trackingUi' => $this->trackingUiFor($request->user()),
            'stateColors' => \App\Services\Mobile\VehicleStatusSpec::STATE_COLORS,
        ], $extra);
    }

    /**
     * Reports honour datetime-local inputs; date-only filters expand to full calendar days.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    private function resolveReportRange(Request $request): array
    {
        $fromInput = trim((string) ($request->query('from', $request->input('from', ''))));
        $toInput = trim((string) ($request->query('to', $request->input('to', ''))));
        $tz = (string) config('app.timezone');

        if ($fromInput === '') {
            return [
                'from' => now()->subHours(24),
                'to' => now(),
            ];
        }

        $from = $this->parseHistoryDate($fromInput, $tz, true);
        $to = $toInput !== ''
            ? $this->parseHistoryDate($toInput, $tz, false)
            : ($this->isDateOnlyInput($fromInput) ? $from->copy()->endOfDay() : now());

        if ($this->isDateOnlyInput($fromInput) && ($toInput === '' || $this->isDateOnlyInput($toInput))) {
            return HistoryRangeBounds::normalize($from, $to);
        }

        if ($to->lessThan($from)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        return ['from' => $from, 'to' => $to];
    }
}
