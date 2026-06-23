<?php

namespace App\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class DeviceLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $deviceId;
    public array $payload;

    public function __construct(int $deviceId, array $payload)
    {
        $this->deviceId = $deviceId;
        $this->payload  = $payload;
    }

    /**
     * Broadcast on the private device channel (Echo will use `private-device.{id}`)
     */
    public function broadcastOn()
    {
        return new PrivateChannel("device.{$this->deviceId}");
    }

    /**
     * Event name seen by JS: ".DeviceLocationUpdated"
     */
    public function broadcastAs(): string
    {
        return 'DeviceLocationUpdated';
    }

    /**
     * Payload to broadcast (lat, lng, speed, heading, etc)
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
