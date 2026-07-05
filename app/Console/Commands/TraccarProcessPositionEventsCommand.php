<?php

namespace App\Console\Commands;

use App\Services\Tracking\TraccarPositionBroadcastService;
use Illuminate\Console\Command;

class TraccarProcessPositionEventsCommand extends Command
{
    protected $signature = 'traccar:process-position-events';

    protected $description = 'Process alerts/status/geofence logic from each device latest GPS (light broadcast mode)';

    public function handle(TraccarPositionBroadcastService $service): int
    {
        $count = $service->processLatestPositionEvents();

        if ($this->output->isVerbose() && $count > 0) {
            $this->line("Processed events for {$count} device(s).");
        }

        return self::SUCCESS;
    }
}
