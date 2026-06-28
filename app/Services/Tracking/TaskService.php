<?php

namespace App\Services\Tracking;

use App\Models\Device;
use App\Models\TrackingTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Carbon as SupportCarbon;

class TaskService
{
    /** @var list<string> */
    public const PRIORITIES = ['low', 'normal', 'high'];

    /** @var list<string> */
    public const STATUSES = ['new', 'in_progress', 'done', 'cancelled'];

    public function __construct(
        private GlobalTrackingService $tracking,
    ) {}

    /**
     * @param  array{device_id?:int|null, from?:string|null, to?:string|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function listForActor(User $actor, array $filters = []): array
    {
        $deviceIds = $this->tracking->allowedDeviceIds($actor);
        if ($deviceIds === []) {
            return [];
        }

        $query = TrackingTask::query()->whereIn('device_id', $deviceIds);

        $deviceFilter = (int) ($filters['device_id'] ?? 0);
        if ($deviceFilter > 0 && in_array($deviceFilter, $deviceIds, true)) {
            $query->where('device_id', $deviceFilter);
        }

        if (! empty($filters['from'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('time_from')->orWhere('time_from', '>=', Carbon::parse($filters['from']));
            });
        }

        if (! empty($filters['to'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('time_to')->orWhere('time_to', '<=', Carbon::parse($filters['to']));
            });
        }

        $tasks = $query->orderByDesc('time_from')->orderByDesc('id')->get();

        $devices = Device::query()
            ->whereIn('id', $tasks->pluck('device_id')->unique()->all())
            ->get()
            ->keyBy('id');

        return $tasks->map(function (TrackingTask $task) use ($devices) {
            $device = $devices->get($task->device_id);

            return [
                'id' => (int) $task->id,
                'device_id' => (int) $task->device_id,
                'device_name' => $device?->mapMarkerTitle() ?? ('#' . $task->device_id),
                'name' => (string) $task->name,
                'start' => (string) ($task->start ?? ''),
                'destination' => (string) ($task->destination ?? ''),
                'priority' => (string) $task->priority,
                'status' => (string) $task->status,
                'time_from' => optional($task->time_from)->format('Y-m-d H:i'),
                'time_to' => optional($task->time_to)->format('Y-m-d H:i'),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): ?TrackingTask
    {
        $deviceId = (int) ($data['device_id'] ?? 0);
        if (! in_array($deviceId, $this->tracking->filterAllowedIds($actor, [$deviceId]), true)) {
            return null;
        }

        return TrackingTask::create([
            'device_id' => $deviceId,
            'created_by' => (int) $actor->id,
            'name' => (string) ($data['name'] ?? 'Task'),
            'start' => $this->nullableString($data['start'] ?? null),
            'destination' => $this->nullableString($data['destination'] ?? null),
            'priority' => $this->normalizePriority($data['priority'] ?? null),
            'status' => $this->normalizeStatus($data['status'] ?? null),
            'time_from' => $this->nullableDate($data['time_from'] ?? null),
            'time_to' => $this->nullableDate($data['time_to'] ?? null),
        ]);
    }

    public function delete(User $actor, int $taskId): bool
    {
        $task = TrackingTask::find($taskId);
        if (! $task) {
            return false;
        }

        if (! in_array((int) $task->device_id, $this->tracking->allowedDeviceIds($actor), true)) {
            return false;
        }

        return (bool) $task->delete();
    }

    public function deleteAllForActor(User $actor): int
    {
        $deviceIds = $this->tracking->allowedDeviceIds($actor);
        if ($deviceIds === []) {
            return 0;
        }

        return TrackingTask::whereIn('device_id', $deviceIds)->delete();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    private function nullableDate(mixed $value): ?SupportCarbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizePriority(mixed $value): string
    {
        $value = strtolower((string) $value);

        return in_array($value, self::PRIORITIES, true) ? $value : 'normal';
    }

    private function normalizeStatus(mixed $value): string
    {
        $value = strtolower((string) $value);

        return in_array($value, self::STATUSES, true) ? $value : 'new';
    }
}
