<?php

namespace App\Services\Tracking;

use App\Models\User;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Facades\DB;

class CommandService
{
    public const ALLOWED_TYPES = [
        'engineStop',
        'engineResume',
        'alarmArm',
        'alarmDisarm',
        'custom',
    ];

    public function __construct(
        private GlobalTrackingService $tracking,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function historyForActor(User $actor, int $limit = 50): array
    {
        $deviceIds = $this->tracking->allowedDeviceIds($actor);
        if ($deviceIds === [] || ! TraccarSchema::hasTable('tc_events')) {
            return [];
        }

        return DB::table(config('traccar.tables.events', 'tc_events'))
            ->whereIn('deviceid', $deviceIds)
            ->whereIn('type', ['commandResult', 'queuedCommandSent'])
            ->orderByDesc('eventtime')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'device_id' => (int) $row->deviceid,
                'type' => (string) $row->type,
                'time' => (string) $row->eventtime,
                'attributes' => json_decode((string) ($row->attributes ?? '{}'), true) ?: [],
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message: string, id?: int}
     */
    public function send(User $actor, array $data): array
    {
        if ((bool) ($actor->getAttribute('limitCommands') ?? false)) {
            return ['success' => false, 'message' => 'Commands are restricted for this account.'];
        }

        $deviceId = (int) ($data['device_id'] ?? 0);
        $type = (string) ($data['type'] ?? 'custom');

        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            return ['success' => false, 'message' => 'Invalid command type.'];
        }

        if (! in_array($deviceId, $this->tracking->filterAllowedIds($actor, [$deviceId]), true)) {
            return ['success' => false, 'message' => 'Device not accessible.'];
        }

        $attributes = [
            'command' => $type,
            'data' => $data['data'] ?? '',
            'requested_by' => $actor->id,
            'requested_at' => now()->toIso8601String(),
        ];

        if (TraccarSchema::hasTable('tc_commands')) {
            $payload = TraccarSchema::filterColumns('tc_commands', [
                'description' => $type,
                'type' => $type,
                'textchannel' => false,
                'attributes' => json_encode(array_merge($attributes, ['deviceId' => $deviceId])),
            ]);
            $id = (int) DB::table('tc_commands')->insertGetId($payload);

            return [
                'success' => true,
                'message' => 'Command queued. Traccar server must be running to deliver it.',
                'id' => $id,
            ];
        }

        return [
            'success' => false,
            'message' => 'Command table not available. Ensure Traccar schema is installed.',
        ];
    }
}
