<?php

namespace App\Services\Tracking;

use App\Models\DeviceCommandLog;
use Illuminate\Support\Facades\Log;

/**
 * Append pipeline stages on a command log and write a dedicated log channel.
 */
class CommandPipelineLogger
{
    public const STAGE_CREATED = 'created';

    public const STAGE_MAPPED = 'mapped';

    public const STAGE_TRACCAR_API = 'traccar_api';

    public const STAGE_QUEUED = 'queued';

    public const STAGE_SENT = 'sent';

    public const STAGE_DELIVERED = 'delivered';

    public const STAGE_EXECUTED = 'executed';

    public const STAGE_FAILED = 'failed';

    public const STAGE_TIMEOUT = 'timeout';

    public const STAGE_CANCELED = 'canceled';

    /**
     * @param  array<string, mixed>  $context
     */
    public function stage(?DeviceCommandLog $log, string $stage, string $message, array $context = []): void
    {
        $payload = array_merge([
            'stage' => $stage,
            'message' => $message,
            'at' => now()->toIso8601String(),
            'command_log_id' => $log?->id,
            'device_id' => $log?->device_id,
            'traccar_device_id' => $log?->traccar_device_id,
            'status' => $log?->status,
            'type' => $log?->type,
            'wire_type' => $log?->wire_type,
        ], $context);

        Log::channel('commands')->info("[command.{$stage}] {$message}", $payload);

        if (! $log) {
            return;
        }

        $stages = $log->stages;
        if (! is_array($stages)) {
            $stages = [];
        }
        $stages[] = [
            'stage' => $stage,
            'message' => $message,
            'at' => $payload['at'],
            'context' => $context,
        ];
        $log->stages = $stages;
        $log->save();
    }
}
