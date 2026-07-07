<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Services\Tracking\GlobalTrackingService;
use App\Services\Tracking\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TasksController extends Controller
{
    use ResolvesTrackingPanel;

    public function __construct(
        private GlobalTrackingService $tracking,
        private TaskService $tasks,
    ) {}

    public function index(Request $request): View
    {
        $panel = $this->resolvePanel($request);

        return view('tracking.tasks', $this->viewData($request, $panel, [
            'jsonUrl' => route('tracking.tasks.json'),
            'storeUrl' => route('tracking.tasks.store'),
            'destroyAllUrl' => route('tracking.tasks.destroy-all'),
            'exportUrl' => route('tracking.tasks.export'),
            'baseUrl' => route('tracking.tasks.index'),
            'priorities' => TaskService::PRIORITIES,
            'statuses' => TaskService::STATUSES,
        ]));
    }

    public function json(Request $request): JsonResponse
    {
        return $this->noStoreJson([
            'items' => $this->tasks->listForActor($request->user(), [
                'device_id' => (int) $request->query('device_id', 0),
                'from' => $request->query('from'),
                'to' => $request->query('to'),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'name' => 'required|string|max:160',
            'start' => 'nullable|string|max:255',
            'destination' => 'nullable|string|max:255',
            'priority' => 'nullable|in:' . implode(',', TaskService::PRIORITIES),
            'status' => 'nullable|in:' . implode(',', TaskService::STATUSES),
            'time_from' => 'nullable|date',
            'time_to' => 'nullable|date',
        ]);

        $task = $this->tasks->create($request->user(), $validated);

        return $this->noStoreJson(['success' => $task !== null, 'id' => $task?->id]);
    }

    public function destroy(Request $request, int $task): JsonResponse
    {
        $ok = $this->tasks->delete($request->user(), $task);

        return $this->noStoreJson(['success' => $ok]);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $count = $this->tasks->deleteAllForActor($request->user());

        return $this->noStoreJson(['success' => true, 'deleted' => $count]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->tasks->listForActor($request->user(), [
            'device_id' => (int) $request->query('device_id', 0),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]);

        $filename = 'tasks-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel renders Arabic/Unicode correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Time', 'Name', 'Object', 'Start', 'Destination', 'Priority', 'Status']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['time_from'] ?? '',
                    $row['name'],
                    $row['device_name'],
                    $row['start'],
                    $row['destination'],
                    $row['priority'],
                    $row['status'],
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function viewData(Request $request, string $panel, array $extra = []): array
    {
        return array_merge([
            'panel' => $panel,
            'layout' => $this->layoutForPanel($panel),
            'vehicles' => $this->tracking->listItemsForActor($request->user()),
            'hubRoutes' => $this->trackingHubRoutes($panel),
        ], $extra);
    }
}
