<?php

namespace App\Services\Billing;

use App\Models\Device;
use App\Models\DeviceStockSaleItem;
use App\Models\InventoryFifoLayer;
use App\Models\InventoryMovement;
use App\Models\InventoryProduct;

class DeviceCostResolver
{
    /**
     * Average unit cost for a device at client scope (FIFO stock, sale history, or install record).
     */
    public function resolveForClientDevice(Device $device, int $clientId): float
    {
        $deviceType = Device::canonicalDeviceType($device->device_type ?? $device->category ?? '');

        $productId = $deviceType
            ? (int) (InventoryProduct::query()
                ->where('device_type', $deviceType)
                ->orderBy('id')
                ->value('id') ?? 0)
            : 0;

        if ($productId) {
            $availableCost = $this->weightedFifoCost($productId, $clientId);
            if ($availableCost > 0) {
                return $availableCost;
            }

            $installCost = $this->costFromInstallMovement($device->id, $clientId, $productId);
            if ($installCost > 0) {
                return $installCost;
            }

            $historicalCost = $this->latestFifoLayerCost($productId, $clientId);
            if ($historicalCost > 0) {
                return $historicalCost;
            }
        }

        $saleCost = $this->weightedSaleItemCost($clientId, $deviceType);
        if ($saleCost > 0) {
            return $saleCost;
        }

        $latestSaleCost = $this->latestSaleItemCost($clientId, $deviceType);

        return $latestSaleCost !== null ? round((float) $latestSaleCost, 2) : 0.0;
    }

    private function weightedFifoCost(int $productId, int $clientId): float
    {
        $layers = InventoryFifoLayer::query()
            ->where('product_id', $productId)
            ->where('scope', 'client')
            ->where('scope_id', $clientId)
            ->where('remaining_qty', '>', 0)
            ->get(['unit_cost', 'remaining_qty']);

        if ($layers->isEmpty()) {
            return 0.0;
        }

        $totalQty = $layers->sum('remaining_qty');
        if ($totalQty < 1) {
            return 0.0;
        }
        $weighted = $layers->sum(fn ($l) => (float) $l->unit_cost * (int) $l->remaining_qty);

        return round($weighted / $totalQty, 2);
    }

    private function latestFifoLayerCost(int $productId, int $clientId): float
    {
        $unitCost = InventoryFifoLayer::query()
            ->where('product_id', $productId)
            ->where('scope', 'client')
            ->where('scope_id', $clientId)
            ->orderByDesc('received_at')
            ->value('unit_cost');

        return $unitCost !== null ? round((float) $unitCost, 2) : 0.0;
    }

    private function costFromInstallMovement(int $deviceId, int $clientId, int $productId): float
    {
        $hasInstall = InventoryMovement::query()
            ->where('product_id', $productId)
            ->where('scope', 'client')
            ->where('scope_id', $clientId)
            ->where('type', 'install')
            ->where('source_type', Device::class)
            ->where('source_id', $deviceId)
            ->exists();

        if (! $hasInstall) {
            return 0.0;
        }

        $unitCost = InventoryFifoLayer::query()
            ->where('product_id', $productId)
            ->where('scope', 'client')
            ->where('scope_id', $clientId)
            ->orderByDesc('received_at')
            ->value('unit_cost');

        return $unitCost !== null ? round((float) $unitCost, 2) : 0.0;
    }

    /**
     * @return list<string>
     */
    private function deviceTypeKeysForMatch(?string $canonical): array
    {
        if ($canonical === null || $canonical === '') {
            return [];
        }

        $keys = [$canonical];
        foreach (Device::LEGACY_DEVICE_TYPE_MAP as $legacy => $mapped) {
            if ($mapped === $canonical) {
                $keys[] = $legacy;
            }
        }

        return array_values(array_unique($keys));
    }

    private function weightedSaleItemCost(int $clientId, ?string $deviceType): float
    {
        $typeKeys = $this->deviceTypeKeysForMatch($deviceType);

        $query = DeviceStockSaleItem::query()
            ->whereHas('sale', fn ($q) => $q->where('client_id', $clientId)->where('status', 'issued'))
            ->where('quantity', '>', 0);

        if ($typeKeys !== []) {
            $query->whereHas('stockOrder', fn ($q) => $q->whereIn('device_type', $typeKeys));
        }

        $items = $query->get(['unit_cost', 'quantity']);

        if ($items->isEmpty()) {
            return 0.0;
        }

        $totalQty = $items->sum('quantity');
        if ($totalQty < 1) {
            return 0.0;
        }

        $weighted = $items->sum(fn ($item) => (float) $item->unit_cost * (int) $item->quantity);

        return round($weighted / $totalQty, 2);
    }

    private function latestSaleItemCost(int $clientId, ?string $deviceType): ?float
    {
        $typeKeys = $this->deviceTypeKeysForMatch($deviceType);

        $query = DeviceStockSaleItem::query()
            ->whereHas('sale', fn ($q) => $q->where('client_id', $clientId)->where('status', 'issued'));

        if ($typeKeys !== []) {
            $query->whereHas('stockOrder', fn ($q) => $q->whereIn('device_type', $typeKeys));
        }

        $saleCost = $query->orderByDesc('id')->value('unit_cost');

        return $saleCost !== null ? (float) $saleCost : null;
    }
}
