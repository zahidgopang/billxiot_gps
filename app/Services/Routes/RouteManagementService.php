<?php

namespace App\Services\Routes;

use App\Models\RouteCheckpoint;
use App\Models\RoutePlan;
use App\Support\Geo\GeoLocalityResolver;
use App\Support\Geo\GeoMath;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RouteManagementService
{
    public function __construct(
        private RouteGuidanceService $guidance,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $checkpoints
     */
    public function create(array $data, array $checkpoints = []): RoutePlan
    {
        return DB::transaction(function () use ($data, $checkpoints) {
            $route = RoutePlan::query()->create($this->normalizeRouteData($data));
            $this->syncCheckpoints($route, $checkpoints);

            if (! array_key_exists('expected_distance_km', $data) || ! array_key_exists('expected_duration_minutes', $data)) {
                $route->recalculateTotalsFromCheckpoints();
                $route->save();
            }

            $this->syncRoutePolyline($route);

            return $route->fresh(['checkpoints']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>|null  $checkpoints
     */
    public function update(RoutePlan $route, array $data, ?array $checkpoints = null): RoutePlan
    {
        return DB::transaction(function () use ($route, $data, $checkpoints) {
            $route->fill($this->normalizeRouteData($data));
            $route->save();

            if ($checkpoints !== null) {
                $this->syncCheckpoints($route, $checkpoints);
            }

            if (Arr::get($data, 'recalculate_totals', false)) {
                $route->recalculateTotalsFromCheckpoints();
                $route->save();
            }

            $this->syncRoutePolyline($route);

            return $route->fresh(['checkpoints']);
        });
    }

    public function delete(RoutePlan $route): void
    {
        if ($route->assignments()->exists()) {
            throw ValidationException::withMessages([
                'route' => [__('app.routes.cannot_delete_assigned')],
            ]);
        }

        $route->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $checkpoints
     */
    public function syncCheckpoints(RoutePlan $route, array $checkpoints): void
    {
        $route->checkpoints()->delete();

        foreach (array_values($checkpoints) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $fromLat = (float) ($row['from_lat'] ?? 0);
            $fromLng = (float) ($row['from_lng'] ?? 0);
            $toLat = (float) ($row['to_lat'] ?? 0);
            $toLng = (float) ($row['to_lng'] ?? 0);
            if (abs($toLat) < 1e-6 && abs($toLng) < 1e-6) {
                continue;
            }

            $distance = (float) ($row['distance_km'] ?? 0);
            if ($distance <= 0 && ($fromLat || $toLat)) {
                $distance = GeoMath::haversineKm($fromLat, $fromLng, $toLat, $toLng);
            }

            RouteCheckpoint::query()->create([
                'route_id' => $route->id,
                'sequence' => (int) ($row['sequence'] ?? ($index + 1)),
                'from_location' => (string) ($row['from_location'] ?? ''),
                'to_location' => (string) ($row['to_location'] ?? ''),
                'from_lat' => $fromLat,
                'from_lng' => $fromLng,
                'to_lat' => $toLat,
                'to_lng' => $toLng,
                'distance_km' => round($distance, 2),
                'expected_duration_minutes' => (int) ($row['expected_duration_minutes'] ?? 0),
                'speed_limit_kmh' => isset($row['speed_limit_kmh']) ? (int) $row['speed_limit_kmh'] : null,
                'notes' => $row['notes'] ?? null,
            ]);
        }
    }

    private function syncRoutePolyline(RoutePlan $route): void
    {
        $route->refresh();
        $route->load('checkpoints');
        $preserveCheckpoints = $route->checkpoints->isNotEmpty();

        if (! $route->show_polyline) {
            $route->forceFill([
                'encoded_polyline' => null,
                'guided_distance_km' => null,
                'guided_duration_minutes' => null,
                'polyline_generated_at' => null,
            ])->save();
            $this->guidance->forgetCache($route);

            return;
        }

        // Keep user checkpoints — fetchFromGoogle uses them as Directions waypoints.
        $result = $this->guidance->fetchFromGoogle($route);
        if ($result === null || ($result['encoded_polyline'] ?? '') === '') {
            session()->flash('warning', (string) __('app.routes.polyline_save_warning'));

            return;
        }

        $this->guidance->persistGuidanceResult($route, $result);
        $route->refresh();

        if (! $preserveCheckpoints) {
            $checkpointRows = $this->guidance->buildCheckpointRows($route, $result);
            if ($checkpointRows !== []) {
                $this->syncCheckpoints($route, $checkpointRows);
                $route->refresh();
                $route->load('checkpoints');
                $route->recalculateTotalsFromCheckpoints();
                $route->save();
            }
        }

        if ($route->guided_distance_km > 0) {
            $updates = [];
            if ((float) $route->expected_distance_km <= 0) {
                $updates['expected_distance_km'] = $route->guided_distance_km;
            }
            if ((int) $route->expected_duration_minutes <= 0 && $route->guided_duration_minutes > 0) {
                $updates['expected_duration_minutes'] = $route->guided_duration_minutes;
            }
            if ($updates !== []) {
                $route->update($updates);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeRouteData(array $data): array
    {
        return [
            'name' => (string) ($data['name'] ?? ''),
            'start_city' => app(GeoLocalityResolver::class)->sanitizeLabel((string) ($data['start_city'] ?? ''))
                ?? (string) ($data['start_city'] ?? ''),
            'destination_city' => app(GeoLocalityResolver::class)->sanitizeLabel((string) ($data['destination_city'] ?? ''))
                ?? (string) ($data['destination_city'] ?? ''),
            'start_lat' => (float) ($data['start_lat'] ?? 0),
            'start_lng' => (float) ($data['start_lng'] ?? 0),
            'destination_lat' => (float) ($data['destination_lat'] ?? 0),
            'destination_lng' => (float) ($data['destination_lng'] ?? 0),
            'expected_distance_km' => (float) ($data['expected_distance_km'] ?? 0),
            'expected_duration_minutes' => (int) ($data['expected_duration_minutes'] ?? 0),
            'arrival_radius_meters' => (int) ($data['arrival_radius_meters'] ?? 500),
            'show_polyline' => (bool) ($data['show_polyline'] ?? false),
            'show_progress_bar' => (bool) ($data['show_progress_bar'] ?? false),
            'auto_complete_on_arrival' => (bool) ($data['auto_complete_on_arrival'] ?? false),
            'status' => (string) ($data['status'] ?? RoutePlan::STATUS_ACTIVE),
        ];
    }
}
