<?php

namespace App\Console\Commands;

use App\Services\Tracking\TraccarPositionBroadcastService;
use Illuminate\Console\Command;

class TraccarBroadcastPositionsCommand extends Command
{
    protected $signature = 'traccar:broadcast-positions';

    protected $description = 'Broadcast latest Traccar GPS to map clients (WebSocket). Use TRACCAR_BROADCAST_MODE=light on small VPS.';

    public function handle(TraccarPositionBroadcastService $service): int
    {
        $count = $service->broadcastNewPositions();

        if ($this->output->isVerbose() && $count > 0) {
            $this->line("Broadcast {$count} position(s).");
        }

        return self::SUCCESS;
    }
}
