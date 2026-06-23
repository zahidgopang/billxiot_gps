<?php

namespace App\Services\Inventory;

use App\Models\Device;
use App\Models\DeviceInventorySummary;
use App\Models\DeviceStockOrder;
use App\Models\DeviceStockSale;
use App\Models\DeviceStockSaleItem;
use App\Models\InventoryFifoLayer;
use App\Models\InventoryMovement;
use App\Models\InventoryProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        private FifoAllocator $fifo,
    ) {}

    public function ensureProductForOrder(DeviceStockOrder $order): InventoryProduct
    {
        return InventoryProduct::query()->firstOrCreate([
            'device_type' => (string) $order->device_type,
            'brand' => $order->brand ?: null,
            'model' => $order->model ?: null,
        ]);
    }

    public function warehouseAvailable(int $productId): int
    {
        return (int) (DeviceInventorySummary::query()
            ->where('product_id', $productId)
            ->where('scope', 'warehouse')
            ->whereNull('scope_id')
            ->value('available_qty') ?? 0);
    }

    public function clientAvailable(int $productId, int $clientId): int
    {
        return (int) (DeviceInventorySummary::query()
            ->where('product_id', $productId)
            ->where('scope', 'client')
            ->where('scope_id', $clientId)
            ->value('available_qty') ?? 0);
    }

    public function recordPurchaseFromOrder(DeviceStockOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $product = $this->ensureProductForOrder($order);
            $qty = (int) $order->quantity;
            if ($qty < 1) {
                return;
            }

            $occurredAt = $order->created_at ?? now();

            InventoryMovement::create([
                'product_id' => $product->id,
                'scope' => 'warehouse',
                'scope_id' => null,
                'type' => 'purchase',
                'quantity' => $qty,
                'source_type' => 'device_stock_orders',
                'source_id' => (int) $order->id,
                'occurred_at' => $occurredAt,
                'created_by' => $order->created_by,
                'meta' => [
                    'order_code' => $order->order_code,
                    'unit_cost' => (float) $order->unit_cost,
                    'currency' => $order->currency,
                ],
            ]);

            InventoryFifoLayer::create([
                'product_id' => $product->id,
                'scope' => 'warehouse',
                'scope_id' => null,
                'received_at' => $occurredAt,
                'unit_cost' => (float) $order->unit_cost,
                'currency' => (string) ($order->currency ?: 'USD'),
                'remaining_qty' => $qty,
                'source_purchase_order_id' => (int) $order->id,
            ]);

            $this->applySummaryDelta($product->id, 'warehouse', null, [
                'total_purchased' => $qty,
                'available_qty' => $qty,
            ]);
        });
    }

    /**
     * Transfer stock from warehouse to client based on existing invoice item.
     */
    public function transferToClientFromSaleItem(DeviceStockSale $sale, DeviceStockSaleItem $item, DeviceStockOrder $order): void
    {
        DB::transaction(function () use ($sale, $item, $order) {
            $product = $this->ensureProductForOrder($order);
            $qty = (int) $item->quantity;
            if ($qty < 1) {
                return;
            }

            $available = $this->warehouseAvailable((int) $product->id);
            if ($available < $qty) {
                throw ValidationException::withMessages([
                    'quantity' => ["Warehouse stock insufficient (available {$available})."],
                ]);
            }

            $occurredAt = $sale->issued_at ?? now();
            $allocs = $this->fifo->allocate('warehouse', null, (int) $product->id, $qty);

            // Decrement warehouse FIFO layers; create client layers per allocation.
            foreach ($allocs as $a) {
                /** @var \App\Models\InventoryFifoLayer $layer */
                $layer = $a['layer'];
                $take = (int) $a['qty'];

                $layer->remaining_qty = max(0, (int) $layer->remaining_qty - $take);
                $layer->save();

                InventoryFifoLayer::create([
                    'product_id' => $product->id,
                    'scope' => 'client',
                    'scope_id' => (int) $sale->client_id,
                    'received_at' => $layer->received_at,
                    'unit_cost' => (float) $layer->unit_cost,
                    'currency' => (string) ($layer->currency ?: 'USD'),
                    'remaining_qty' => $take,
                    'source_purchase_order_id' => $layer->source_purchase_order_id,
                ]);
            }

            InventoryMovement::create([
                'product_id' => $product->id,
                'scope' => 'warehouse',
                'scope_id' => null,
                'type' => 'sale_transfer',
                'quantity' => -$qty,
                'source_type' => 'device_stock_sales',
                'source_id' => (int) $sale->id,
                'occurred_at' => $occurredAt,
                'created_by' => $sale->created_by,
                'meta' => ['sale_item_id' => (int) $item->id],
            ]);

            InventoryMovement::create([
                'product_id' => $product->id,
                'scope' => 'client',
                'scope_id' => (int) $sale->client_id,
                'type' => 'sale_transfer',
                'quantity' => $qty,
                'source_type' => 'device_stock_sales',
                'source_id' => (int) $sale->id,
                'occurred_at' => $occurredAt,
                'created_by' => $sale->created_by,
                'meta' => ['sale_item_id' => (int) $item->id],
            ]);

            $this->applySummaryDelta($product->id, 'warehouse', null, [
                'total_sold' => $qty,
                'available_qty' => -$qty,
            ]);

            // Client received stock increases available.
            $this->applySummaryDelta($product->id, 'client', (int) $sale->client_id, [
                'available_qty' => $qty,
            ]);
        });
    }

    /**
     * Consume one unit from client scope when installing a device.
     */
    public function consumeForInstall(int $clientId, string $deviceType, int $deviceId, ?int $actorId = null): void
    {
        DB::transaction(function () use ($clientId, $deviceType, $deviceId, $actorId) {
            // Choose a product for this type (brand/model not tracked on device yet).
            $productId = (int) (InventoryProduct::query()
                ->where('device_type', $deviceType)
                ->orderBy('id')
                ->value('id') ?? 0);

            if (! $productId) {
                throw ValidationException::withMessages([
                    'device_type' => ['No inventory product configured for this device type.'],
                ]);
            }

            $available = $this->clientAvailable($productId, $clientId);
            if ($available < 1) {
                throw ValidationException::withMessages([
                    'device_type' => ['No client stock available for this device type.'],
                ]);
            }

            $allocs = $this->fifo->allocate('client', $clientId, $productId, 1);
            foreach ($allocs as $a) {
                $layer = $a['layer'];
                $layer->remaining_qty = max(0, (int) $layer->remaining_qty - 1);
                $layer->save();
            }

            InventoryMovement::create([
                'product_id' => $productId,
                'scope' => 'client',
                'scope_id' => $clientId,
                'type' => 'install',
                'quantity' => -1,
                'source_type' => Device::class,
                'source_id' => $deviceId,
                'occurred_at' => now(),
                'created_by' => $actorId,
                'meta' => ['device_id' => $deviceId],
            ]);

            $this->applySummaryDelta($productId, 'client', $clientId, [
                'total_installed' => 1,
                'available_qty' => -1,
            ]);
        });
    }

    /**
     * Reverse one install unit when a subscription is cancelled (if install movement exists).
     */
    public function returnFromSubscriptionCancel(int $clientId, string $deviceType, int $deviceId, ?int $actorId = null): void
    {
        DB::transaction(function () use ($clientId, $deviceType, $deviceId, $actorId) {
            $existingReturn = InventoryMovement::query()
                ->where('scope', 'client')
                ->where('scope_id', $clientId)
                ->where('type', 'return')
                ->where('source_type', Device::class)
                ->where('source_id', $deviceId)
                ->exists();

            if ($existingReturn) {
                return;
            }

            $install = InventoryMovement::query()
                ->where('scope', 'client')
                ->where('scope_id', $clientId)
                ->where('type', 'install')
                ->where('source_type', Device::class)
                ->where('source_id', $deviceId)
                ->orderByDesc('id')
                ->first();

            if (! $install) {
                return;
            }

            $productId = (int) $install->product_id;

            InventoryMovement::create([
                'product_id' => $productId,
                'scope' => 'client',
                'scope_id' => $clientId,
                'type' => 'return',
                'quantity' => 1,
                'source_type' => Device::class,
                'source_id' => $deviceId,
                'occurred_at' => now(),
                'created_by' => $actorId,
                'meta' => ['reason' => 'subscription_cancelled', 'device_id' => $deviceId],
            ]);

            $unitCost = (float) ($install->meta['unit_cost'] ?? 0);
            if ($unitCost <= 0) {
                $unitCost = (float) (InventoryFifoLayer::query()
                    ->where('product_id', $productId)
                    ->where('scope', 'client')
                    ->where('scope_id', $clientId)
                    ->orderByDesc('id')
                    ->value('unit_cost') ?? 0);
            }

            InventoryFifoLayer::create([
                'product_id' => $productId,
                'scope' => 'client',
                'scope_id' => $clientId,
                'remaining_qty' => 1,
                'unit_cost' => $unitCost,
                'currency' => 'USD',
                'received_at' => now(),
            ]);

            $this->applySummaryDelta($productId, 'client', $clientId, [
                'total_installed' => -1,
                'total_returned' => 1,
                'available_qty' => 1,
            ]);
        });
    }

    /**
     * Atomic summary delta application.
     *
     * @param  array{total_purchased?:int,total_sold?:int,total_installed?:int,total_returned?:int,available_qty?:int}  $delta
     */
    private function applySummaryDelta(int $productId, string $scope, ?int $scopeId, array $delta): void
    {
        DeviceInventorySummary::query()->updateOrCreate(
            ['product_id' => $productId, 'scope' => $scope, 'scope_id' => $scopeId],
            []
        );

        $row = DeviceInventorySummary::query()
            ->where('product_id', $productId)
            ->where('scope', $scope)
            ->where('scope_id', $scopeId)
            ->lockForUpdate()
            ->first();

        if (! $row) {
            return;
        }

        foreach (['total_purchased', 'total_sold', 'total_installed', 'total_returned', 'available_qty'] as $field) {
            if (array_key_exists($field, $delta)) {
                $row->{$field} = (int) $row->{$field} + (int) $delta[$field];
                if ($field !== 'available_qty') {
                    $row->{$field} = max(0, (int) $row->{$field});
                }
            }
        }

        $row->save();
    }
}

