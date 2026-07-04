<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceRouteAssignment;
use App\Models\RoutePlan;
use App\Services\AdminAuditService;
use App\Services\Routes\RouteGuidanceService;
use App\Services\Routes\RouteManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RouteController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private RouteManagementService $routes,
        private RouteGuidanceService $guidance,
        private AdminAuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizePermission('routes.view');

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $routes = RoutePlan::query()
            ->withCount(['checkpoints', 'assignments'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('start_city', 'like', "%{$search}%")
                        ->orWhere('destination_city', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, [RoutePlan::STATUS_ACTIVE, RoutePlan::STATUS_INACTIVE], true), function ($q) use ($status) {
                $q->where('status', $status);
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.routes.index', [
            'routes' => $routes,
            'panel' => $this->panelPrefix(),
            'search' => $search,
            'statusFilter' => $status,
            'stats' => [
                'total' => RoutePlan::query()->count(),
                'active' => RoutePlan::query()->where('status', RoutePlan::STATUS_ACTIVE)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorizePermission('routes.manage');

        return view('admin.routes.create', [
            'route' => new RoutePlan([
                'status' => RoutePlan::STATUS_ACTIVE,
                'arrival_radius_meters' => 500,
                'show_polyline' => true,
                'show_progress_bar' => true,
            ]),
            'panel' => $this->panelPrefix(),
            'googleMapsKey' => config('services.google.maps_key'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission('routes.manage');

        $validated = $this->validateRoute($request);
        $validated['show_polyline'] = $request->boolean('show_polyline');
        $validated['show_progress_bar'] = $request->boolean('show_progress_bar');
        $validated['auto_complete_on_arrival'] = $request->boolean('auto_complete_on_arrival');
        $checkpoints = $this->parseCheckpoints($request);

        $route = $this->routes->create($validated, $checkpoints);

        $this->audit->logCreated($route, "route {$route->name}", ['route_id' => $route->id]);

        return redirect()
            ->route($this->panelPrefix().'.routes.edit', $route)
            ->with('success', __('app.routes.saved'));
    }

    public function edit(RoutePlan $route): View
    {
        $this->authorizePermission('routes.view');
        $route->load('checkpoints');

        return view('admin.routes.edit', [
            'route' => $route,
            'panel' => $this->panelPrefix(),
            'googleMapsKey' => config('services.google.maps_key'),
        ]);
    }

    public function update(Request $request, RoutePlan $route): RedirectResponse
    {
        $this->authorizePermission('routes.manage');

        $validated = $this->validateRoute($request);
        $validated['show_polyline'] = $request->boolean('show_polyline');
        $validated['show_progress_bar'] = $request->boolean('show_progress_bar');
        $validated['auto_complete_on_arrival'] = $request->boolean('auto_complete_on_arrival');
        $checkpoints = $this->parseCheckpoints($request);

        $route = $this->routes->update($route, $validated, $checkpoints);

        $this->audit->logUpdated($route, "route {$route->name}", ['route_id' => $route->id]);

        return redirect()
            ->route($this->panelPrefix().'.routes.edit', $route)
            ->with('success', __('app.routes.saved'));
    }

    public function destroy(RoutePlan $route): RedirectResponse
    {
        $this->authorizePermission('routes.manage');

        $routeId = $route->id;
        $routeName = $route->name;

        try {
            $this->routes->delete($route);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->with('error', $e->validator->errors()->first('route') ?? __('app.routes.cannot_delete_assigned'));
        }

        $this->audit->log('deleted', "Deleted route {$routeName}", null, ['route_id' => $routeId]);

        return redirect()
            ->route($this->panelPrefix().'.routes.index')
            ->with('success', __('app.routes.deleted'));
    }

    public function assignDevice(Request $request, Device $device): JsonResponse
    {
        $this->authorizePermission('devices.manage');

        $validated = $request->validate([
            'route_id' => ['nullable', 'integer', 'exists:routes,id'],
        ]);

        if (empty($validated['route_id'])) {
            DeviceRouteAssignment::query()->where('device_id', $device->id)->delete();
        } else {
            DeviceRouteAssignment::query()->updateOrCreate(
                ['device_id' => $device->id],
                ['route_id' => (int) $validated['route_id']],
            );
        }

        return response()->json(['success' => true]);
    }

    public function directionsPreview(Request $request): JsonResponse
    {
        $this->authorizePermission('routes.manage');

        $validated = $request->validate([
            'start_lat' => ['required', 'numeric', 'between:-90,90'],
            'start_lng' => ['required', 'numeric', 'between:-180,180'],
            'destination_lat' => ['required', 'numeric', 'between:-90,90'],
            'destination_lng' => ['required', 'numeric', 'between:-180,180'],
            'checkpoints' => ['nullable', 'array'],
        ]);

        $route = new RoutePlan([
            'start_lat' => (float) $validated['start_lat'],
            'start_lng' => (float) $validated['start_lng'],
            'destination_lat' => (float) $validated['destination_lat'],
            'destination_lng' => (float) $validated['destination_lng'],
            'show_polyline' => true,
        ]);

        $checkpoints = collect($validated['checkpoints'] ?? [])->map(function ($row, $index) {
            return new \App\Models\RouteCheckpoint([
                'sequence' => (int) ($row['sequence'] ?? ($index + 1)),
                'to_lat' => (float) ($row['to_lat'] ?? 0),
                'to_lng' => (float) ($row['to_lng'] ?? 0),
            ]);
        });
        $route->setRelation('checkpoints', $checkpoints);

        $result = $this->guidance->fetchFromGoogle($route);
        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => __('app.routes.polyline_error'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'encoded_polyline' => $result['encoded_polyline'],
            'vertices' => $result['vertices'],
            'distance_km' => $result['distance_km'],
            'duration_minutes' => $result['duration_minutes'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRoute(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'start_city' => ['required', 'string', 'max:255'],
            'destination_city' => ['required', 'string', 'max:255'],
            'start_lat' => ['required', 'numeric', 'between:-90,90'],
            'start_lng' => ['required', 'numeric', 'between:-180,180'],
            'destination_lat' => ['required', 'numeric', 'between:-90,90'],
            'destination_lng' => ['required', 'numeric', 'between:-180,180'],
            'expected_distance_km' => ['nullable', 'numeric', 'min:0'],
            'expected_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'arrival_radius_meters' => ['required', 'integer', 'min:50', 'max:50000'],
            'show_polyline' => ['sometimes', 'boolean'],
            'show_progress_bar' => ['sometimes', 'boolean'],
            'auto_complete_on_arrival' => ['sometimes', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseCheckpoints(Request $request): array
    {
        $raw = $request->input('checkpoints', []);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? array_values($raw) : [];
    }
}
