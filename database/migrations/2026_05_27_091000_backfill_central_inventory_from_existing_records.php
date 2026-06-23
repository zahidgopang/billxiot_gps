<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('device_stock_orders')
            || ! Schema::hasTable('device_stock_sales')
            || ! Schema::hasTable('device_stock_sale_items')
            || ! Schema::hasTable('client_devices')
            || ! Schema::hasTable('inventory_products')
            || ! Schema::hasTable('inventory_movements')
            || ! Schema::hasTable('inventory_fifo_layers')
            || ! Schema::hasTable('device_inventory_summary')) {
            return;
        }

        DB::transaction(function () {
            // 1) Create products from orders (device_type + brand + model).
            $orderRows = DB::table('device_stock_orders')
                ->select('device_type', 'brand', 'model')
                ->distinct()
                ->get();

            foreach ($orderRows as $r) {
                DB::table('inventory_products')->updateOrInsert(
                    [
                        'device_type' => (string) $r->device_type,
                        'brand' => $r->brand !== null ? (string) $r->brand : null,
                        'model' => $r->model !== null ? (string) $r->model : null,
                    ],
                    [
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            // product map: key => id
            $products = DB::table('inventory_products')
                ->get(['id', 'device_type', 'brand', 'model'])
                ->mapWithKeys(function ($p) {
                    $key = implode('|', [
                        (string) $p->device_type,
                        (string) ($p->brand ?? ''),
                        (string) ($p->model ?? ''),
                    ]);
                    return [$key => (int) $p->id];
                });

            // Helper to upsert summary row and recompute available.
            $touchSummary = function (int $productId, string $scope, ?int $scopeId) {
                DB::table('device_inventory_summary')->updateOrInsert(
                    ['product_id' => $productId, 'scope' => $scope, 'scope_id' => $scopeId],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            };

            // 2) Purchases → warehouse (+) and FIFO layers
            $orders = DB::table('device_stock_orders')->orderBy('id')->get();
            foreach ($orders as $o) {
                $key = implode('|', [
                    (string) $o->device_type,
                    (string) ($o->brand ?? ''),
                    (string) ($o->model ?? ''),
                ]);
                $productId = (int) ($products[$key] ?? 0);
                if (! $productId) {
                    continue;
                }

                $qty = (int) $o->quantity;
                if ($qty < 1) {
                    continue;
                }

                $occurredAt = $o->created_at ?: now();

                DB::table('inventory_movements')->insert([
                    'product_id' => $productId,
                    'scope' => 'warehouse',
                    'scope_id' => null,
                    'type' => 'purchase',
                    'quantity' => $qty,
                    'source_type' => 'device_stock_orders',
                    'source_id' => (int) $o->id,
                    'occurred_at' => $occurredAt,
                    'created_by' => $o->created_by,
                    'meta' => json_encode([
                        'order_code' => $o->order_code,
                        'unit_cost' => $o->unit_cost,
                        'currency' => $o->currency,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('inventory_fifo_layers')->insert([
                    'product_id' => $productId,
                    'scope' => 'warehouse',
                    'scope_id' => null,
                    'received_at' => $occurredAt,
                    'unit_cost' => (float) ($o->unit_cost ?? 0),
                    'currency' => (string) ($o->currency ?? 'USD'),
                    'remaining_qty' => $qty,
                    'source_purchase_order_id' => (int) $o->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $touchSummary($productId, 'warehouse', null);
            }

            // 3) Sales (existing) → treat as warehouse→client transfer.
            $saleItems = DB::table('device_stock_sale_items')
                ->join('device_stock_sales', 'device_stock_sales.id', '=', 'device_stock_sale_items.sale_id')
                ->join('device_stock_orders', 'device_stock_orders.id', '=', 'device_stock_sale_items.stock_order_id')
                ->where('device_stock_sales.status', 'issued')
                ->orderBy('device_stock_sale_items.id')
                ->select([
                    'device_stock_sale_items.id as item_id',
                    'device_stock_sale_items.quantity as qty',
                    'device_stock_sales.id as sale_id',
                    'device_stock_sales.client_id as client_id',
                    'device_stock_sales.issued_at as issued_at',
                    'device_stock_sales.created_by as created_by',
                    'device_stock_orders.device_type as device_type',
                    'device_stock_orders.brand as brand',
                    'device_stock_orders.model as model',
                ])
                ->get();

            foreach ($saleItems as $it) {
                $key = implode('|', [
                    (string) $it->device_type,
                    (string) ($it->brand ?? ''),
                    (string) ($it->model ?? ''),
                ]);
                $productId = (int) ($products[$key] ?? 0);
                if (! $productId) {
                    continue;
                }

                $qty = (int) $it->qty;
                if ($qty < 1) {
                    continue;
                }

                $occurredAt = $it->issued_at ? ((string) $it->issued_at . ' 00:00:00') : now();

                // Warehouse out
                DB::table('inventory_movements')->insert([
                    'product_id' => $productId,
                    'scope' => 'warehouse',
                    'scope_id' => null,
                    'type' => 'sale_transfer',
                    'quantity' => -$qty,
                    'source_type' => 'device_stock_sales',
                    'source_id' => (int) $it->sale_id,
                    'occurred_at' => $occurredAt,
                    'created_by' => $it->created_by,
                    'meta' => json_encode(['sale_item_id' => (int) $it->item_id]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Client in
                DB::table('inventory_movements')->insert([
                    'product_id' => $productId,
                    'scope' => 'client',
                    'scope_id' => (int) $it->client_id,
                    'type' => 'sale_transfer',
                    'quantity' => $qty,
                    'source_type' => 'device_stock_sales',
                    'source_id' => (int) $it->sale_id,
                    'occurred_at' => $occurredAt,
                    'created_by' => $it->created_by,
                    'meta' => json_encode(['sale_item_id' => (int) $it->item_id]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $touchSummary($productId, 'warehouse', null);
                $touchSummary($productId, 'client', (int) $it->client_id);
            }

            // 4) Installations: infer by device.category (device_type only; brand/model unknown)
            // We map installs to product where brand/model NULL for that device_type when possible,
            // otherwise to any product matching device_type.
            $deviceTypeToProduct = DB::table('inventory_products')
                ->whereNull('brand')
                ->whereNull('model')
                ->get(['id', 'device_type'])
                ->mapWithKeys(fn ($p) => [(string) $p->device_type => (int) $p->id]);

            $installs = DB::table('client_devices')
                ->join('tc_devices', 'tc_devices.id', '=', 'client_devices.device_id')
                ->select([
                    'client_devices.client_id as client_id',
                    'client_devices.device_id as device_id',
                    'tc_devices.category as device_type',
                    'client_devices.created_at as created_at',
                ])
                ->orderBy('client_devices.id')
                ->get();

            foreach ($installs as $ins) {
                $deviceType = (string) ($ins->device_type ?? '');
                if ($deviceType === '') {
                    continue;
                }

                $productId = (int) ($deviceTypeToProduct[$deviceType] ?? 0);
                if (! $productId) {
                    // fallback: first product matching device_type
                    $productId = (int) (DB::table('inventory_products')->where('device_type', $deviceType)->value('id') ?? 0);
                }
                if (! $productId) {
                    continue;
                }

                DB::table('inventory_movements')->insert([
                    'product_id' => $productId,
                    'scope' => 'client',
                    'scope_id' => (int) $ins->client_id,
                    'type' => 'install',
                    'quantity' => -1,
                    'source_type' => 'device',
                    'source_id' => (int) $ins->device_id,
                    'occurred_at' => $ins->created_at ?: now(),
                    'created_by' => null,
                    'meta' => json_encode(['device_id' => (int) $ins->device_id]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $touchSummary($productId, 'client', (int) $ins->client_id);
            }

            // 5) Recompute summaries from movements
            $summaries = DB::table('inventory_movements')
                ->selectRaw("product_id, scope, scope_id,
                    SUM(CASE WHEN type = 'purchase' THEN quantity ELSE 0 END) as total_purchased,
                    SUM(CASE WHEN type = 'sale_transfer' AND quantity < 0 AND scope = 'warehouse' THEN (-quantity) ELSE 0 END) as total_sold,
                    SUM(CASE WHEN type = 'install' AND quantity < 0 THEN (-quantity) ELSE 0 END) as total_installed,
                    SUM(CASE WHEN type = 'return' AND quantity > 0 THEN quantity ELSE 0 END) as total_returned,
                    SUM(quantity) as available_qty")
                ->groupBy('product_id', 'scope', 'scope_id')
                ->get();

            foreach ($summaries as $s) {
                DB::table('device_inventory_summary')->updateOrInsert(
                    ['product_id' => (int) $s->product_id, 'scope' => (string) $s->scope, 'scope_id' => $s->scope_id !== null ? (int) $s->scope_id : null],
                    [
                        'total_purchased' => max(0, (int) $s->total_purchased),
                        'total_sold' => max(0, (int) $s->total_sold),
                        'total_installed' => max(0, (int) $s->total_installed),
                        'total_returned' => max(0, (int) $s->total_returned),
                        'available_qty' => (int) $s->available_qty,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_movements')) {
            return;
        }

        // Best-effort rollback: clear generated inventory rows.
        DB::table('device_inventory_summary')->truncate();
        DB::table('inventory_fifo_layers')->truncate();
        DB::table('inventory_movements')->truncate();
        DB::table('inventory_products')->truncate();
    }
};

