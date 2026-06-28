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
            foreach ($this->csvRows($report, $type) as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function exportSpreadsheet(array $report): StreamedResponse
    {
        // Minimal XLSX-compatible XML spreadsheet (Excel opens without extra packages).
        $type = (string) ($report['type'] ?? 'summary');
        $filename = "report-{$type}-" . now()->format('Ymd_His') . '.xls';
        $rows = $this->csvRows($report, $type);

        return response()->streamDownload(function () use ($rows) {
            echo '<?xml version="1.0"?>';
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
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel']);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function exportPdf(array $report): Response
    {
        $html = view('tracking.exports.report-pdf', ['report' => $report])->render();
        $type = (string) ($report['type'] ?? 'summary');

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->download("report-{$type}-" . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<list<string|int|float>>
     */
    private function csvRows(array $report, string $type): array
    {
        $rows = [['Device', 'Field', 'Value']];

        foreach ($report['devices'] ?? [] as $device) {
            $name = (string) ($device['device_name'] ?? $device['device_id'] ?? '');

            if ($type === 'summary') {
                foreach ($device as $key => $val) {
                    if (in_array($key, ['device_id', 'device_name'], true) || is_array($val)) {
                        continue;
                    }
                    $rows[] = [$name, $key, (string) $val];
                }
            } elseif ($type === 'trips') {
                foreach ($device['trips'] ?? [] as $i => $trip) {
                    $rows[] = [$name, 'trip_' . ($i + 1), json_encode($trip)];
                }
            } elseif ($type === 'stops') {
                foreach ($device['stops'] ?? [] as $i => $stop) {
                    $rows[] = [$name, 'stop_' . ($i + 1), json_encode($stop)];
                }
            } elseif ($type === 'events') {
                foreach ($device['events'] ?? [] as $i => $event) {
                    $rows[] = [$name, 'event_' . ($i + 1), ($event['title'] ?? '') . ' @ ' . ($event['time'] ?? '')];
                }
            } elseif ($type === 'route') {
                $rows[] = [$name, 'points', (string) ($device['point_count'] ?? 0)];
            }
        }

        return $rows;
    }
}
