<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Stock\ClientStockBalanceService;
use Illuminate\Http\Request;

class ClientStockBalanceController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private ClientStockBalanceService $balances,
    ) {}

    public function show(Request $request, ?Client $client = null)
    {
        $this->authorizePermission('devices.view');

        if ($client) {
            $this->authorizeVisibleClient($request, (int) $client->id);
            $clientId = (int) $client->id;
        } else {
            $clientId = $this->tenantScope()->ensureClientForManager($request->user());
        }

        return response()->json($this->balances->balanceForClient($clientId));
    }
}
