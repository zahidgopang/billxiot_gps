<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\DeviceCommandLog;
use App\Models\User;
use App\Services\Traccar\TraccarApiClient;
use App\Services\Traccar\TraccarIdMap;
use App\Support\DateTime\AppDateTime;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Device commands — BillX → Traccar Commands API → device → acknowledgement.
 *
 * Statuses: pending → sent → delivered → executed | failed | timeout | canceled
 */
class CommandService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_TIMEOUT = 'timeout';

    public const STATUS_CANCELED = 'canceled';

    public const DELIVERY_API = 'traccar_api';

    public const DELIVERY_QUEUE = 'queue';

    /** @var list<string> */
    public const ALLOWED_TYPES = [
        'engineStop',
        'engineResume',
        'alarmArm',
        'alarmDisarm',
        'rebootDevice',
        'positionSingle',
        'custom',
    ];

    /** Statuses that can still be cancelled by the operator. */
    private const CANCELABLE = [
        self::STATUS_PENDING,
        self::STATUS_SENT,
    ];

    public function __construct(
        private GlobalTrackingService $tracking,
        private TraccarIdMap $idMap,
        private TraccarApiClient $traccarApi,
        private CommandProtocolMapper $protocolMapper,
        private CommandPipelineLogger $pipeline,
    ) {}

    private function table(): string
    {
        return config('traccar.tables.commands_queue', 'tc_commands_queue');
    }

    private function hasAuditTable(): bool
    {
        return Schema::hasTable('device_command_logs');
    }

    /**
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
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => (string) __('app.tracking.command_status_pending'),
            self::STATUS_SENT => (string) __('app.tracking.command_status_sent'),
            self::STATUS_DELIVERED => (string) __('app.tracking.command_status_delivered'),
            self::STATUS_EXECUTED => (string) __('app.tracking.command_status_executed'),
            self::STATUS_FAILED => (string) __('app.tracking.command_status_failed'),
            self::STATUS_TIMEOUT => (string) __('app.tracking.command_status_timeout'),
            self::STATUS_CANCELED => (string) __('app.tracking.command_status_canceled'),
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

        $rows = $this->auditHistory($deviceIds, $limit);
        if ($rows !== []) {
            return $rows;
        }

        return $this->legacyQueueHistory($deviceIds, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function historyForDevice(User $actor, int $deviceId, int $limit = 50): array
    {
        if (! in_array($deviceId, $this->tracking->filterAllowedIds($actor, [$deviceId]), true)) {
            return [];
        }

        $rows = $this->auditHistory([$deviceId], $limit);
        if ($rows !== []) {
            return $rows;
        }

        return $this->legacyQueueHistory([$deviceId], $limit);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message: string, id?: int, status?: string}
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
        $traccarDeviceId = (int) $traccarDeviceId;

        $device = Device::query()->find($deviceId);
        if (! $device) {
            return ['success' => false, 'message' => (string) __('app.tracking.command_device_inaccessible')];
        }

        $offlineHint = $this->offlineHint($traccarDeviceId);
        $wire = $this->protocolMapper->resolve($device, $type, $raw);

        $log = $this->writeAudit([
            'device_id' => $deviceId,
            'traccar_device_id' => $traccarDeviceId,
            'type' => $type,
            'data' => $raw,
            'wire_type' => $wire['type'],
            'wire_data' => $wire['wire_data'],
            'protocol_profile' => $wire['profile'],
            'status' => self::STATUS_PENDING,
            'requested_by' => $actor->id,
            'requested_by_name' => $actor->name,
            'result' => '',
            'delivery' => self::DELIVERY_API,
            'stages' => [],
            'timeout_at' => now()->addMinutes(max(1, (int) config('device_commands.timeout_minutes', 15))),
        ]);

        $this->pipeline->stage(
            $log,
            CommandPipelineLogger::STAGE_CREATED,
            'Command accepted by BillX',
            ['logical_type' => $type, 'raw' => $raw]
        );
        $this->pipeline->stage(
            $log,
            CommandPipelineLogger::STAGE_MAPPED,
            "Mapped to Traccar wire type {$wire['type']} via profile {$wire['profile']}",
            [
                'profile' => $wire['profile'],
                'wire_type' => $wire['type'],
                'wire_data' => $wire['wire_data'],
                'attributes' => $wire['attributes'],
            ]
        );

        if ($this->traccarApi->configured()) {
            $api = $this->traccarApi->sendCommand(
                $traccarDeviceId,
                $wire['type'],
                $wire['attributes']
            );

            if ($api['ok']) {
                $queueId = $this->extractQueueId($api['body'] ?? null);
                if ($log) {
                    $log->status = self::STATUS_SENT;
                    $log->delivery = self::DELIVERY_API;
                    $log->queue_id = $queueId;
                    $log->result = (string) ($api['message'] ?? 'Accepted by Traccar API');
                    $log->save();
                }

                $this->pipeline->stage(
                    $log,
                    CommandPipelineLogger::STAGE_TRACCAR_API,
                    'Traccar POST /api/commands/send succeeded',
                    [
                        'http_status' => $api['status'] ?? null,
                        'queue_id' => $queueId,
                        'body' => is_array($api['body'] ?? null)
                            ? $api['body']
                            : ['raw' => mb_substr((string) ($api['body'] ?? ''), 0, 400)],
                    ]
                );
                $this->pipeline->stage(
                    $log,
                    CommandPipelineLogger::STAGE_SENT,
                    'Awaiting device delivery / acknowledgement',
                    ['offline_hint' => $offlineHint]
                );

                $message = (string) __('app.tracking.command_sent_ok');
                if ($offlineHint !== null) {
                    $message .= ' '.$offlineHint;
                }

                return [
                    'success' => true,
                    'message' => $message,
                    'id' => $log?->id,
                    'status' => self::STATUS_SENT,
                ];
            }

            $apiError = (string) ($api['message'] ?? 'Traccar API error');
            $this->pipeline->stage(
                $log,
                CommandPipelineLogger::STAGE_FAILED,
                'Traccar API rejected command — falling back to DB queue',
                ['error' => $apiError, 'http_status' => $api['status'] ?? null]
            );
        } else {
            $apiError = 'Traccar API is not configured';
            $this->pipeline->stage(
                $log,
                CommandPipelineLogger::STAGE_FAILED,
                $apiError,
                []
            );
        }

        if (! TraccarSchema::hasTable($this->table())) {
            if ($log) {
                $log->status = self::STATUS_FAILED;
                $log->result = $apiError ?: (string) __('app.tracking.command_delivery_unavailable');
                $log->delivery = self::DELIVERY_QUEUE;
                $log->save();
            }
            $this->pipeline->stage(
                $log,
                CommandPipelineLogger::STAGE_FAILED,
                (string) __('app.tracking.command_delivery_unavailable'),
                []
            );

            return [
                'success' => false,
                'message' => (string) __('app.tracking.command_delivery_unavailable'),
                'id' => $log?->id,
                'status' => self::STATUS_FAILED,
            ];
        }

        $attributes = [
            // Wire payload only — Traccar may encode from type + attributes.data
            'data' => $wire['wire_data'],
            'status' => self::STATUS_PENDING,
            'requested_by' => $actor->id,
            'requested_by_name' => $actor->name,
            'requested_at' => now()->toIso8601String(),
            'billx_log_id' => $log?->id,
            'api_error' => $apiError ?? null,
        ];

        $payload = TraccarSchema::filterColumns($this->table(), [
            'deviceid' => $traccarDeviceId,
            'type' => $wire['type'],
            'textchannel' => false,
            'attributes' => json_encode($attributes),
        ]);

        $queueId = (int) DB::table($this->table())->insertGetId($payload);

        if ($log) {
            $log->status = self::STATUS_PENDING;
            $log->delivery = self::DELIVERY_QUEUE;
            $log->queue_id = $queueId;
            $log->result = $apiError
                ? (string) __('app.tracking.command_queued_api_fallback', ['error' => $apiError])
                : (string) __('app.tracking.command_queued_ok');
            $log->save();
        }

        $this->pipeline->stage(
            $log,
            CommandPipelineLogger::STAGE_QUEUED,
            'Inserted into tc_commands_queue (fallback)',
            ['queue_id' => $queueId, 'wire_type' => $wire['type']]
        );

        $message = $apiError
            ? (string) __('app.tracking.command_queued_api_fallback', ['error' => $apiError])
            : (string) __('app.tracking.command_queued_ok');
        if ($offlineHint !== null) {
            $message .= ' '.$offlineHint;
        }

        return [
            'success' => true,
            'message' => $message,
            'id' => $log?->id ?? $queueId,
            'status' => self::STATUS_PENDING,
        ];
    }

    public function cancel(User $actor, int $commandId): bool
    {
        if ($this->hasAuditTable()) {
            $log = DeviceCommandLog::query()->where('id', $commandId)->first();
            if ($log) {
                if (! in_array(
                    (int) $log->device_id,
                    $this->tracking->filterAllowedIds($actor, [(int) $log->device_id]),
                    true
                )) {
                    return false;
                }

                if (! in_array((string) $log->status, self::CANCELABLE, true)) {
                    return false;
                }

                $log->status = self::STATUS_CANCELED;
                $log->result = (string) __('app.tracking.command_canceled_by', ['user' => $actor->name]);
                $log->save();

                $this->pipeline->stage(
                    $log,
                    CommandPipelineLogger::STAGE_CANCELED,
                    'Canceled by operator',
                    ['user' => $actor->name]
                );

                if ($log->queue_id && TraccarSchema::hasTable($this->table())) {
                    $this->cancelQueueRow((int) $log->queue_id, $actor);
                }

                return true;
            }
        }

        return $this->cancelQueueRow($commandId, $actor);
    }

    /**
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

            if ($this->hasAuditTable()) {
                $log = DeviceCommandLog::query()
                    ->where('queue_id', (int) $row->id)
                    ->whereIn('status', [self::STATUS_PENDING, self::STATUS_SENT])
                    ->first();
                if ($log) {
                    $log->status = self::STATUS_DELIVERED;
                    $log->delivered_at = now();
                    $log->result = (string) __('app.tracking.command_status_delivered');
                    $log->save();
                    $this->pipeline->stage(
                        $log,
                        CommandPipelineLogger::STAGE_DELIVERED,
                        'Delivered via Laravel /api/device/data check-in',
                        ['queue_id' => (int) $row->id]
                    );
                }
            }
        }

        return $payload;
    }

    /**
     * @param  mixed  $body
     */
    private function extractQueueId($body): ?int
    {
        if (! is_array($body)) {
            return null;
        }
        foreach (['id', 'commandId'] as $key) {
            if (isset($body[$key]) && is_numeric($body[$key])) {
                return (int) $body[$key];
            }
        }

        return null;
    }

    private function offlineHint(int $traccarDeviceId): ?string
    {
        $devicesTable = config('traccar.tables.devices', 'tc_devices');
        if (! TraccarSchema::hasTable($devicesTable)) {
            return null;
        }

        $lastUpdate = DB::table($devicesTable)->where('id', $traccarDeviceId)->value('lastupdate');
        if ($lastUpdate === null || $lastUpdate === '') {
            return (string) __('app.tracking.command_device_offline_hint');
        }

        try {
            if (Carbon::parse($lastUpdate)->lt(now()->subMinutes(10))) {
                return (string) __('app.tracking.command_device_offline_hint');
            }
        } catch (\Throwable) {
            return (string) __('app.tracking.command_device_offline_hint');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function writeAudit(array $attrs): ?DeviceCommandLog
    {
        if (! $this->hasAuditTable()) {
            return null;
        }

        return DeviceCommandLog::query()->create($attrs);
    }

    /**
     * @param  list<int>  $deviceIds
     * @return list<array<string, mixed>>
     */
    private function auditHistory(array $deviceIds, int $limit): array
    {
        if (! $this->hasAuditTable() || $deviceIds === []) {
            return [];
        }

        $typeLabels = self::typeLabels();
        $statusLabels = self::statusLabels();
        $logs = DeviceCommandLog::query()
            ->whereIn('device_id', $deviceIds)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($logs->isEmpty()) {
            return [];
        }

        $names = Device::query()
            ->whereIn('id', $logs->pluck('device_id')->unique()->all())
            ->get()
            ->mapWithKeys(fn (Device $d) => [(int) $d->id => $d->mapMarkerTitle()])
            ->all();

        return $logs->map(function (DeviceCommandLog $log) use ($typeLabels, $statusLabels, $names) {
            $at = $log->updated_at ?? $log->created_at;
            $status = (string) $log->status;

            return [
                'id' => (int) $log->id,
                'device_id' => (int) $log->device_id,
                'device' => $names[(int) $log->device_id] ?? ('#'.$log->device_id),
                'type' => (string) $log->type,
                'type_label' => $typeLabels[$log->type] ?? (string) $log->type,
                'wire_type' => $log->wire_type,
                'wire_data' => (string) ($log->wire_data ?? ''),
                'protocol_profile' => $log->protocol_profile,
                'data' => (string) ($log->data ?? ''),
                'status' => $status,
                'status_label' => $statusLabels[$status] ?? $status,
                'requested_by' => $log->requested_by_name,
                'result' => (string) ($log->result ?? ''),
                'delivery' => (string) ($log->delivery ?? self::DELIVERY_QUEUE),
                'stages' => is_array($log->stages) ? $log->stages : [],
                'queue_id' => $log->queue_id,
                'time' => $at ? AppDateTime::format($at, 'display') : '',
                ...($at ? AppDateTime::apiFields($at) : []),
            ];
        })->all();
    }

    /**
     * @param  list<int>  $deviceIds
     * @return list<array<string, mixed>>
     */
    private function legacyQueueHistory(array $deviceIds, int $limit): array
    {
        if ($deviceIds === [] || ! TraccarSchema::hasTable($this->table())) {
            return [];
        }

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

        $typeLabels = self::typeLabels();
        $statusLabels = self::statusLabels();
        $deviceNames = Device::query()
            ->whereIn('id', array_values($traccarToLaravel))
            ->get()
            ->mapWithKeys(fn (Device $d) => [(int) $d->id => $d->mapMarkerTitle()])
            ->all();

        return $rows->map(function ($row) use ($typeLabels, $statusLabels, $traccarToLaravel, $deviceNames) {
            $attrs = $this->decodeAttributes($row->attributes ?? null);
            $laravelDeviceId = $traccarToLaravel[(int) $row->deviceid] ?? 0;
            $at = $this->timestampFrom($attrs);
            $status = (string) ($attrs['status'] ?? self::STATUS_PENDING);

            return [
                'id' => (int) $row->id,
                'device_id' => $laravelDeviceId,
                'device' => $deviceNames[$laravelDeviceId] ?? ('#'.$laravelDeviceId),
                'type' => (string) $row->type,
                'type_label' => $typeLabels[$row->type] ?? (string) $row->type,
                'data' => (string) ($attrs['data'] ?? ''),
                'status' => $status,
                'status_label' => $statusLabels[$status] ?? $status,
                'requested_by' => $attrs['requested_by_name'] ?? null,
                'result' => (string) ($attrs['result'] ?? ''),
                'delivery' => self::DELIVERY_QUEUE,
                'stages' => [],
                'time' => $at ? AppDateTime::format($at, 'display') : '',
                ...($at ? AppDateTime::apiFields($at) : []),
            ];
        })->all();
    }

    private function cancelQueueRow(int $commandId, User $actor): bool
    {
        if (! TraccarSchema::hasTable($this->table())) {
            return false;
        }

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
