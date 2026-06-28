<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\User;
use App\Support\DateTime\AppDateTime;

class CommandService
{
    /**
     * Command types we expose. Mirrors common Traccar command types; `custom`
     * lets an operator push a raw command string via the data field.
     *
     * @var list<string>
     */
    public const ALLOWED_TYPES = [
        'engineStop',
        'engineResume',
        'alarmArm',
        'alarmDisarm',
        'rebootDevice',
        'positionSingle',
        'custom',
    ];

    public function __construct(
        private GlobalTrackingService $tracking,
    ) {}

    /**
     * Type => human label map for the UI.
     *
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            'engineStop' => (string) __('app.tracking.command_type_engine_stop'),
            'engineResume' => (string) __('app.tracking.command_type_engine_resume'),
            'alarmArm' => (string) __('app.tracking.command_type_alarm_arm'),
            'alarmDisarm' => (string) __('app.tracking.command_type_alarm_disarm'),
            'rebootDevice' => (string) __('app.tracking.command_type_reboot'),
            'positionSingle' => (string) __('app.tracking.command_type_position_single'),
            'custom' => (string) __('app.tracking.command_type_custom'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historyForActor(User $actor, int $limit = 100): array
    {
        $deviceIds = $this->tracking->allowedDeviceIds($actor);
        if ($deviceIds === []) {
            return [];
        }

        $rows = DeviceCommand::query()
            ->with(['device', 'requester'])
            ->whereIn('device_id', $deviceIds)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $labels = self::typeLabels();

        return $rows->map(function (DeviceCommand $cmd) use ($labels) {
            $at = $cmd->sent_at ?? $cmd->queued_at ?? $cmd->created_at;

            return [
                'id' => $cmd->id,
                'device_id' => $cmd->device_id,
                'device' => $cmd->device?->mapMarkerTitle() ?? ('#' . $cmd->device_id),
                'type' => $cmd->type,
                'type_label' => $labels[$cmd->type] ?? $cmd->type,
                'data' => (string) ($cmd->data ?? ''),
                'status' => $cmd->status,
                'requested_by' => $cmd->requester?->name,
                'result' => (string) ($cmd->result ?? ''),
                'time' => $at ? AppDateTime::format($at, 'display') : '',
                ...($at ? AppDateTime::apiFields($at) : []),
            ];
        })->all();
    }

    /**
     * Queue a command for delivery to a device.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message: string, id?: int}
     */
    public function send(User $actor, array $data): array
    {
        if ((bool) ($actor->getAttribute('limitCommands') ?? false)) {
            return ['success' => false, 'message' => (string) __('app.tracking.command_restricted')];
        }

        $deviceId = (int) ($data['device_id'] ?? 0);
        $type = (string) ($data['type'] ?? 'custom');
        $raw = trim((string) ($data['data'] ?? ''));

        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            return ['success' => false, 'message' => (string) __('app.tracking.command_invalid_type')];
        }

        if (! in_array($deviceId, $this->tracking->filterAllowedIds($actor, [$deviceId]), true)) {
            return ['success' => false, 'message' => (string) __('app.tracking.command_device_inaccessible')];
        }

        if ($type === 'custom' && $raw === '') {
            return ['success' => false, 'message' => (string) __('app.tracking.command_custom_required')];
        }

        $command = DeviceCommand::create([
            'device_id' => $deviceId,
            'type' => $type,
            'data' => $raw !== '' ? $raw : null,
            'attributes' => [
                'command' => $type,
                'data' => $raw,
                'requested_by' => $actor->id,
                'requested_at' => now()->toIso8601String(),
            ],
            'status' => DeviceCommand::STATUS_PENDING,
            'requested_by' => $actor->id,
            'queued_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => (string) __('app.tracking.command_queued_ok'),
            'id' => $command->id,
        ];
    }

    /**
     * Cancel a still-pending command the actor is allowed to manage.
     */
    public function cancel(User $actor, int $commandId): bool
    {
        $command = DeviceCommand::query()
            ->where('id', $commandId)
            ->where('status', DeviceCommand::STATUS_PENDING)
            ->first();

        if (! $command) {
            return false;
        }

        if (! in_array((int) $command->device_id, $this->tracking->filterAllowedIds($actor, [(int) $command->device_id]), true)) {
            return false;
        }

        $command->update([
            'status' => DeviceCommand::STATUS_CANCELED,
            'result' => (string) __('app.tracking.command_canceled_by', ['user' => $actor->name]),
        ]);

        return true;
    }

    /**
     * Pull pending commands for a device, mark them sent, and return the
     * delivery payload to embed in the tracker's check-in response.
     *
     * @return list<array<string, mixed>>
     */
    public function deliverPendingForDevice(Device $device): array
    {
        $pending = DeviceCommand::query()
            ->where('device_id', $device->id)
            ->where('status', DeviceCommand::STATUS_PENDING)
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            return [];
        }

        $payload = [];
        $now = now();

        foreach ($pending as $command) {
            $payload[] = [
                'id' => $command->id,
                'type' => $command->type,
                'data' => (string) ($command->data ?? ''),
                'attributes' => $command->attributes ?? [],
            ];

            $command->update([
                'status' => DeviceCommand::STATUS_SENT,
                'sent_at' => $now,
            ]);
        }

        return $payload;
    }
}
