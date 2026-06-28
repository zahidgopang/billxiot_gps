<?php

use App\Models\Device;
use App\Models\TrackingTask;
use App\Models\User;
use App\Services\Tracking\TaskService;

$device = Device::query()->first();
$actor = User::query()->first();
if (! $device || ! $actor) {
    echo "NO_DATA\n";
    return;
}

$svc = app(TaskService::class);

$task = $svc->create($actor, [
    'device_id' => $device->id,
    'name' => 'Test delivery run',
    'start' => 'Warehouse A',
    'destination' => 'Site B',
    'priority' => 'high',
    'status' => 'new',
    'time_from' => now()->toDateTimeString(),
    'time_to' => now()->addHours(3)->toDateTimeString(),
]);
echo 'create_id: ' . ($task?->id ?? 'NULL') . "\n";

$list = $svc->listForActor($actor, ['device_id' => $device->id]);
echo 'list_count: ' . count($list) . "\n";
echo 'first_row: ' . json_encode($list[0] ?? null) . "\n";

$deleted = $svc->delete($actor, (int) $task->id);
echo 'delete_ok: ' . ($deleted ? 'yes' : 'no') . "\n";

echo 'remaining: ' . TrackingTask::where('id', $task->id)->count() . "\n";
echo "done\n";
