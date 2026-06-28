<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Report</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px;text-align:left}</style>
</head><body>
<h2>{{ __('app.tracking.reports_title') }} — {{ strtoupper($report['type'] ?? '') }}</h2>
<p>{{ $report['from'] ?? '' }} — {{ $report['to'] ?? '' }}</p>
@foreach($report['devices'] ?? [] as $device)
<h3>{{ $device['device_name'] ?? $device['device_id'] ?? '' }}</h3>
<table><tbody>
@foreach($device as $key => $val)
@if(!in_array($key, ['device_id','device_name','points','trips','stops','events'], true) && !is_array($val))
<tr><th>{{ $key }}</th><td>{{ $val }}</td></tr>
@endif
@endforeach
</tbody></table>
@endforeach
</body></html>
