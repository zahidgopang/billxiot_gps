<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Tracking\PositionReaderInterface;
use App\Http\Controllers\Controller;
use App\Http\Concerns\ResolvesHistoryDateRange;
use App\Models\Device;
use App\Services\Tracking\DevicePositionLoader;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class UserDeviceController extends Controller
{
    use ResolvesHistoryDateRange;

    public function __construct(
        private PositionReaderInterface $positions,
        private DevicePositionLoader $positionLoader,
    ) {}

    public function list(Request $req)
    {
        $devices = auth()->user()->trackerDevicesQuery()->get();
        $this->positionLoader->attachLatestToMany($devices);

        return response()->json($devices);
    }

    public function latest(Request $req, $imei)
    {
        $device = auth()->user()->trackerDevicesQuery()->whereImei($imei)->firstOrFail();
        $loc = $this->positions->latestForDevice($device);
        if (!$loc) return response()->json(['error' => 'no data'], 404);
        return response()->json($loc);
    }

    public function history(Request $req, $imei)
    {
        $device = auth()->user()->trackerDevicesQuery()->whereImei($imei)->firstOrFail();

        $perPage = min(1000, (int) $req->query('per_page', 500));
        $page = max(1, (int) $req->query('page', 1));
        $range = $this->resolveHistoryRange($req);

        $all = $this->positions->historyForDevice(
            $device,
            $range['from'],
            $range['to'],
            'asc'
        );

        $total = $all->count();
        $items = $all->slice(($page - 1) * $perPage, $perPage)->values();

        $result = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => $req->url(), 'query' => $req->query()]
        );

        return response()->json($result);
    }
}
