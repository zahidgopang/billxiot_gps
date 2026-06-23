<?php

namespace App\Contracts\Tracking;

use App\Data\Tracking\IncomingPosition;
use App\Models\DeviceLocation;

interface PositionWriterInterface
{
    public function store(IncomingPosition $position): DeviceLocation;
}
