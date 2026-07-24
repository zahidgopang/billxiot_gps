<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Services\Tracking\GlobalTrackingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OdometerReportController extends Controller
{
    use ResolvesTrackingPanel;

    public function __construct(
        private GlobalTrackingService $tracking,
    ) {}

    public function index(Request $request): View
    {
        $panel = $this->resolvePanel($request);

        return view('tracking.odometer', $this->viewData($request, $panel, [
            'generateUrl' => route('tracking.reports.generate'),
            'exportUrl' => route('tracking.reports.export'),
            'lockedType' => 'odometer',
        ]));
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
}
