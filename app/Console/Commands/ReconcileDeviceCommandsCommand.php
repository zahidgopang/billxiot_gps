<?php

namespace App\Console\Commands;

use App\Services\Tracking\CommandLifecycleReconciler;
use Illuminate\Console\Command;

class ReconcileDeviceCommandsCommand extends Command
{
    protected $signature = 'commands:reconcile-status {--limit=200 : Max open logs to scan}';

    protected $description = 'Advance BillX command logs from Traccar queuedCommandSent / commandResult events';

    public function handle(CommandLifecycleReconciler $reconciler): int
    {
        $stats = $reconciler->reconcile((int) $this->option('limit'));
        $this->info(sprintf(
            'Command reconcile: delivered=%d executed=%d timed_out=%d',
            $stats['delivered'],
            $stats['executed'],
            $stats['timed_out']
        ));

        return self::SUCCESS;
    }
}
