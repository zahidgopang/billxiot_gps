<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(AdminDashboardService $dashboard)
    {
        try {
            $stats = $dashboard->getStats();
        } catch (\Throwable $e) {
            report($e);
            $stats = [
                'totalUsers' => 0,
                'totalAdmins' => 0,
                'totalDevices' => 0,
                'activeDevices' => 0,
                'inactiveDevices' => 0,
                'blockedDevices' => 0,
                'onlineNow' => 0,
                'movingNow' => 0,
                'offlineDevices' => 0,
                'activeSubscriptions' => 0,
                'totalSubscriptions' => 0,
                'expiredSubscriptions' => 0,
                'alertsToday' => 0,
                'alertsWeek' => 0,
                'geofenceCount' => 0,
                'dataPointsToday' => 0,
                'dataPointsWeek' => 0,
                'unassignedDevices' => 0,
                'pendingContacts' => 0,
                'lastGpsAt' => null,
                'gpsLive' => false,
                'userGrowth' => ['value' => 0, 'positive' => true, 'label' => 'no change'],
                'deviceGrowth' => ['value' => 0, 'positive' => true, 'label' => 'no change'],
                'onlineChange' => ['value' => 0, 'positive' => true, 'label' => 'no change'],
                'chart' => ['labels' => [], 'gpsPings' => [], 'activeDevices' => []],
                'recentActivities' => collect(),
                'recentDevices' => collect(),
                'eventsByType' => collect(),
            ];
        }

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
