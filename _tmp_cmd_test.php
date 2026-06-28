<?php

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\User;
use App\Services\Tracking\CommandService;

$device = Device::query()->first();
if (! $device) {
    echo "NO_DEVICE\n";
    return;
}

$actor = User::query()->first();
$svc = app(CommandService::class);

$res = $svc->send($actor, ['device_id' => $device->id, 'type' => 'engineStop', 'data' => '']);
echo 'send: ' . json_encode($res) . "\n";

$pendingBefore = DeviceCommand::where('device_id', $device->id)->where('status', 'pending')->count();
echo "pending_before_deliver: {$pendingBefore}\n";

$delivered = $svc->deliverPendingForDevice($device);
echo 'delivered_payload: ' . json_encode($delivered) . "\n";

$cmd = DeviceCommand::find($res['id']);
echo 'status_after_deliver: ' . $cmd->status . ' sent_at=' . ($cmd->sent_at?->toIso8601String()) . "\n";

// History shape
$hist = $svc->historyForActor($actor, 3);
echo 'history_first: ' . json_encode($hist[0] ?? null) . "\n";

// Cancel path: queue a fresh one and cancel it.
$res2 = $svc->send($actor, ['device_id' => $device->id, 'type' => 'custom', 'data' => 'RAW#123']);
$canceled = $svc->cancel($actor, $res2['id']);
$cmd2 = DeviceCommand::find($res2['id']);
echo 'cancel_ok: ' . ($canceled ? 'yes' : 'no') . ' status=' . $cmd2->status . "\n";

// Cleanup test rows.
DeviceCommand::whereIn('id', [$res['id'], $res2['id']])->delete();
echo "cleaned\n";
