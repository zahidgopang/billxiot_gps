<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Services\Tracking\MaintenanceService;
use Illuminate\Http\Request;

/**
 * Mobile maintenance / service schedules (oil change, etc.) — same data as web hub.
 */
class MaintenanceController extends Controller
{
    use RespondsWithMobileJson;

    public function __construct(
        private MaintenanceService $maintenance,
    ) {}

    public function index(Request $request)
    {
        return $this->mobileSuccess([
            'items' => $this->maintenance->listForActor($request->user()),
        ]);
    }

    public function store(Request $request)
    {
        $id = $this->maintenance->save($request->user(), $this->validateMaintenance($request));

        if ($id === null) {
            return $this->mobileError('Could not create maintenance service.', 422);
        }

        $item = $this->findItem($request, $id);

        return $this->mobileSuccess([
            'id' => $id,
            'item' => $item,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $savedId = $this->maintenance->save(
            $request->user(),
            $this->validateMaintenance($request),
            $id,
        );

        if ($savedId === null) {
            return $this->mobileError('Could not update maintenance service.', 422);
        }

        return $this->mobileSuccess([
            'id' => $savedId,
            'item' => $this->findItem($request, $savedId),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $ok = $this->maintenance->delete($request->user(), $id);

        if (! $ok) {
            return $this->mobileError('Could not delete maintenance service.', 422);
        }

        return $this->mobileSuccess(['deleted' => true]);
    }

    public function complete(Request $request, int $id)
    {
        $validated = $request->validate([
            'device_id' => 'nullable|integer',
        ]);

        $item = $this->maintenance->complete(
            $request->user(),
            $id,
            isset($validated['device_id']) ? (int) $validated['device_id'] : null,
        );

        if ($item === null) {
            return $this->mobileError('Could not mark service as completed.', 422);
        }

        return $this->mobileSuccess([
            'item' => $item,
            'message' => 'Service marked as completed.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMaintenance(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:160',
            'device_ids' => 'required|array|min:1',
            'device_ids.*' => 'integer',
            'data_list' => 'nullable|boolean',
            'popup' => 'nullable|boolean',
            'odometer_enabled' => 'nullable|boolean',
            'odometer_interval' => 'nullable|numeric|min:0',
            'odometer_last' => 'nullable|numeric|min:0',
            'hours_enabled' => 'nullable|boolean',
            'hours_interval' => 'nullable|numeric|min:0',
            'hours_last' => 'nullable|numeric|min:0',
            'days_enabled' => 'nullable|boolean',
            'days_interval' => 'nullable|numeric|min:0',
            'days_last' => 'nullable|date',
            'trigger_odometer' => 'nullable|boolean',
            'trigger_hours' => 'nullable|boolean',
            'trigger_days' => 'nullable|boolean',
            'update_last_service' => 'nullable|boolean',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findItem(Request $request, int $id): ?array
    {
        foreach ($this->maintenance->listForActor($request->user()) as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }

        return null;
    }
}
