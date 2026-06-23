<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceStockOrder;
use Illuminate\Support\Facades\DB;
use App\Services\AdminAuditService;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceStockController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private AdminAuditService $audit,
        private InventoryService $inventory,
    ) {}

    public function index(Request $request)
    {
        return $this->renderIndex($request, repairList: false);
    }

    public function repairs(Request $request)
    {
        return $this->renderIndex($request, repairList: true);
    }

    private function renderIndex(Request $request, bool $repairList): \Illuminate\View\View
    {
        $this->authorizePermission('stock.view');

        $q = DeviceStockOrder::query()->with('creator');

        if ($repairList) {
            $q->where('condition', 'faulty');
        }

        if ($search = $request->query('q')) {
            $q->where(function ($w) use ($search) {
                $w->where('order_code', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('supplier', 'like', "%{$search}%")
                    ->orWhere('purchase_order_ref', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        if ($deviceType = $request->query('device_type')) {
            $q->where('device_type', $deviceType);
        }

        if (! $repairList && ($condition = $request->query('condition'))) {
            $q->where('condition', $condition);
        }

        $orders = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $statsBase = $repairList
            ? DeviceStockOrder::query()->where('condition', 'faulty')
            : DeviceStockOrder::query();

        $stats = [
            'total_orders' => (clone $statsBase)->count(),
            'total_units' => (int) (clone $statsBase)->sum('quantity'),
            'in_stock_units' => (int) (clone $statsBase)->inStock()->selectRaw('SUM(GREATEST(0, quantity - sold_quantity)) as total')->value('total'),
            'inventory_cost' => (float) (clone $statsBase)->inStock()
                ->selectRaw('SUM('.DeviceStockOrder::inventoryCostSql().') as total')->value('total'),
            'inventory_retail' => (float) (clone $statsBase)->inStock()
                ->selectRaw('SUM('.DeviceStockOrder::inventoryRetailSql().') as total')->value('total'),
        ];

        $warehouseByType = DB::table('device_inventory_summary')
            ->join('inventory_products', 'inventory_products.id', '=', 'device_inventory_summary.product_id')
            ->where('device_inventory_summary.scope', 'warehouse')
            ->whereNull('device_inventory_summary.scope_id')
            ->groupBy('inventory_products.device_type')
            ->selectRaw('inventory_products.device_type as device_type,
                SUM(device_inventory_summary.total_purchased) as total_purchased,
                SUM(device_inventory_summary.total_sold) as total_sold,
                SUM(device_inventory_summary.total_installed) as total_installed,
                SUM(device_inventory_summary.available_qty) as available_qty')
            ->orderBy('inventory_products.device_type')
            ->get();

        return view('admin.device-stock.index', compact('orders', 'stats', 'repairList', 'warehouseByType'));
    }

    public function show(DeviceStockOrder $device_stock)
    {
        $this->authorizePermission('stock.view');

        $device_stock->load('creator');

        return view('admin.device-stock.show', ['order' => $device_stock]);
    }

    public function create()
    {
        $this->authorizePermission('stock.manage');

        return view('admin.device-stock.create', [
            'order' => new DeviceStockOrder([
                'quantity' => 1,
                'condition' => 'new',
                'status' => 'in_stock',
                'currency' => 'USD',
                'device_type' => 'gps_tracker',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('stock.manage');

        $data = $this->validateOrder($request);
        $data['created_by'] = $request->user()->id;

        $order = DeviceStockOrder::create($data);

        // Central inventory movement (+warehouse) while keeping purchase order history.
        $this->inventory->recordPurchaseFromOrder($order);

        $this->audit->logCreated($order, "stock order {$order->order_code}", [
            AdminAuditService::PROP_CATEGORY => 'stock',
            'quantity' => $order->quantity,
        ]);

        return redirect()
            ->route('admin.device-stock.show', $order)
            ->with('success', __('app.admin.stock.created'));
    }

    public function edit(DeviceStockOrder $device_stock)
    {
        $this->authorizePermission('stock.manage');

        return view('admin.device-stock.edit', ['order' => $device_stock]);
    }

    public function update(Request $request, DeviceStockOrder $device_stock)
    {
        $this->authorizePermission('stock.manage');

        $data = $this->validateOrder($request);
        $device_stock->update($data);

        $this->audit->logUpdated($device_stock, "stock order {$device_stock->order_code}", [
            AdminAuditService::PROP_CATEGORY => 'stock',
        ]);

        return redirect()
            ->route('admin.device-stock.show', $device_stock)
            ->with('success', __('app.admin.stock.updated'));
    }

    public function destroy(DeviceStockOrder $device_stock)
    {
        $this->authorizePermission('stock.manage');

        $code = $device_stock->order_code;
        $device_stock->delete();

        $this->audit->log('deleted', "Deleted stock order {$code}", null, [
            AdminAuditService::PROP_CATEGORY => 'stock',
            'order_code' => $code,
        ]);

        return redirect()
            ->route('admin.device-stock.index')
            ->with('success', __('app.admin.stock.deleted'));
    }

    private function validateOrder(Request $request): array
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'device_type' => ['required', Rule::in(array_keys(Device::DEVICE_TYPES))],
            'brand' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'condition' => ['required', Rule::in(array_keys(DeviceStockOrder::CONDITIONS))],
            'status' => ['required', Rule::in(array_keys(DeviceStockOrder::STATUSES))],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'warehouse_location' => ['nullable', 'string', 'max:120'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'purchase_order_ref' => ['nullable', 'string', 'max:64'],
            'purchased_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        // Enforce repair queue rule:
        // - Faulty condition must always be in repair status.
        // - Repair status is only allowed when condition is faulty.
        if (($validated['condition'] ?? null) === 'faulty') {
            $validated['status'] = 'repair';
        } elseif (($validated['status'] ?? null) === 'repair') {
            $validated['status'] = 'in_stock';
        }

        return $validated;
    }
}
