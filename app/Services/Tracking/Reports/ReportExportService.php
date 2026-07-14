<?php

namespace App\Services\Tracking\Reports;

use App\Support\Pdf\ArabicPdfText;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function export(array $report, string $format): StreamedResponse|Response
    {
        return match ($format) {
            'pdf' => $this->exportPdf($report),
            'xlsx' => $this->exportSpreadsheet($report),
            default => $this->exportCsv($report),
        };
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float>>
     */
    public function tableRows(array $report, ?string $type = null): array
    {
        $type = $type ?? (string) ($report['type'] ?? 'summary');
        $rows = [ReportLabels::columnsForType($type)];

        foreach ($report['devices'] ?? [] as $device) {
            $name = (string) ($device['device_name'] ?? $device['device_id'] ?? '');
            $plate = (string) ($device['plate'] ?? '');
            $driver = (string) ($device['driver'] ?? '');

            if ($type === 'summary') {
                $rows[] = [
                    $name,
                    $plate,
                    $driver,
                    $this->num($device['total_distance_km'] ?? 0),
                    ReportLabels::formatDuration((int) ($device['moving_time_seconds'] ?? 0)),
                    ReportLabels::formatDuration((int) ($device['stopped_time_seconds'] ?? 0)),
                    ReportLabels::formatDuration((int) ($device['idle_time_seconds'] ?? 0)),
                    ReportLabels::formatDuration((int) ($device['parking_time_seconds'] ?? 0)),
                    ReportLabels::formatDuration((int) ($device['offline_time_seconds'] ?? 0)),
                    $this->num($device['max_speed_kmh'] ?? 0),
                    $this->num($device['average_speed_kmh'] ?? 0),
                    (int) ($device['stop_count'] ?? 0),
                    (int) ($device['trip_count'] ?? 0),
                    (int) ($device['overspeed_events'] ?? 0),
                    (string) ($device['start_time'] ?? ''),
                    (string) ($device['end_time'] ?? ''),
                    ReportLabels::formatDuration((int) ($device['total_duration_seconds'] ?? 0)),
                ];
            } elseif ($type === 'trips') {
                foreach ($device['trips'] ?? [] as $trip) {
                    $rows[] = [
                        $name,
                        $plate,
                        $driver,
                        (string) ($trip['start_time'] ?? ''),
                        (string) ($trip['end_time'] ?? ''),
                        $this->coord($trip['start_lat'] ?? null),
                        $this->coord($trip['start_lng'] ?? null),
                        $this->coord($trip['end_lat'] ?? null),
                        $this->coord($trip['end_lng'] ?? null),
                        (string) ($trip['start_maps_url'] ?? ''),
                        (string) ($trip['end_maps_url'] ?? ''),
                        $this->num($trip['distance_km'] ?? 0),
                        ReportLabels::formatDuration((int) ($trip['duration_seconds'] ?? 0)),
                        ReportLabels::formatDuration((int) ($trip['moving_time_seconds'] ?? 0)),
                        (int) ($trip['stop_count'] ?? 0),
                        (int) ($trip['route_point_count'] ?? 0),
                        $this->num($trip['max_speed_kmh'] ?? 0),
                        $this->num($trip['average_speed_kmh'] ?? 0),
                    ];
                }
            } elseif ($type === 'stops') {
                foreach ($device['stops'] ?? [] as $stop) {
                    $rows[] = [
                        $name,
                        $plate,
                        (string) ($stop['status_label'] ?? ''),
                        (string) ($stop['start_display'] ?? $stop['start'] ?? ''),
                        (string) ($stop['end_display'] ?? $stop['end'] ?? ''),
                        ReportLabels::formatDuration((int) ($stop['duration_seconds'] ?? 0)),
                        $this->coord($stop['lat'] ?? null),
                        $this->coord($stop['lng'] ?? null),
                        (string) ($stop['maps_url'] ?? ''),
                    ];
                }
            } elseif ($type === 'trips_stops') {
                foreach ($device['segments'] ?? [] as $segment) {
                    $kind = (string) ($segment['kind'] ?? '');
                    $isTrip = $kind === 'trip';
                    $rows[] = [
                        $name,
                        $plate,
                        (string) ($segment['kind_label'] ?? $kind),
                        (string) ($segment['start_time'] ?? $segment['start_display'] ?? $segment['start'] ?? ''),
                        (string) ($segment['end_time'] ?? $segment['end_display'] ?? $segment['end'] ?? ''),
                        ReportLabels::formatDuration((int) ($segment['duration_seconds'] ?? 0)),
                        $isTrip ? $this->num($segment['distance_km'] ?? 0) : '',
                        $this->coord($isTrip ? ($segment['start_lat'] ?? null) : ($segment['lat'] ?? null)),
                        $this->coord($isTrip ? ($segment['start_lng'] ?? null) : ($segment['lng'] ?? null)),
                        (string) ($segment['maps_url'] ?? $segment['start_maps_url'] ?? ''),
                        $isTrip ? (int) ($segment['stop_count'] ?? 0) : '',
                    ];
                }
            } elseif ($type === 'mileage') {
                foreach ($device['days'] ?? [] as $day) {
                    $rows[] = [
                        $name,
                        $plate,
                        (string) ($day['date'] ?? ''),
                        $this->num($day['distance_km'] ?? 0),
                        ReportLabels::formatDuration((int) ($day['duration_seconds'] ?? 0)),
                        (int) ($day['point_count'] ?? 0),
                        (string) ($day['start_time'] ?? ''),
                        (string) ($day['end_time'] ?? ''),
                        (string) ($day['start_maps_url'] ?? ''),
                        (string) ($day['end_maps_url'] ?? ''),
                    ];
                }
            } elseif ($type === 'diesel') {
                $effUnit = (string) ($device['efficiency_label'] ?? $device['efficiency_unit'] ?? '');
                $rows[] = [
                    $name,
                    $plate,
                    (string) __('app.tracking.report_seg_period'),
                    (string) ($device['start_time'] ?? ''),
                    (string) ($device['end_time'] ?? ''),
                    $this->num($device['total_distance_km'] ?? 0),
                    $this->num($device['fuel_liters'] ?? ''),
                    $device['efficiency'] !== null && $device['efficiency'] !== ''
                        ? $this->num($device['efficiency']).($effUnit !== '' ? ' '.$effUnit : '')
                        : '',
                    (string) ($device['fuel_method_label'] ?? $device['fuel_method'] ?? ''),
                    $this->num($device['rate_l_per_100km'] ?? ''),
                    '',
                ];
                foreach ($device['trips'] ?? [] as $trip) {
                    $rows[] = [
                        $name,
                        $plate,
                        (string) __('app.tracking.report_seg_trip'),
                        (string) ($trip['start_time'] ?? ''),
                        (string) ($trip['end_time'] ?? ''),
                        $this->num($trip['distance_km'] ?? 0),
                        $this->num($trip['fuel_liters'] ?? ''),
                        $trip['efficiency'] !== null && $trip['efficiency'] !== ''
                            ? $this->num($trip['efficiency'])
                            : '',
                        (string) ($trip['fuel_method'] ?? ''),
                        '',
                        (string) ($trip['maps_url'] ?? $trip['start_maps_url'] ?? ''),
                    ];
                }
                foreach ($device['days'] ?? [] as $day) {
                    $rows[] = [
                        $name,
                        $plate,
                        (string) __('app.tracking.report_seg_day'),
                        (string) ($day['date'] ?? $day['start_time'] ?? ''),
                        (string) ($day['end_time'] ?? ''),
                        $this->num($day['distance_km'] ?? 0),
                        $this->num($day['fuel_liters'] ?? ''),
                        $day['efficiency'] !== null && $day['efficiency'] !== ''
                            ? $this->num($day['efficiency'])
                            : '',
                        (string) ($day['fuel_method'] ?? ''),
                        '',
                        (string) ($day['maps_url'] ?? $day['start_maps_url'] ?? ''),
                    ];
                }
            } elseif ($type === 'events') {
                foreach ($device['events'] ?? [] as $event) {
                    $rows[] = [
                        $name,
                        $plate,
                        (string) ($event['time_display'] ?? $event['time'] ?? ''),
                        (string) ($event['event_type'] ?? $event['type'] ?? ''),
                        (string) ($event['title'] ?? ''),
                        (string) ($event['message'] ?? ''),
                        (string) ($event['geofence'] ?? ''),
                        $this->coord($event['lat'] ?? null),
                        $this->coord($event['lng'] ?? null),
                        (string) ($event['maps_url'] ?? ''),
                        $this->num($event['speed'] ?? ''),
                    ];
                }
            } elseif ($type === 'positions') {
                foreach ($device['positions'] ?? [] as $position) {
                    $rows[] = [
                        $name,
                        $plate,
                        (string) ($position['time_display'] ?? $position['time'] ?? ''),
                        $this->coord($position['lat'] ?? null),
                        $this->coord($position['lng'] ?? null),
                        (string) ($position['maps_url'] ?? ''),
                        $this->num($position['speed'] ?? 0),
                        $this->coord($position['heading'] ?? null),
                        ReportLabels::formatIgnition($position['ignition'] ?? null),
                        (string) ($position['status'] ?? ''),
                    ];
                }
            } elseif ($type === 'route') {
                $rows[] = [
                    $name,
                    $plate,
                    (int) ($device['point_count'] ?? 0),
                    $this->num($device['total_distance_km'] ?? 0),
                    ReportLabels::formatDuration((int) ($device['moving_time_seconds'] ?? 0)),
                    ReportLabels::formatDuration((int) ($device['stopped_time_seconds'] ?? 0)),
                    ReportLabels::formatDuration((int) ($device['idle_time_seconds'] ?? 0)),
                    ReportLabels::formatDuration((int) ($device['parking_time_seconds'] ?? 0)),
                    ReportLabels::formatDuration((int) ($device['offline_time_seconds'] ?? 0)),
                    $this->num($device['max_speed_kmh'] ?? 0),
                    $this->num($device['average_speed_kmh'] ?? 0),
                    (int) ($device['trip_count'] ?? 0),
                    (string) ($device['start_time'] ?? ''),
                    (string) ($device['end_time'] ?? ''),
                    ReportLabels::formatDuration((int) ($device['total_duration_seconds'] ?? 0)),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function exportCsv(array $report): StreamedResponse
    {
        $type = (string) ($report['type'] ?? 'summary');
        $filename = $this->exportFilename($type, 'csv');

        return response()->streamDownload(function () use ($report, $type) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($this->tableRows($report, $type) as $row) {
                fputcsv($out, array_map(fn ($cell) => (string) $cell, $row));
            }
            fclose($out);
        }, $filename, $this->downloadHeaders(
            'text/csv; charset=UTF-8',
            $filename,
        ));
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function exportSpreadsheet(array $report): StreamedResponse
    {
        $type = (string) ($report['type'] ?? 'summary');
        $filename = $this->exportFilename($type, 'xls');
        $rows = $this->tableRows($report, $type);
        $rtl = $this->exportIsRtl();

        return response()->streamDownload(function () use ($rows, $rtl) {
            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<?mso-application progid="Excel.Sheet"?>'."\n";
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
            echo ' xmlns:o="urn:schemas-microsoft-com:office:office"';
            echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"';
            echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'."\n";
            echo '<Worksheet ss:Name="'.htmlspecialchars($this->worksheetTitle(), ENT_XML1).'">'."\n";
            if ($rtl) {
                echo '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">';
                echo '<DisplayRightToLeft/>';
                echo '</WorksheetOptions>'."\n";
            }
            echo '<Table>'."\n";
            foreach ($rows as $row) {
                echo '<Row>';
                foreach ($row as $cell) {
                    $value = htmlspecialchars((string) $cell, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $typeAttr = $this->spreadsheetCellType($cell);
                    echo '<Cell><Data ss:Type="'.$typeAttr.'">'.$value.'</Data></Cell>';
                }
                echo '</Row>'."\n";
            }
            echo '</Table>'."\n";
            echo '</Worksheet>'."\n";
            echo '</Workbook>';
        }, $filename, $this->downloadHeaders(
            'application/vnd.ms-excel; charset=UTF-8',
            $filename,
        ));
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function exportPdf(array $report): Response
    {
        $locale = app()->getLocale();
        $type = (string) ($report['type'] ?? 'summary');
        $rtl = $locale === 'ar';
        $rows = $this->tableRows($report, $type);

        if ($rtl) {
            $rows = ArabicPdfText::shapeTableRows($rows);
        }

        $html = view('tracking.exports.report-pdf', [
            'report' => $report,
            'rows' => $rows,
            'rtl' => $rtl,
            'title' => $rtl
                ? ArabicPdfText::shape((string) __('app.tracking.reports_title').' — '.(string) __('app.tracking.report_'.$type))
                : (string) __('app.tracking.reports_title').' — '.(string) __('app.tracking.report_'.$type),
            'noData' => $rtl
                ? ArabicPdfText::shape((string) __('app.tracking.report_no_data'))
                : (string) __('app.tracking.report_no_data'),
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false)
            ->download($this->exportFilename($type, 'pdf'));
    }

    private function exportIsRtl(): bool
    {
        return app()->getLocale() === 'ar';
    }

    private function worksheetTitle(): string
    {
        return (string) __('app.tracking.reports_title');
    }

    private function exportFilename(string $type, string $ext): string
    {
        $label = (string) __('app.tracking.report_'.$type);
        $slug = preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', $label) ?: $type;
        $slug = trim((string) $slug, '-');

        return 'report-'.$slug.'-'.now()->format('Ymd_His').'.'.$ext;
    }

    /**
     * @return array<string, string>
     */
    private function downloadHeaders(string $contentType, string $filename): array
    {
        $ascii = preg_replace('/[^\x20-\x7E]+/', '_', $filename) ?: 'report.dat';
        $encoded = rawurlencode($filename);

        return [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$ascii}\"; filename*=UTF-8''{$encoded}",
        ];
    }

    private function spreadsheetCellType(mixed $cell): string
    {
        if ($cell === null || $cell === '') {
            return 'String';
        }

        if (is_int($cell) || is_float($cell)) {
            return 'Number';
        }

        if (is_string($cell) && preg_match('/^-?\d+(\.\d+)?$/', $cell)) {
            return 'Number';
        }

        return 'String';
    }

    private function num(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        }

        return (string) $value;
    }

    private function coord(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 6, '.', '');
    }
}
