<?php

namespace App\Services\Inventory;

use App\Models\InventoryFifoLayer;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FifoAllocator
{
    /**
     * Allocate qty from FIFO layers for a scope.
     *
     * @return list<array{layer: InventoryFifoLayer, qty: int}>
     */
    public function allocate(string $scope, ?int $scopeId, int $productId, int $qty): array
    {
        if ($qty < 1) {
            return [];
        }

        /** @var Collection<int, InventoryFifoLayer> $layers */
        $layers = InventoryFifoLayer::query()
            ->where('product_id', $productId)
            ->where('scope', $scope)
            ->where('scope_id', $scopeId)
            ->where('remaining_qty', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $qty;
        $allocations = [];

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (int) $layer->remaining_qty);
            if ($take <= 0) {
                continue;
            }

            $allocations[] = ['layer' => $layer, 'qty' => $take];
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'quantity' => ["Insufficient FIFO stock (missing {$remaining})."],
            ]);
        }

        return $allocations;
    }
}

