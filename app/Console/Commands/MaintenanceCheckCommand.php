<?php

namespace App\Console\Commands;

use App\Services\Tracking\MaintenanceNotifierService;
use Illuminate\Console\Command;

class MaintenanceCheckCommand extends Command
{
    protected $signature = 'maintenance:check';

    protected $description = 'Raise maintenance-due alerts (web + push) when a service interval is reached';

    public function handle(MaintenanceNotifierService $notifier): int
    {
        $count = $notifier->run();

        $this->info("Maintenance check completed. {$count} alert(s) emitted.");

        return self::SUCCESS;
    }
}
