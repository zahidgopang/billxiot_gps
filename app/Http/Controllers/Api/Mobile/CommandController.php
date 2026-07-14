<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Concerns\ResolvesMobileDevice;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Http\Controllers\Controller;
use App\Services\Tracking\CommandService;
use Illuminate\Http\Request;

/**
 * Mobile device commands (engine stop/resume, alarm, reboot, locate, custom).
 *
 * Backed by the same Traccar command queue used by the web panel
 * (App\Services\Tracking\CommandService) so behaviour stays in sync.
 */
class CommandController extends Controller
{
    use ResolvesMobileDevice;
    use RespondsWithMobileJson;

    public function __construct(private CommandService $commands) {}

    /**
     * GET /devices/{id}/commands
     * Returns the supported command types plus recent command history.
     */
    public function index(Request $request, int $id)
    {
        $device = $this->findMobileDevice($request->user(), $id);

        return $this->mobileSuccess([
            'types' => $this->commandTypes(),
            'commands' => $this->commands->historyForDevice($request->user(), (int) $device->id),
            'can_send' => ! (bool) ($request->user()->getAttribute('limitCommands') ?? false),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * POST /devices/{id}/commands
     * Queues a command for the device.
     */
    public function store(Request $request, int $id)
    {
        $device = $this->findMobileDevice($request->user(), $id);

        $validated = $request->validate([
            'type' => 'required|string',
            'data' => 'nullable|string|max:500',
        ]);

        $result = $this->commands->send($request->user(), [
            'device_id' => (int) $device->id,
            'type' => $validated['type'],
            'data' => $validated['data'] ?? '',
        ]);

        if (! ($result['success'] ?? false)) {
            return $this->mobileError((string) ($result['message'] ?? 'Command failed'), 422);
        }

        return $this->mobileSuccess([
            'id' => $result['id'] ?? null,
            'status' => $result['status'] ?? null,
            'message' => (string) ($result['message'] ?? ''),
        ]);
    }

    /**
     * DELETE /devices/{id}/commands/{command}
     * Cancels a still-pending command.
     */
    public function destroy(Request $request, int $id, int $command)
    {
        $this->findMobileDevice($request->user(), $id);

        $ok = $this->commands->cancel($request->user(), $command);

        if (! $ok) {
            return $this->mobileError('This command can no longer be cancelled.', 422);
        }

        return $this->mobileSuccess(['canceled' => true]);
    }

    /**
     * @return list<array{type: string, label: string}>
     */
    private function commandTypes(): array
    {
        $labels = CommandService::typeLabels();

        return array_map(
            fn (string $type) => [
                'type' => $type,
                'label' => $labels[$type] ?? $type,
            ],
            CommandService::ALLOWED_TYPES,
        );
    }
}
