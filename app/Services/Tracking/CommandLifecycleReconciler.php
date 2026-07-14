<?php

namespace App\Services\Tracking;

use App\Models\DeviceCommandLog;
use App\Support\Traccar\TraccarSchema;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Advance command statuses from Traccar events:
 *   queuedCommandSent → delivered
 *   commandResult     → executed
 * and mark stale in-flight commands as timeout.
 */
class CommandLifecycleReconciler
{
    public function __construct(
        private CommandPipelineLogger $pipeline,
    ) {}

    /**
     * @return array{delivered:int, executed:int, timed_out:int}
     */
    public function reconcile(int $limit = 200): array
    {
        $stats = ['delivered' => 0, 'executed' => 0, 'timed_out' => 0];

        if (! Schema::hasTable('device_command_logs')) {
            return $stats;
        }

        $stats['delivered'] = $this->applyQueuedCommandSent($limit);
        $stats['executed'] = $this->applyCommandResults($limit);
        $stats['timed_out'] = $this->applyTimeouts();

        return $stats;
    }

    private function applyQueuedCommandSent(int $limit): int
    {
        $eventsTable = config('traccar.tables.events', 'tc_events');
        if (! TraccarSchema::hasTable($eventsTable)) {
            return 0;
        }

        $open = DeviceCommandLog::query()
            ->whereIn('status', [
                CommandService::STATUS_PENDING,
                CommandService::STATUS_SENT,
            ])
            ->whereNotNull('queue_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($open->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach ($open as $log) {
            $event = DB::table($eventsTable)
                ->where('type', 'queuedCommandSent')
                ->where('deviceid', $log->traccar_device_id)
                ->where('id', '>', (int) ($log->last_event_id ?? 0))
                ->orderBy('id')
                ->get(['id', 'attributes', 'eventtime'])
                ->first(function ($row) use ($log) {
                    $attrs = $this->decode($row->attributes ?? null);

                    return (int) ($attrs['id'] ?? 0) === (int) $log->queue_id;
                });

            if (! $event) {
                continue;
            }

            $log->status = CommandService::STATUS_DELIVERED;
            $log->delivered_at = $this->parseTime($event->eventtime) ?? now();
            $log->last_event_id = (int) $event->id;
            $log->result = trim((string) ($log->result ? $log->result.' | ' : '').'Delivered to device (queuedCommandSent)');
            $log->save();

            $this->pipeline->stage(
                $log,
                CommandPipelineLogger::STAGE_DELIVERED,
                'Traccar queuedCommandSent — command handed to device socket',
                ['event_id' => (int) $event->id, 'queue_id' => (int) $log->queue_id]
            );
            $count++;
        }

        return $count;
    }

    private function applyCommandResults(int $limit): int
    {
        $eventsTable = config('traccar.tables.events', 'tc_events');
        if (! TraccarSchema::hasTable($eventsTable)) {
            return 0;
        }

        $open = DeviceCommandLog::query()
            ->whereIn('status', [
                CommandService::STATUS_SENT,
                CommandService::STATUS_DELIVERED,
            ])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($open->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach ($open as $log) {
            $sentAt = $log->created_at?->copy()->subSeconds(5) ?? now()->subHour();
            $query = DB::table($eventsTable)
                ->where('type', 'commandResult')
                ->where('deviceid', $log->traccar_device_id)
                ->where('id', '>', (int) ($log->last_event_id ?? 0))
                ->orderBy('id');

            if (TraccarSchema::hasColumn($eventsTable, 'eventtime')) {
                $query->where('eventtime', '>=', $sentAt->utc()->format('Y-m-d H:i:s'));
            }

            $event = $query->first();
            if (! $event) {
                continue;
            }

            $attrs = $this->decode($event->attributes ?? null);
            $resultText = (string) ($attrs['result'] ?? json_encode($attrs));

            $log->status = CommandService::STATUS_EXECUTED;
            $log->executed_at = $this->parseTime($event->eventtime) ?? now();
            $log->last_event_id = (int) $event->id;
            if (! $log->delivered_at) {
                $log->delivered_at = $log->executed_at;
            }
            $log->result = $resultText !== '' ? $resultText : (string) ($log->result ?? 'OK');
            $log->save();

            $this->pipeline->stage(
                $log,
                CommandPipelineLogger::STAGE_EXECUTED,
                'Device acknowledgement (commandResult)',
                ['event_id' => (int) $event->id, 'result' => $resultText]
            );
            $count++;
        }

        return $count;
    }

    private function applyTimeouts(): int
    {
        $minutes = max(1, (int) config('device_commands.timeout_minutes', 15));
        $cutoff = now()->subMinutes($minutes);

        $stale = DeviceCommandLog::query()
            ->whereIn('status', [
                CommandService::STATUS_PENDING,
                CommandService::STATUS_SENT,
                CommandService::STATUS_DELIVERED,
            ])
            ->where('created_at', '<', $cutoff)
            ->limit(200)
            ->get();

        $count = 0;
        foreach ($stale as $log) {
            // Delivered without ACK can stay delivered until timeout → timeout
            $log->status = CommandService::STATUS_TIMEOUT;
            $log->timeout_at = now();
            $log->result = trim((string) ($log->result ? $log->result.' | ' : '')
                ."Timed out after {$minutes} minutes without device acknowledgement");
            $log->save();

            $this->pipeline->stage(
                $log,
                CommandPipelineLogger::STAGE_TIMEOUT,
                "No commandResult within {$minutes} minutes",
                ['timeout_minutes' => $minutes]
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  mixed  $raw
     * @return array<string, mixed>
     */
    private function decode($raw): array
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

    private function parseTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
