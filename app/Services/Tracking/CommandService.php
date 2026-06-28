<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\User;
use App\Services\Traccar\TraccarIdMap;
use App\Support\DateTime\AppDateTime;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Device command queue backed by Traccar's native `tc_commands_queue` table.
 *
 * Traccar stores commands that cannot be delivered immediately (device offline)
 * in `tc_commands_queue`. We reuse that exact table so the feature stays
 * compatible with a real Traccar daemon. The base table only has
 * (id, deviceid, type, textchannel, attributes), so lifecycle metadata
 * (status / data / requester / timestamps) is kept inside the `attributes` JSON.
 */
class CommandService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELED = 'canceled';

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
        private TraccarIdMap $idMap,
    ) {}

    private function table(): string
    {
        return config('traccar.tables.commands_queue', 'tc_commands_queue');
    }

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
        if ($deviceIds === [] || ! TraccarSchema::hasTable($this->table())) {
            return [];
        }

        // Map laravel device ids -> traccar device ids and keep a reverse lookup.
        $traccarToLaravel = [];
        foreach ($deviceIds as $laravelId) {
            $tid = $this->idMap->get(\App\Models\TraccarEntityMap::TYPE_DEVICE, (int) $laravelId);
            if ($tid) {
                $traccarToLaravel[(int) $tid] = (int) $laravelId;
            }
        }

        if ($traccarToLaravel === []) {
            return [];
        }

        $rows = DB::table($this->table())
            ->whereIn('deviceid', array_keys($traccarToLaravel))
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $labels = self::typeLabels();

        $deviceNames = Device::query()
            ->whereIn('id', array_values($traccarToLaravel))
            ->get()
            ->mapWithKeys(fn (Device $d) => [(int) $d->id => $d->mapMarkerTitle()])
            ->all();

        return $rows->map(function ($row) use ($labels, $traccarToLaravel, $deviceNames) {
            $attrs = $this->decodeAttributes($row->attributes ?? null);
            $laravelDeviceId = $traccarToLaravel[(int) $row->deviceid] ?? 0;
            $at = $this->timestampFrom($attrs);

            return [
                'id' => (int) $row->id,
                'device_id' => $laravelDeviceId,
                'device' => $deviceNames[$laravelDeviceId] ?? ('#' . $laravelDeviceId),
                'type' => (string) $row->type,
                'type_label' => $labels[$row->type] ?? (string) $row->type,
                'data' => (string) ($attrs['data'] ?? ''),
                'status' => (string) ($attrs['status'] ?? self::STATUS_PENDING),
                'requested_by' => $attrs['requested_by_name'] ?? null,
                'result' => (string) ($attrs['result'] ?? ''),
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

        $traccarDeviceId = $this->idMap->get(\App\Models\TraccarEntityMap::TYPE_DEVICE, $deviceId);
        if (! $traccarDeviceId) {
            return ['success' => false, 'message' => (string) __('app.tracking.command_device_inaccessible')];
        }

        $attributes = [
            'data' => $raw,
            'status' => self::STATUS_PENDING,
            'requested_by' => $actor->id,
            'requested_by_name' => $actor->name,
            'requested_at' => now()->toIso8601String(),
        ];

        $payload = TraccarSchema::filterColumns($this->table(), [
            'deviceid' => $traccarDeviceId,
            'type' => $type,
            'textchannel' => false,
            'attributes' => json_encode($attributes),
        ]);

        $id = DB::table($this->table())->insertGetId($payload);

        return [
            'success' => true,
            'message' => (string) __('app.tracking.command_queued_ok'),
            'id' => (int) $id,
        ];
    }

    /**
     * Cancel a still-pending command the actor is allowed to manage.
     */
    public function cancel(User $actor, int $commandId): bool
    {
        $row = DB::table($this->table())->where('id', $commandId)->first();
        if (! $row) {
            return false;
        }

        $laravelDeviceId = $this->idMap->laravelId(\App\Models\TraccarEntityMap::TYPE_DEVICE, (int) $row->deviceid);
        if (! $laravelDeviceId
            || ! in_array((int) $laravelDeviceId, $this->tracking->filterAllowedIds($actor, [(int) $laravelDeviceId]), true)) {
            return false;
        }

        $attrs = $this->decodeAttributes($row->attributes ?? null);
        if (($attrs['status'] ?? self::STATUS_PENDING) !== self::STATUS_PENDING) {
            return false;
        }

        $attrs['status'] = self::STATUS_CANCELED;
        $attrs['result'] = (string) __('app.tracking.command_canceled_by', ['user' => $actor->name]);
        $attrs['canceled_at'] = now()->toIso8601String();

        DB::table($this->table())
            ->where('id', $commandId)
            ->update(['attributes' => json_encode($attrs)]);

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
        if (! TraccarSchema::hasTable($this->table())) {
            return [];
        }

        $traccarDeviceId = $this->idMap->get(\App\Models\TraccarEntityMap::TYPE_DEVICE, (int) $device->id);
        if (! $traccarDeviceId) {
            return [];
        }

        $rows = DB::table($this->table())
            ->where('deviceid', $traccarDeviceId)
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $payload = [];
        $now = now()->toIso8601String();

        foreach ($rows as $row) {
            $attrs = $this->decodeAttributes($row->attributes ?? null);
            if (($attrs['status'] ?? self::STATUS_PENDING) !== self::STATUS_PENDING) {
                continue;
            }

            $payload[] = [
                'id' => (int) $row->id,
                'type' => (string) $row->type,
                'data' => (string) ($attrs['data'] ?? ''),
            ];

            $attrs['status'] = self::STATUS_SENT;
            $attrs['sent_at'] = $now;

            DB::table($this->table())
                ->where('id', $row->id)
                ->update(['attributes' => json_encode($attrs)]);
        }

        return $payload;
    }

    /**
     * @param  mixed  $raw
     * @return array<string, mixed>
     */
    private function decodeAttributes($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function timestampFrom(array $attrs): ?Carbon
    {
        $value = $attrs['sent_at'] ?? $attrs['canceled_at'] ?? $attrs['requested_at'] ?? null;
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
