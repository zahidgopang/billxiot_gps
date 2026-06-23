<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\DeviceStockSale;
use App\Services\Authorization\TenantScopeService;
use Illuminate\Http\Request;

class PurchaseHistoryController extends Controller
{
    public function index(Request $request, TenantScopeService $tenantScope)
    {
        $actor = $request->user();
        $tenantScope->ensureClientForManager($actor);
        $clientIds = $tenantScope->visibleClientIds($actor);

        $q = DeviceStockSale::query()
            ->with(['client'])
            ->whereIn('client_id', $clientIds)
            ->orderByDesc('id');

        if ($search = $request->query('q')) {
            $q->where('invoice_no', 'like', "%{$search}%");
        }

        $sales = $q->paginate(15)->withQueryString();

        return view('client.purchases.index', compact('sales'));
    }

    public function show(Request $request, TenantScopeService $tenantScope, DeviceStockSale $sale)
    {
        $actor = $request->user();
        $tenantScope->ensureClientForManager($actor);
        $clientIds = $tenantScope->visibleClientIds($actor);

        abort_unless(in_array((int) $sale->client_id, $clientIds, true), 404);

        $sale->load(['client', 'creator', 'items.stockOrder']);

        return view('client.purchases.show', compact('sale'));
    }
}

