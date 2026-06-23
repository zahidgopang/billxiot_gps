<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Device;
use App\Models\DeviceStockSale;
use App\Models\User;
use App\Services\Authorization\TenantScopeService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, TenantScopeService $tenantScope)
    {
        $actor = $request->user();
        $tenantScope->ensureClientForManager($actor);
        $clientIds = $tenantScope->visibleClientIds($actor);

        $stats = [
            'clients' => count($clientIds),
            'users' => $tenantScope->scopeUsers(User::query(), $actor)->count(),
            'devices' => $tenantScope->scopeDevices(Device::query(), $actor)->count(),
        ];

        $clients = Client::query()->whereIn('id', $clientIds)->orderBy('name')->get();

        $recentPurchases = DeviceStockSale::query()
            ->with(['client'])
            ->whereIn('client_id', $clientIds)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('client.dashboard', compact('stats', 'clients', 'recentPurchases'));
    }
}
