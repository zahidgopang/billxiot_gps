<?php

namespace App\Services\Tracking\Reports;

use App\Contracts\Geofences\GeofenceStoreInterface;
use App\Models\Device;
use App\Models\Geofence;
use App\Support\Geo\GeoLocalityResolver;
use App\Support\Traccar\GeofenceWkt;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolve human location labels for report rows (zone / marker / address).
 *
 * Markers: small circle geofences (≤ 300 m) treated as POI-style markers.
 * Zones: any containing geofence.
 * Addresses: Google reverse-geocode (falls back to coordinates when geocode fails).
 */
final class ReportLocationLabelResolver
{
    private const MARKER_MAX_RADIUS_M = 300;

    /** @var array<int, Collection<int, object>> */
    private array $geofenceCache = [];

    private ?Collection $accountGeofences = null;

    public function __construct(
        private GeofenceStoreInterface $geofences,
        private GeoLocalityResolver $geo,
    ) {}

    public function clearCache(): void
    {
        $this->geofenceCache = [];
        $this->accountGeofences = null;
    }

    public function geocodeConfigured(): bool
    {
        return trim((string) config('services.google.maps_key', '')) !== '';
    }

    /**
     * @return array{address: ?string, marker: ?string, zone: ?string, location_label: ?string}
     */
    public function resolve(?float $lat, ?float $lng, Device $device, ReportFilters $filters): array
    {
        $empty = [
            'address' => null,
            'marker' => null,
            'zone' => null,
            'location_label' => null,
        ];

        if ($lat === null || $lng === null || ! is_finite($lat) || ! is_finite($lng)) {
            return $empty;
        }
        if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) {
            return $empty;
        }

        if (! $filters->needsLocationLabels()) {
            return $empty;
        }

        $marker = null;
        $zone = null;

        if ($filters->markersInsteadOfAddresses || $filters->zonesInsteadOfAddresses) {
            [$marker, $zone] = $this->findMarkerAndZone($lat, $lng, $device);
        }

        // Standard priority when multiple location options are enabled:
        // Marker → Zone → Address. Coordinates are a separate column toggle.
        $address = null;
        if ($filters->showAddresses) {
            $address = $this->geo->reverseGeocodeAddress($lat, $lng);
        }

        $label = null;
        if ($filters->markersInsteadOfAddresses && $marker !== null && $marker !== '') {
            $label = $marker;
        } elseif ($filters->zonesInsteadOfAddresses && $zone !== null && $zone !== '') {
            $label = $zone;
        } elseif ($filters->showAddresses) {
            $label = ($address !== null && $address !== '')
                ? $address
                : null;
        }

        return [
            'address' => $address,
            'marker' => $marker,
            'zone' => $zone,
            'location_label' => $label,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function enrichPointRow(array $row, Device $device, ReportFilters $filters, string $latKey = 'lat', string $lngKey = 'lng', string $labelKey = 'address'): array
    {
        $lat = isset($row[$latKey]) ? (float) $row[$latKey] : null;
        $lng = isset($row[$lngKey]) ? (float) $row[$lngKey] : null;
        $resolved = $this->resolve($lat, $lng, $device, $filters);
        $row[$labelKey] = $resolved['location_label'];
        $row['location_label'] = $resolved['location_label'];
        $row['resolved_address'] = $resolved['address'];
        $row['resolved_marker'] = $resolved['marker'];
        $row['resolved_zone'] = $resolved['zone'];

        return $row;
    }

    /**
     * @return array{0: ?string, 1: ?string} [marker, zone]
     */
    private function findMarkerAndZone(float $lat, float $lng, Device $device): array
    {
        $marker = null;
        $zone = null;

        foreach ($this->geofencesFor($device) as $g) {
            $type = (string) ($g->type ?? 'polygon');
            $area = isset($g->area) ? (string) $g->area : null;
            $inside = GeofenceWkt::containsPoint(
                $lat,
                $lng,
                $area,
                $type,
                $g->coords ?? null,
                $g->center ?? null,
                isset($g->radius) ? (int) $g->radius : null,
            );

            if (! $inside) {
                continue;
            }

            $name = trim((string) ($g->name ?? ''));
            if ($name === '') {
                continue;
            }

            $zone ??= $name;

            $radius = $this->circleRadiusMeters($g);
            $isCircle = $type === 'circle'
                || ($area !== null && str_starts_with(strtoupper(ltrim($area)), 'CIRCLE'));

            if ($isCircle && $radius !== null && $radius <= self::MARKER_MAX_RADIUS_M) {
                $marker ??= $name;
            }
        }

        return [$marker, $zone];
    }

    /**
     * @param  object{type?: string, radius?: int|null, area?: string|null}  $g
     */
    private function circleRadiusMeters(object $g): ?int
    {
        if (isset($g->radius) && $g->radius !== null && $g->radius !== '') {
            return max(0, (int) $g->radius);
        }

        $area = isset($g->area) ? trim((string) $g->area) : '';
        if ($area !== '' && preg_match('/CIRCLE\s*\(\s*[-\d.]+\s+[-\d.]+\s*,\s*([-\d.]+)\s*\)/i', $area, $m)) {
            return max(0, (int) round((float) $m[1]));
        }

        return null;
    }

    /**
     * @return Collection<int, object>
     */
    private function geofencesFor(Device $device): Collection
    {
        $id = (int) $device->id;
        if (! array_key_exists($id, $this->geofenceCache)) {
            $linked = $this->geofences->forDevice($device);
            $this->geofenceCache[$id] = $linked->isNotEmpty()
                ? $linked
                : $this->accountGeofencesFallback();
        }

        return $this->geofenceCache[$id];
    }

    /**
     * When a device has no linked geofences, still resolve against account zones/markers.
     *
     * @return Collection<int, object>
     */
    private function accountGeofencesFallback(): Collection
    {
        if ($this->accountGeofences !== null) {
            return $this->accountGeofences;
        }

        if (TraccarMode::readsTraccar() && TraccarSchema::hasGeofences()) {
            $table = config('traccar.tables.geofences', 'tc_geofences');
            $this->accountGeofences = DB::table($table)
                ->orderBy('id')
                ->limit(500)
                ->get()
                ->map(function ($row) {
                    $attrs = [];
                    if (! empty($row->attributes)) {
                        $decoded = is_string($row->attributes)
                            ? json_decode($row->attributes, true)
                            : $row->attributes;
                        $attrs = is_array($decoded) ? $decoded : [];
                    }

                    $type = (string) ($attrs['type'] ?? 'polygon');
                    $area = isset($row->area) ? (string) $row->area : null;
                    if ($area && str_starts_with(strtoupper(ltrim($area)), 'CIRCLE')) {
                        $type = 'circle';
                    }

                    return (object) [
                        'id' => (int) $row->id,
                        'name' => (string) ($row->name ?? ''),
                        'type' => $type,
                        'area' => $area,
                        'center' => $attrs['center'] ?? null,
                        'coords' => $attrs['coords'] ?? null,
                        'radius' => isset($attrs['radius']) ? (int) $attrs['radius'] : null,
                    ];
                });

            return $this->accountGeofences;
        }

        $this->accountGeofences = Geofence::query()
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn (Geofence $g) => (object) [
                'id' => $g->id,
                'name' => $g->name,
                'type' => $g->type,
                'area' => $g->area ?? null,
                'center' => $g->center ? json_decode($g->center, true) : null,
                'coords' => $g->coords ? json_decode($g->coords, true) : null,
                'radius' => $g->radius,
            ]);

        return $this->accountGeofences;
    }
}
