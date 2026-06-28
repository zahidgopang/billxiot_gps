<?php

use App\Services\Tracking\CommandService;
use App\Support\Traccar\TraccarSchema;

$svc = app(CommandService::class);
echo "resolved: " . get_class($svc) . "\n";
echo "queue table exists: " . (TraccarSchema::hasTable('tc_commands_queue') ? 'yes' : 'no') . "\n";
echo "types: " . implode(',', array_keys(CommandService::typeLabels())) . "\n";

$user = \App\Models\User::query()->first();
if ($user) {
    $hist = $svc->historyForActor($user, 5);
    echo "history rows for first user: " . count($hist) . "\n";
} else {
    echo "no users\n";
}
echo "OK\n";
