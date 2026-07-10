<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Concerns\ResolvesTrackingPanel;
use App\Http\Controllers\Controller;
use App\Services\Tracking\CommandService;
use App\Services\Tracking\GlobalTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommandsController extends Controller
{
    use ResolvesTrackingPanel;

    public function __construct(
        private GlobalTrackingService $tracking,
        private CommandService $commands,
    ) {}

    public function index(Request $request): View
    {
        $panel = $this->resolvePanel($request);

        return view('tracking.commands', $this->viewData($request, $panel, [
            'jsonUrl' => route('tracking.commands.json'),
            'sendUrl' => route('tracking.commands.send'),
            'cancelUrl' => route('tracking.commands.cancel', ['command' => 0]),
            'commandTypes' => CommandService::typeLabels(),
        ]));
    }

    public function json(Request $request): JsonResponse
    {
        return $this->noStoreJson([
            'commands' => $this->commands->historyForActor($request->user()),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer',
            'type' => 'required|string',
            'data' => 'nullable|string|max:500',
        ]);

        $result = $this->commands->send($request->user(), $validated);

        return $this->noStoreJson($result, ($result['success'] ?? false) ? 200 : 422);
    }

    public function cancel(Request $request, int $command): JsonResponse
    {
        $ok = $this->commands->cancel($request->user(), $command);

        return $this->noStoreJson(['success' => $ok], $ok ? 200 : 422);
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
            'trackingUi' => $this->trackingUiFor($request->user()),
        ], $extra);
    }
}
