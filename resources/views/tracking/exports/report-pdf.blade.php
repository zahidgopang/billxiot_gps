<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('app.tracking.reports_title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; direction: {{ $dir }}; }
        h2 { font-size: 16px; margin: 0 0 6px; }
        .meta { color: #64748b; margin-bottom: 14px; }
        h3 { font-size: 12px; margin: 14px 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: {{ $align }}; vertical-align: top; }
        th { background: #f1f5f9; font-weight: 700; }
        .num { direction: ltr; unicode-bidi: embed; text-align: {{ $align === 'right' ? 'left' : 'right' }}; }
    </style>
</head>
<body>
@php
    $type = (string) ($report['type'] ?? 'summary');
    $formatDuration = fn (int $seconds) => \App\Services\Tracking\Reports\ReportLabels::formatDuration($seconds);
@endphp
<h2>{{ __('app.tracking.reports_title') }} — {{ __('app.tracking.report_' . $type) }}</h2>
<p class="meta">{{ $report['from'] ?? '' }} — {{ $report['to'] ?? '' }}</p>

@foreach($report['devices'] ?? [] as $device)
    @php $name = $device['device_name'] ?? $device['device_id'] ?? ''; @endphp
    <h3>{{ $name }}</h3>
    <table>
        <thead>
            <tr>
                @foreach($columns as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if($type === 'summary')
                <tr>
                    <td>{{ $name }}</td>
                    <td class="num">{{ $device['total_distance_km'] ?? 0 }}</td>
                    <td>{{ $formatDuration((int) ($device['moving_time_seconds'] ?? 0)) }}</td>
                    <td>{{ $formatDuration((int) ($device['stopped_time_seconds'] ?? 0)) }}</td>
                    <td class="num">{{ $device['max_speed_kmh'] ?? 0 }}</td>
                    <td class="num">{{ $device['average_speed_kmh'] ?? 0 }}</td>
                    <td class="num">{{ $device['stop_count'] ?? 0 }}</td>
                    <td class="num">{{ $device['overspeed_events'] ?? 0 }}</td>
                    <td>{{ $device['start_time'] ?? '' }}</td>
                    <td>{{ $device['end_time'] ?? '' }}</td>
                    <td>{{ $formatDuration((int) ($device['total_duration_seconds'] ?? 0)) }}</td>
                </tr>
            @elseif($type === 'trips')
                @foreach($device['trips'] ?? [] as $trip)
                    <tr>
                        <td>{{ $name }}</td>
                        <td>{{ $trip['start_time'] ?? '' }}</td>
                        <td>{{ $trip['end_time'] ?? '' }}</td>
                        <td class="num">{{ $trip['distance_km'] ?? 0 }}</td>
                        <td>{{ $formatDuration((int) ($trip['duration_seconds'] ?? 0)) }}</td>
                        <td>{{ $formatDuration((int) ($trip['moving_time_seconds'] ?? 0)) }}</td>
                        <td class="num">{{ $trip['max_speed_kmh'] ?? 0 }}</td>
                        <td class="num">{{ $trip['average_speed_kmh'] ?? 0 }}</td>
                    </tr>
                @endforeach
            @elseif($type === 'stops')
                @foreach($device['stops'] ?? [] as $stop)
                    <tr>
                        <td>{{ $name }}</td>
                        <td>{{ $stop['start_display'] ?? $stop['start'] ?? '' }}</td>
                        <td>{{ $stop['end_display'] ?? $stop['end'] ?? '' }}</td>
                        <td>{{ $formatDuration((int) ($stop['duration_seconds'] ?? 0)) }}</td>
                        <td class="num">{{ $stop['lat'] ?? '' }}</td>
                        <td class="num">{{ $stop['lng'] ?? '' }}</td>
                    </tr>
                @endforeach
            @elseif($type === 'events')
                @foreach($device['events'] ?? [] as $event)
                    <tr>
                        <td>{{ $name }}</td>
                        <td>{{ $event['time_display'] ?? $event['time'] ?? '' }}</td>
                        <td>{{ $event['event_type'] ?? $event['type'] ?? '' }}</td>
                        <td>{{ $event['title'] ?? '' }}</td>
                        <td>{{ $event['message'] ?? '' }}</td>
                        <td class="num">{{ $event['speed'] ?? '' }}</td>
                    </tr>
                @endforeach
            @elseif($type === 'route')
                <tr>
                    <td>{{ $name }}</td>
                    <td class="num">{{ $device['point_count'] ?? 0 }}</td>
                    <td class="num">{{ $device['total_distance_km'] ?? 0 }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endforeach
</body>
</html>
