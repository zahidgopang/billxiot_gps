<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('app.tracking.reports_title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; direction: {{ $dir }}; }
        h2 { font-size: 16px; margin: 0 0 6px; }
        .meta { color: #64748b; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 5px; text-align: {{ $align }}; vertical-align: top; }
        th { background: #f1f5f9; font-weight: 700; }
        .num { direction: ltr; unicode-bidi: embed; text-align: {{ $align === 'right' ? 'left' : 'right' }}; }
    </style>
</head>
<body>
@php
    $type = (string) ($report['type'] ?? 'summary');
    $header = $rows[0] ?? [];
    $bodyRows = array_slice($rows, 1);
@endphp
<h2>{{ __('app.tracking.reports_title') }} — {{ __('app.tracking.report_' . $type) }}</h2>
<p class="meta">{{ $report['from'] ?? '' }} — {{ $report['to'] ?? '' }}</p>

<table>
    <thead>
        <tr>
            @foreach($header as $col)
                <th>{{ $col }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($bodyRows as $row)
            <tr>
                @foreach($row as $cell)
                    <td class="{{ is_numeric($cell) ? 'num' : '' }}">{{ $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ max(1, count($header)) }}">{{ __('app.tracking.report_no_data') }}</td>
            </tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
