<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Services\Billing\ProfitLossReportService;
use Illuminate\Http\Request;

class ProfitLossReportController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private ProfitLossReportService $reports,
    ) {}

    public function index(Request $request)
    {
        $this->authorizePermission('billing.view');

        $metrics = $this->reports->forActor($request->user());

        return view('admin.reports.profit-loss', [
            'metrics' => $metrics,
            'panel' => $this->panelPrefix(),
        ]);
    }
}
