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
        $html = view('tracking.exports.report-pdf', [
            'report' => $report,
            'columns' => ReportLabels::columnsForType((string) ($report['type'] ?? 'summary')),
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'align' => $locale === 'ar' ? 'right' : 'left',
        ])->render();
        $type = (string) ($report['type'] ?? 'summary');

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->download("report-{$type}-" . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float>>
     */
    private function tableRows(array $report, string $type): array
    {
        $rows = [ReportLabels::columnsForType($type)];

        foreach ($report['devices'] ?? [] as $device) {
            $name = (string) ($device['device_name'] ?? $device['device_id'] ?? '');

            if ($type === 'summary') {
                $rows[] = [
                    $name,
                    $this->num($device['total_distance_km'] ?? 0),
                    ReportLabels::formatDuration((int) ($device['moving_time_seconds'] ?? 0)),
                    ReportLabels::formatDuration((int) ($device['stopped_time_seconds'] ?? 0)),
                    $this->num($device['max_speed_kmh'] ?? 0),
                    $this->num($device['average_speed_kmh'] ?? 0),
                    (int) ($device['stop_count'] ?? 0),
                    (int) ($device['overspeed_events'] ?? 0),
                    (string) ($device['start_time'] ?? ''),
                    (string) ($device['end_time'] ?? ''),
                    ReportLabels::formatDuration((int) ($device['total_duration_seconds'] ?? 0)),
                ];
            } elseif ($type === 'trips') {
                foreach ($device['trips'] ?? [] as $trip) {
                    $rows[] = [
                        $name,
                        (string) ($trip['start_time'] ?? ''),
                        (string) ($trip['end_time'] ?? ''),
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
                        (string) ($event['time_display'] ?? $event['time'] ?? ''),
                        (string) ($event['event_type'] ?? $event['type'] ?? ''),
                        (string) ($event['title'] ?? ''),
                        (string) ($event['message'] ?? ''),
                        $this->num($event['speed'] ?? ''),
                    ];
                }
            } elseif ($type === 'route') {
                $rows[] = [
                    $name,
                    (int) ($device['point_count'] ?? 0),
                    $this->num($device['total_distance_km'] ?? 0),
                ];
            }
        }

        return $rows;
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
