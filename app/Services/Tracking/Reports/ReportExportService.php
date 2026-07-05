<?php

namespace App\Services\Tracking\Reports;

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
                        $this->num($trip['distance_km'] ?? 0),
                        ReportLabels::formatDuration((int) ($trip['duration_seconds'] ?? 0)),
                        ReportLabels::formatDuration((int) ($trip['moving_time_seconds'] ?? 0)),
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
        $filename = "report-{$type}-" . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($report, $type) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($this->tableRows($report, $type) as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function exportSpreadsheet(array $report): StreamedResponse
    {
        $type = (string) ($report['type'] ?? 'summary');
        $filename = "report-{$type}-" . now()->format('Ymd_His') . '.xls';
        $rows = $this->tableRows($report, $type);

        return response()->streamDownload(function () use ($rows) {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<?mso-application progid="Excel.Sheet"?>';
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="Report" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Table>';
            foreach ($rows as $row) {
                echo '<Row>';
                foreach ($row as $cell) {
                    echo '<Cell><Data ss:Type="String">' . htmlspecialchars((string) $cell, ENT_XML1) . '</Data></Cell>';
                }
                echo '</Row>';
            }
            echo '</Table></Worksheet></Workbook>';
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function exportPdf(array $report): Response
    {
        $locale = app()->getLocale();
        $type = (string) ($report['type'] ?? 'summary');
        $html = view('tracking.exports.report-pdf', [
            'report' => $report,
            'rows' => $this->tableRows($report, $type),
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'align' => $locale === 'ar' ? 'right' : 'left',
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->download("report-{$type}-" . now()->format('Ymd_His') . '.pdf');
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
