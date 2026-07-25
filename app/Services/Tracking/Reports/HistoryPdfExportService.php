<?php

namespace App\Services\Tracking\Reports;

use App\Models\Device;
use App\Models\User;
use App\Services\Tracking\GlobalTrackingHistoryService;
use App\Services\Tracking\HistoryAnalyticsService;
use App\Support\Geo\GeoLocalityResolver;
use App\Support\Geo\StaticMapRenderer;
use App\Support\Pdf\ArabicPdfText;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * History PDF with route map + full addresses side by side and trips/stops table.
 */
class HistoryPdfExportService
{
    private const MAX_TABLE_ROWS = 120;

    /** Unique rounded coordinates to reverse-geocode for the PDF table. */
    private const MAX_UNIQUE_GEOCODES = 60;

    /** Cap vehicles per PDF to keep DomPDF + maps under memory/time budgets. */
    private const MAX_DEVICES = 8;

    public function __construct(
        private ReportService $reports,
        private GlobalTrackingHistoryService $history,
        private HistoryAnalyticsService $analytics,
        private StaticMapRenderer $staticMaps,
        private GeoLocalityResolver $geo,
    ) {}

    /**
     * @param  list<int>  $deviceIds
     */
    public function export(User $actor, array $deviceIds, Carbon $from, ?Carbon $to): Response
    {
        @ini_set('memory_limit', '1024M');
        $to = $to ?? $from->copy()->endOfDay();

        $filters = new ReportFilters(
            ignoreEmpty: false,
            showCoordinates: true,
            showAddresses: false,
            markersInsteadOfAddresses: false,
            zonesInsteadOfAddresses: false,
        );

        $allowed = collect($deviceIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->take(self::MAX_DEVICES)
            ->all();

        $sections = [];
        foreach ($allowed as $deviceId) {
            $device = Device::query()->find($deviceId);
            if (! $device) {
                continue;
            }

            $fetch = $this->history->fetchLocations($device, $from, $to);
            /** @var Collection<int, mixed> $locations */
            $locations = $fetch['locations'];
            $pointCount = $locations->count();

            $pointsPayload = $this->history->vehiclePointsPayload(
                $device,
                $locations,
                (bool) $fetch['used_fallback'],
                $fetch['fallback_reason'],
            );
            $mapPoints = is_array($pointsPayload['points'] ?? null) ? $pointsPayload['points'] : [];

            $deviceProp = $this->reports->tripsStopsFromLocations($device, $locations, $filters, [
                'attach_route_points' => false,
                'enrich_addresses' => false,
            ]);

            // Prefer full-track distance when analytics downsample under-counts.
            $distanceKm = (float) ($deviceProp['total_distance_km'] ?? 0);
            $fullDistance = $pointCount >= 2 ? $this->analytics->distanceKmForPoints($locations) : 0.0;
            if ($fullDistance > $distanceKm) {
                $distanceKm = $fullDistance;
            }

            $movingSec = (int) ($deviceProp['moving_time_seconds'] ?? 0);
            $stoppedSec = (int) ($deviceProp['stopped_time_seconds'] ?? 0);
            if ($movingSec === 0 && $stoppedSec === 0 && $pointCount >= 2) {
                $quick = $this->analytics->analyze($locations, [
                    'point_statuses' => false,
                    'include_track_points' => false,
                    'minimal_stats' => false,
                    'skip_timeline' => false,
                ]);
                $movingSec = (int) ($quick['moving_time_seconds'] ?? 0);
                $stoppedSec = (int) ($quick['stopped_time_seconds'] ?? 0);
                if ($distanceKm <= 0) {
                    $distanceKm = (float) ($quick['total_distance_km'] ?? 0);
                }
                if (((int) ($deviceProp['trip_count'] ?? 0)) === 0 && ((int) ($deviceProp['stop_count'] ?? 0)) === 0) {
                    $deviceProp['stops'] = $quick['stops'] ?? [];
                    $deviceProp['stop_count'] = (int) ($quick['stop_count'] ?? count($deviceProp['stops']));
                    // Rebuild segments from quick stats stops + existing trips if any.
                    if (($deviceProp['segments'] ?? []) === [] && ($deviceProp['stops'] ?? []) !== []) {
                        $deviceProp['segments'] = array_map(static function (array $stop): array {
                            return array_merge($stop, [
                                'kind' => 'stop',
                                'kind_label' => (string) __('app.tracking.report_seg_stop'),
                            ]);
                        }, $deviceProp['stops']);
                    }
                }
            }

            $stopMarkers = $this->stopMarkersForMap($deviceProp);
            $addresses = $this->resolveSummaryAddresses($deviceProp, $mapPoints);

            // Prefer full route map; if Google Static Maps fails (referer key),
            // fall back to OSM / a pin map of the start–end address locations.
            $map = $this->staticMaps->renderRouteMap($mapPoints, $stopMarkers, 560, 340);
            if (! ($map['ok'] ?? false)) {
                $map = $this->staticMaps->renderAddressPairMap(
                    (float) ($addresses['start_lat'] ?? 0),
                    (float) ($addresses['start_lng'] ?? 0),
                    isset($addresses['end_lat']) ? (float) $addresses['end_lat'] : null,
                    isset($addresses['end_lng']) ? (float) $addresses['end_lng'] : null,
                    560,
                    340,
                );
            }

            $rows = $this->segmentRows($deviceProp);
            $totalSegments = count($rows);
            if (count($rows) > self::MAX_TABLE_ROWS) {
                $rows = array_slice($rows, 0, self::MAX_TABLE_ROWS);
            }
            // Resolve addresses for visible rows (deduped). Without this, most rows
            // stay as "lat, lng" after the old 16-lookup budget.
            $rows = $this->attachRowAddresses($rows);

            $sections[] = [
                'device' => [
                    'device_id' => $deviceProp['device_id'] ?? $device->id,
                    'device_name' => (string) ($deviceProp['device_name'] ?? $device->mapMarkerTitle()),
                    'plate' => (string) ($deviceProp['plate'] ?? ''),
                ],
                'map_data_uri' => $map['data_uri'],
                'map_ok' => $map['ok'],
                'map_error' => $map['error'],
                'start_address' => $addresses['start'],
                'end_address' => $addresses['end'],
                'start_lat' => $addresses['start_lat'],
                'start_lng' => $addresses['start_lng'],
                'end_lat' => $addresses['end_lat'],
                'end_lng' => $addresses['end_lng'],
                'rows' => $rows,
                'distance_km' => round($distanceKm, 2),
                'moving_time' => ReportLabels::formatDuration($movingSec),
                'stopped_time' => ReportLabels::formatDuration($stoppedSec),
                'trip_count' => (int) ($deviceProp['trip_count'] ?? 0),
                'stop_count' => (int) ($deviceProp['stop_count'] ?? 0),
                'point_count' => (int) ($pointsPayload['point_count'] ?? $pointCount),
                'rows_truncated' => $totalSegments > self::MAX_TABLE_ROWS,
            ];

            unset($locations, $fetch, $deviceProp, $pointsPayload, $mapPoints, $map);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $locale = app()->getLocale();
        $rtl = $locale === 'ar';
        $title = ArabicPdfText::shape((string) __('app.tracking.history_pdf_title'));
        $noData = ArabicPdfText::shape((string) __('app.tracking.report_no_data'));
        $mapUnavailable = ArabicPdfText::shape((string) __('app.tracking.history_pdf_map_unavailable'));
        $labels = $this->pdfLabels();
        $sections = $this->shapeSections($sections);

        $html = view('tracking.exports.history-pdf', [
            'title' => $title,
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
            'rtl' => $rtl,
            'labels' => $labels,
            'sections' => $sections,
            'noData' => $noData,
            'mapUnavailable' => $mapUnavailable,
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->download($this->filename());
    }

    /**
     * @return array<string, string>
     */
    private function pdfLabels(): array
    {
        $keys = [
            'start_address' => 'history_pdf_start_address',
            'end_address' => 'history_pdf_end_address',
            'distance' => 'report_col_distance_km',
            'moving_time' => 'report_col_moving_time',
            'stopped_time' => 'report_col_stopped_time',
            'trips' => 'report_col_trips',
            'stops' => 'report_col_stops',
            'points' => 'report_col_points',
            'segment' => 'report_col_segment',
            'start' => 'report_col_start',
            'end' => 'report_col_end',
            'duration' => 'report_col_duration',
            'address' => 'report_col_address',
        ];

        $labels = [];
        foreach ($keys as $key => $langKey) {
            $labels[$key] = ArabicPdfText::shape((string) __('app.tracking.'.$langKey));
        }
        $labels['table_truncated'] = ArabicPdfText::shape(
            (string) __('app.tracking.history_pdf_table_truncated', ['count' => self::MAX_TABLE_ROWS])
        );
        $labels['map_legend'] = ArabicPdfText::shape(
            (string) __('app.tracking.history_pdf_map_legend')
        );

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $deviceProp
     * @return list<array{lat: float, lng: float, kind: string}>
     */
    private function stopMarkersForMap(array $deviceProp): array
    {
        $out = [];
        foreach ($deviceProp['stops'] ?? [] as $stop) {
            if (! is_array($stop) || ! isset($stop['lat'], $stop['lng'])) {
                continue;
            }
            $out[] = [
                'lat' => (float) $stop['lat'],
                'lng' => (float) $stop['lng'],
                'kind' => (string) ($stop['motion_key'] ?? $stop['status_key'] ?? 'parked'),
            ];
        }

        return $out;
    }

    /**
     * Full start/end addresses + coordinates for the hero map panel.
     *
     * @param  array<string, mixed>  $deviceProp
     * @param  list<array<string, mixed>>  $mapPoints
     * @return array{start: string, end: string, start_lat: ?float, start_lng: ?float, end_lat: ?float, end_lng: ?float}
     */
    private function resolveSummaryAddresses(array $deviceProp, array $mapPoints): array
    {
        $startLat = null;
        $startLng = null;
        $endLat = null;
        $endLng = null;

        $trips = $deviceProp['trips'] ?? [];
        if (is_array($trips) && $trips !== []) {
            $first = $trips[0];
            $last = $trips[count($trips) - 1];
            $startLat = isset($first['start_lat']) ? (float) $first['start_lat'] : null;
            $startLng = isset($first['start_lng']) ? (float) $first['start_lng'] : null;
            $endLat = isset($last['end_lat']) ? (float) $last['end_lat'] : null;
            $endLng = isset($last['end_lng']) ? (float) $last['end_lng'] : null;
        }

        if (($startLat === null || $startLng === null || $endLat === null || $endLng === null) && $mapPoints !== []) {
            $firstPt = $mapPoints[0];
            $lastPt = $mapPoints[count($mapPoints) - 1];
            $startLat ??= isset($firstPt['lat']) ? (float) $firstPt['lat'] : null;
            $startLng ??= isset($firstPt['lng']) ? (float) $firstPt['lng'] : null;
            $endLat ??= isset($lastPt['lat']) ? (float) $lastPt['lat'] : null;
            $endLng ??= isset($lastPt['lng']) ? (float) $lastPt['lng'] : null;
        }

        return [
            'start' => $this->fullAddressOrCoords($startLat, $startLng),
            'end' => $this->fullAddressOrCoords($endLat, $endLng),
            'start_lat' => $startLat,
            'start_lng' => $startLng,
            'end_lat' => $endLat,
            'end_lng' => $endLng,
        ];
    }

    private function fullAddressOrCoords(?float $lat, ?float $lng): string
    {
        if ($lat === null || $lng === null || ! is_finite($lat) || ! is_finite($lng)) {
            return '';
        }
        if (abs($lat) > 90 || abs($lng) > 180 || ($lat == 0.0 && $lng == 0.0)) {
            return '';
        }

        $address = $this->geo->reverseGeocodeAddress($lat, $lng);
        if (is_string($address) && trim($address) !== '') {
            // Soft-break after commas so DomPDF wraps the full street address.
            return $this->pdfWrapAddress(trim($address));
        }

        return number_format($lat, 5, '.', '').', '.number_format($lng, 5, '.', '');
    }

    /** Help DomPDF wrap long address lines without cutting with ellipsis. */
    private function pdfWrapAddress(string $address): string
    {
        // Insert a break opportunity after commas / spaces (visible as normal wrap).
        $address = preg_replace('/,\s*/u', ', ', $address) ?? $address;

        return $address;
    }

    /**
     * @param  array<string, mixed>  $deviceProp
     * @return list<array{kind: string, start: string, end: string, duration: string, distance: string, location: string, lat: ?float, lng: ?float}>
     */
    private function segmentRows(array $deviceProp): array
    {
        $rows = [];
        foreach ($deviceProp['segments'] ?? [] as $seg) {
            if (! is_array($seg)) {
                continue;
            }

            $isTrip = ($seg['kind'] ?? '') === 'trip';
            $lat = $isTrip
                ? (isset($seg['start_lat']) ? (float) $seg['start_lat'] : null)
                : (isset($seg['lat']) ? (float) $seg['lat'] : null);
            $lng = $isTrip
                ? (isset($seg['start_lng']) ? (float) $seg['start_lng'] : null)
                : (isset($seg['lng']) ? (float) $seg['lng'] : null);

            $location = '';
            if ($lat !== null && $lng !== null && is_finite($lat) && is_finite($lng)) {
                $location = number_format($lat, 5).', '.number_format($lng, 5);
            }

            $rows[] = [
                'kind' => (string) ($seg['kind_label'] ?? $seg['kind'] ?? ''),
                'start' => (string) ($seg['start_time_display'] ?? $seg['start_display'] ?? $seg['start_time'] ?? ''),
                'end' => (string) ($seg['end_time_display'] ?? $seg['end_display'] ?? $seg['end_time'] ?? ''),
                'duration' => ReportLabels::formatDuration((int) ($seg['duration_seconds'] ?? 0)),
                'distance' => $isTrip ? number_format((float) ($seg['distance_km'] ?? 0), 2) : '',
                'location' => $location,
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        return $rows;
    }

    /**
     * Resolve street/locality labels for every visible table row.
     * Nearby points share one geocode (~11 m grid) so we don't leave half the PDF as lat/lng.
     *
     * @param  list<array{kind: string, start: string, end: string, duration: string, distance: string, location: string, lat: ?float, lng: ?float}>  $rows
     * @return list<array{kind: string, start: string, end: string, duration: string, distance: string, location: string}>
     */
    private function attachRowAddresses(array $rows): array
    {
        /** @var array<string, string> $resolved */
        $resolved = [];
        $lookups = 0;

        foreach ($rows as $row) {
            $lat = $row['lat'] ?? null;
            $lng = $row['lng'] ?? null;
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                continue;
            }
            $lat = (float) $lat;
            $lng = (float) $lng;
            if (! is_finite($lat) || ! is_finite($lng) || ($lat == 0.0 && $lng == 0.0)) {
                continue;
            }

            $key = $this->geoKey($lat, $lng);
            if (array_key_exists($key, $resolved)) {
                continue;
            }
            if ($lookups >= self::MAX_UNIQUE_GEOCODES) {
                break;
            }

            $label = $this->geo->reverseGeocodeAddress($lat, $lng)
                ?? $this->geo->reverseGeocode($lat, $lng);
            $resolved[$key] = (is_string($label) && trim($label) !== '')
                ? $this->pdfWrapAddress(trim($label))
                : '';
            $lookups++;
        }

        $out = [];
        foreach ($rows as $row) {
            $lat = isset($row['lat']) && is_numeric($row['lat']) ? (float) $row['lat'] : null;
            $lng = isset($row['lng']) && is_numeric($row['lng']) ? (float) $row['lng'] : null;
            if ($lat !== null && $lng !== null) {
                $key = $this->geoKey($lat, $lng);
                if (($resolved[$key] ?? '') !== '') {
                    $row['location'] = $resolved[$key];
                } elseif (is_finite($lat) && is_finite($lng)) {
                    $row['location'] = number_format($lat, 5, '.', '').', '.number_format($lng, 5, '.', '');
                }
            }
            unset($row['lat'], $row['lng']);
            $out[] = $row;
        }

        return $out;
    }

    private function geoKey(float $lat, float $lng): string
    {
        return round($lat, 4).':'.round($lng, 4);
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    private function shapeSections(array $sections): array
    {
        return array_map(function (array $section): array {
            $section['start_address'] = ArabicPdfText::shape((string) ($section['start_address'] ?? ''));
            $section['end_address'] = ArabicPdfText::shape((string) ($section['end_address'] ?? ''));
            $section['moving_time'] = ArabicPdfText::shape((string) ($section['moving_time'] ?? ''));
            $section['stopped_time'] = ArabicPdfText::shape((string) ($section['stopped_time'] ?? ''));
            if (isset($section['device']) && is_array($section['device'])) {
                foreach (['device_name', 'plate'] as $key) {
                    if (isset($section['device'][$key])) {
                        $section['device'][$key] = ArabicPdfText::shape((string) $section['device'][$key]);
                    }
                }
            }
            $section['rows'] = array_map(function (array $row): array {
                foreach (['kind', 'start', 'end', 'duration', 'distance', 'location'] as $key) {
                    $row[$key] = ArabicPdfText::shape((string) ($row[$key] ?? ''));
                }

                return $row;
            }, $section['rows'] ?? []);

            return $section;
        }, $sections);
    }

    private function filename(): string
    {
        return 'history-route-'.now()->format('Ymd_His').'.pdf';
    }
}
