<?php

use App\Models\User;
use App\Services\Tracking\CommandService;
use App\Services\Tracking\GlobalTrackingService;
use Illuminate\Support\Facades\DB;

$svc = app(CommandService::class);
$tracking = app(GlobalTrackingService::class);

$user = User::query()->whereNull('limitCommands')->orWhere('limitCommands', false)->first()
    ?? User::query()->first();
if (! $user) { echo "no user\n"; return; }

$deviceIds = $tracking->allowedDeviceIds($user);
if ($deviceIds === []) { echo "user {$user->id} has no devices\n"; return; }
$deviceId = $deviceIds[0];

echo "user={$user->id} device={$deviceId}\n";

// 1) ADD
$send = $svc->send($user, ['device_id' => $deviceId, 'type' => 'positionSingle', 'data' => '']);
echo "send: " . json_encode($send) . "\n";
$id = $send['id'] ?? null;
if (! $id) { echo "send failed\n"; return; }

// 2) Verify present in core table
$row = DB::table('tc_commands_queue')->where('id', $id)->first();
echo "in tc_commands_queue: " . ($row ? 'yes' : 'no') . " attrs=" . ($row->attributes ?? '') . "\n";

// 3) History shows it
$hist = collect($svc->historyForActor($user, 50))->firstWhere('id', $id);
echo "history status: " . ($hist['status'] ?? 'MISSING') . "\n";

// 4) CANCEL (delete action)
$cancel = $svc->cancel($user, $id);
echo "cancel: " . ($cancel ? 'ok' : 'fail') . "\n";
$row2 = DB::table('tc_commands_queue')->where('id', $id)->first();
$attrs2 = json_decode($row2->attributes ?? '{}', true);
echo "status after cancel: " . ($attrs2['status'] ?? '?') . "\n";

// 5) Cleanup the test row
DB::table('tc_commands_queue')->where('id', $id)->delete();
echo "cleaned test row\n";
echo "DONE\n";
