<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Services\UserDashboardService;

$u = User::where('email', 'admin@admin.com')->first();
$svc = app(UserDashboardService::class);

try {
    $stats = $svc->getStats($u);
    echo 'totalDevices=' . $stats['totalDevices'] . "\n";
    echo 'onlineNow=' . $stats['onlineNow'] . "\n";
    echo 'recentDevices=' . $stats['recentDevices']->count() . "\n";
    echo 'devices=' . $stats['devices']->count() . "\n";
} catch (\Throwable $e) {
    echo 'ERROR: ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
}
