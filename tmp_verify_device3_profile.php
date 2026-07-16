<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Device;
use App\Services\Tracking\CommandProtocolMapper;

$device = Device::query()->findOrFail(3);
$mapper = app(CommandProtocolMapper::class);
$detected = $mapper->detectProfileDetailed($device);
$stop = $mapper->resolve($device, 'engineStop');
$resume = $mapper->resolve($device, 'engineResume');

echo json_encode([
    'device' => [
        'id' => $device->id,
        'name' => $device->name,
        'model' => $device->model,
        'positionid' => $device->positionid,
    ],
    'detected' => $detected,
    'engineStop' => $stop,
    'engineResume' => $resume,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
