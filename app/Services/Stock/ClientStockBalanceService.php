<?php

namespace App\Services\Stock;

use App\Models\Client;
use App\Models\ClientDevice;
use App\Models\Device;
use App\Models\DeviceStockSaleItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ClientStockBalanceService
{
    /**
     * @return array<string, int>
     */
    public function soldQuantitiesByType(int $clientId): array
    {
        $rows = DeviceStockSaleItem::query()
            ->join('device_stock_sales', 'device_stock_sales.id', '=', 'device_stock_sale_items.sale_id')
            ->join('device_stock_orders', 'device_stock_orders.id', '=', 'device_stock_sale_items.stock_order_id')
            ->where('device_stock_sales.client_id', $clientId)
            ->where('device_stock_sales.status', 'issued')
            ->groupBy('device_stock_orders.device_type')
            ->selectRaw('device_stock_orders.device_type as device_type, SUM(device_stock_sale_items.quantity) as qty')
            ->pluck('qty', 'device_type');

        $counts = [];
        foreach ($rows as $type => $qty) {
            $key = Device::canonicalDeviceType((string) $type);
            if (! $key) {
                continue;
            }
            $counts[$key] = ($counts[$key] ?? 0) + (int) $qty;
        }

        return $counts;
    }

    /**
     * Installed = devices linked to this client in client_devices (by canonical device type).
     *
     * @return array<string, int>
     */
    public function installedQuantitiesByType(int $clientId): array
    {
        $links = ClientDevice::query()
            ->where('client_devices.client_id', $clientId)
            ->with(['device'])
            ->get();

        $counts = [];
        foreach ($links as $link) {
            $device = $link->device;
            if (! $device) {
                continue;
            }

            $type = Device::canonicalDeviceType($device->device_type);
            if (! $type) {
                continue;
            }

            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array{
     *     client_id: int,
     *     totals: array{sold: int, installed: int, available: int},
     *     by_type: list<array{device_type: string, sold: int, installed: int, available: int}>
     * }
     */
    public function balanceForClient(int $clientId): array
    {
        $sold = $this->soldQuantitiesByType($clientId);
        $installed = $this->installedQuantitiesByType($clientId);

        $byType = [];
        foreach (array_keys(Device::DEVICE_TYPES) as $type) {
            $s = $sold[$type] ?? 0;
            $i = $installed[$type] ?? 0;
            if ($s === 0 && $i === 0) {
                continue;
            }
            $byType[] = [
                'device_type' => $type,
                'sold' => $s,
                'installed' => $i,
                'available' => max(0, $s - $i),
            ];
        }

        $totalSold = array_sum($sold);
        $totalInstalled = array_sum($installed);

        return [
            'client_id' => $clientId,
            'totals' => [
                'sold' => $totalSold,
                'installed' => $totalInstalled,
                'available' => max(0, $totalSold - $totalInstalled),
            ],
            'by_type' => $byType,
        ];
    }

    /**
     * Device types the client can still install (available > 0).
     *
     * @return list<string>
     */
    public function installableTypesForClient(int $clientId, ?string $includeType = null): array
    {
        $balance = $this->balanceForClient($clientId);
        $types = [];

        foreach ($balance['by_type'] as $row) {
            if ($row['available'] > 0) {
                $types[] = $row['device_type'];
            }
        }

        $includeType = $includeType ? Device::canonicalDeviceType($includeType) : null;
        if ($includeType && ! in_array($includeType, $types, true)) {
            $types[] = $includeType;
        }

        return $types;
    }

    public function hasInstallableStock(int $clientId, ?string $includeType = null): bool
    {
        return count($this->installableTypesForClient($clientId, $includeType)) > 0;
    }

    /**
     * @throws ValidationException
     */
    public function assertCanInstall(int $clientId, string $deviceType, ?int $exceptDeviceId = null): void
    {
        $deviceType = Device::canonicalDeviceType($deviceType) ?? $deviceType;

        $sold = $this->soldQuantitiesByType($clientId);
        $installed = $this->installedQuantitiesByType($clientId);

        $soldQty = $sold[$deviceType] ?? 0;
        $installedQty = $installed[$deviceType] ?? 0;

        if ($exceptDeviceId) {
            $device = Device::query()->find($exceptDeviceId);
            $existingType = $device ? Device::canonicalDeviceType($device->device_type) : null;

            if ($device && $existingType === $deviceType) {
                $linked = ClientDevice::query()
                    ->where('client_id', $clientId)
                    ->where('device_id', $exceptDeviceId)
                    ->exists();

                if ($linked) {
                    $installedQty = max(0, $installedQty - 1);
                }
            }
        }

        if ($soldQty < 1) {
            throw ValidationException::withMessages([
                'device_type' => [__('app.admin.stock_sales.no_client_stock_for_type')],
            ]);
        }

        if ($installedQty >= $soldQty) {
            throw ValidationException::withMessages([
                'device_type' => [__('app.admin.stock_sales.no_client_stock_for_type')],
            ]);
        }
    }

    /**
     * @param  Collection<int, Client>  $clients
     * @return list<array{client: Client, totals: array{sold: int, installed: int, available: int}, by_type: array}>
     */
    public function balancesForClients(Collection $clients): array
    {
        return $clients->map(function (Client $client) {
            $balance = $this->balanceForClient((int) $client->id);

            return [
                'client' => $client,
                'totals' => $balance['totals'],
                'by_type' => $balance['by_type'],
            ];
        })->values()->all();
    }
}
