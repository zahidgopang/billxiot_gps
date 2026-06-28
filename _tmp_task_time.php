<?php

use App\Models\Device;
use App\Models\TrackingTask;
use App\Models\User;
use App\Services\Tracking\TaskService;

$device = Device::query()->first();
$actor = User::query()->first();

$svc = app(TaskService::class);

$task = $svc->create($actor, [
    'device_id' => $device->id,
    'name' => 'Time check',
    'time_from' => '2026-06-28 09:00:00',
    'time_to' => '2026-06-28 12:00:00',
]);

$fresh = TrackingTask::find($task->id);
echo 'raw_time_from: ' . var_export($fresh->getRawOriginal('time_from'), true) . "\n";
echo 'raw_time_to: ' . var_export($fresh->getRawOriginal('time_to'), true) . "\n";
echo 'cast_time_from: ' . var_export(optional($fresh->time_from)->format('Y-m-d H:i'), true) . "\n";

$fresh->delete();
echo "done\n";
