<?php

namespace App\Repositories\Tracking;

use App\Contracts\Tracking\PositionWriterInterface;
use App\Data\Tracking\IncomingPosition;
use App\Models\DeviceLocation;
use Illuminate\Support\Facades\Schema;

class LegacyPositionWriter implements PositionWriterInterface
{
    public function store(IncomingPosition $position): DeviceLocation
    {
        if (! Schema::hasTable('device_locations')) {
            return new DeviceLocation($position->toLegacyAttributes());
        }

        return DeviceLocation::create($position->toLegacyAttributes());
    }
}
