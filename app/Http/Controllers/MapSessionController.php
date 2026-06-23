<?php

namespace App\Http\Controllers;

use App\Services\DeviceMapAccessService;
use Illuminate\Http\Request;

class MapSessionController extends Controller
{
    public function __construct(
        private DeviceMapAccessService $mapAccess
    ) {}

    /**
     * End map API session when user leaves the map (not on refresh).
     */
    public function end(Request $request)
    {
        $this->mapAccess->endMapSession();

        return response()->json(['ok' => true]);
    }
}
