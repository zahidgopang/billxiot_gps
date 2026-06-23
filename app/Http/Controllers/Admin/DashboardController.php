<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(AdminDashboardService $dashboard)
    {
        $stats = $dashboard->getStats();

        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Throwable) {
            $dbOk = false;
        }

        return view('admin.dashboard', array_merge($stats, [
            'dashboardService' => $dashboard,
            'dbOk' => $dbOk,
        ]));
    }
}
