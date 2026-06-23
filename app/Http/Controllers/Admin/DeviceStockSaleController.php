<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\DeviceStockOrder;
use App\Models\DeviceStockSale;
use App\Models\DeviceStockSaleItem;
use App\Services\AdminAuditService;
use App\Services\Inventory\InventoryService;
use App\Services\Stock\ClientStockBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeviceStockSaleController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private AdminAuditService $audit,
        private ClientStockBalanceService $clientStock,
        private InventoryService $inventory,
    ) {}

    public function index(Request $request)
    {
        $this->authorizePermission('stock.manage');

        $q = DeviceStockSale::query()->with(['client'])->orderByDesc('id');

        if ($search = $request->query('q')) {
            $q->where('invoice_no', 'like', "%{$search}%");
        }

        if ($clientId = $request->query('client_id')) {
            $q->where('client_id', (int) $clientId);
        }

        $sales = $q->paginate(15)->withQueryString();

        $clients = Client::query()->orderBy('name')->get(['id', 'name']);

        $filterClientId = $request->query('client_id') ? (int) $request->query('client_id') : null;
        $filterClient = $filterClientId
            ? $clients->firstWhere('id', $filterClientId)
            : null;
        $clientStockBalance = $filterClientId
            ? $this->clientStock->balanceForClient($filterClientId)
            : null;

        return view('admin.device-stock-sales.index', compact(
            'sales',
            'clients',
            'filterClientId',
            'filterClient',
            'clientStockBalance',
        ));
    }

    public function create()
    {
        $this->authorizePermission('stock.manage');

        $clients = Client::query()->orderBy('name')->get(['id', 'name']);

        $orders = DeviceStockOrder::query()
            ->hasAvailableStock()
            ->where('condition', '!=', 'faulty')
            ->orderByDesc('id')
            ->get();

        $selectedClientId = old('client_id') ? (int) old('client_id') : null;
        $clientStockBalance = $selectedClientId
            ? $this->clientStock->balanceForClient($selectedClientId)
            : null;

        return view('admin.device-stock-sales.create', compact('clients', 'orders', 'clientStockBalance', 'selectedClientId'));
    }

    public function store(Request $request)
    {
        $this->authorizePermission('stock.manage');

        $data = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'stock_order_id' => ['required', 'integer', Rule::exists('device_stock_orders', 'id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $sale = DB::transaction(function () use ($request, $data) {
                /** @var DeviceStockOrder $order */
                $order = DeviceStockOrder::query()
                    ->lockForUpdate()
                    ->findOrFail((int) $data['stock_order_id']);

                $available = $order->availableQuantity();
                $qty = (int) $data['quantity'];

                if ($order->condition === 'faulty' || $order->status === 'repair') {
                    throw ValidationException::withMessages([
                        'stock_order_id' => [__('app.admin.stock_sales.cannot_sell_faulty')],
                    ]);
                }

                if ($available < 1) {
                    throw ValidationException::withMessages([
                        'stock_order_id' => [__('app.admin.stock_sales.no_stock_available')],
                    ]);
                }

                if ($qty > $available) {
                    throw ValidationException::withMessages([
                        'quantity' => [__('app.admin.stock_sales.quantity_exceeds_available', ['max' => $available])],
                    ]);
                }

                $unitPrice = round((float) $order->selling_price, 2);
                $unitCost = round((float) $order->unit_cost, 2);
                $lineTotal = round($unitPrice * $qty, 2);

                $sale = DeviceStockSale::create([
                    'client_id' => (int) $data['client_id'],
                    'currency' => $order->currency,
                    'status' => 'issued',
                    'issued_at' => now()->toDateString(),
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                $saleItem = DeviceStockSaleItem::create([
                    'sale_id' => $sale->id,
                    'stock_order_id' => $order->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ]);

                $order->sold_quantity = (int) ($order->sold_quantity ?? 0) + $qty;
                $order->status = $order->sold_quantity >= $order->quantity ? 'sold' : 'partial';
                $order->save();

                // Central inventory: transfer warehouse → client (FIFO) while keeping invoice history unchanged.
                $this->inventory->transferToClientFromSaleItem($sale, $saleItem, $order);

                return $sale->load(['client', 'items.stockOrder']);
            });
        } catch (ValidationException $e) {
            throw $e;
        }

        $item = $sale->items->first();

        $this->audit->logCreated($sale, "stock sale invoice {$sale->invoice_no}", [
            AdminAuditService::PROP_CATEGORY => 'stock',
            'invoice_no' => $sale->invoice_no,
            'client_id' => $sale->client_id,
            'quantity' => $item?->quantity,
            'line_total' => $item?->line_total,
        ]);

        return redirect()
            ->route('admin.device-stock-sales.show', $sale)
            ->with('success', __('app.admin.stock_sales.created'));
    }

    public function show(DeviceStockSale $device_stock_sale)
    {
        $this->authorizePermission('stock.manage');

        $device_stock_sale->load(['client', 'creator', 'items.stockOrder']);

        return view('admin.device-stock-sales.show', ['sale' => $device_stock_sale]);
    }
}
