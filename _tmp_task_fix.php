<?php

use App\Models\Device;
use App\Models\User;
use App\Services\Tracking\TaskService;

$device = Device::query()->first();
$actor = User::query()->first();
$svc = app(TaskService::class);

$task = $svc->create($actor, [
    'device_id' => $device->id,
    'name' => 'Time fix check',
    'time_from' => '2026-06-28 09:00:00',
    'time_to' => '2026-06-28 12:00:00',
]);

$list = $svc->listForActor($actor, ['device_id' => $device->id]);
$row = collect($list)->firstWhere('id', $task->id);
echo 'time_from: ' . var_export($row['time_from'], true) . "\n";
echo 'time_to: ' . var_export($row['time_to'], true) . "\n";

$svc->delete($actor, (int) $task->id);
echo "done\n";
