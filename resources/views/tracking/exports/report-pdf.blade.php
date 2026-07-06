<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="ltr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title ?? __('app.tracking.reports_title') }}</title>
    <style>
        @page { margin: 22px 16px; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5px;
            color: #1e293b;
            margin: 0;
            direction: ltr;
        }
        h2 {
            font-size: 14px;
            margin: 0 0 6px;
            text-align: {{ ($rtl ?? false) ? 'right' : 'left' }};
        }
        .meta {
            color: #64748b;
            margin-bottom: 12px;
            text-align: {{ ($rtl ?? false) ? 'right' : 'left' }};
            direction: ltr;
            unicode-bidi: embed;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            direction: ltr;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        th {
            background: #f1f5f9;
            font-weight: 700;
            font-size: 8px;
            text-align: {{ ($rtl ?? false) ? 'right' : 'left' }};
        }
        td {
            font-size: 8.5px;
        }
        /* Shaped Arabic glyphs are pre-ordered — render LTR, align right */
        .pdf-ar {
            direction: ltr;
            unicode-bidi: embed;
            text-align: right;
        }
        .pdf-ltr {
            direction: ltr;
            unicode-bidi: embed;
            text-align: left;
        }
        .num {
            direction: ltr;
            unicode-bidi: embed;
            text-align: right;
        }
    </style>
</head>
<body>
@php
    $header = $rows[0] ?? [];
    $bodyRows = array_slice($rows, 1);
    $cellClass = function (mixed $cell) use ($rtl): string {
        if (is_int($cell) || is_float($cell)) {
            return 'num';
        }
        $text = (string) $cell;
        if ($text === '') {
            return '';
        }
        if (preg_match('/^-?\d+(\.\d+)?$/', $text) || preg_match('/^\d{4}-\d{2}-\d{2}T/', $text)) {
            return 'num';
        }

        return ($rtl ?? false) ? 'pdf-ar' : 'pdf-ltr';
    };
@endphp
<h2 class="{{ ($rtl ?? false) ? 'pdf-ar' : '' }}">{{ $title }}</h2>
<p class="meta">{{ $report['from'] ?? '' }} — {{ $report['to'] ?? '' }}</p>

<table>
    <thead>
        <tr>
            @foreach($header as $col)
                <th class="{{ ($rtl ?? false) ? 'pdf-ar' : '' }}">{{ $col }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($bodyRows as $row)
            <tr>
                @foreach($row as $cell)
                    <td class="{{ $cellClass($cell) }}">{{ $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ max(1, count($header)) }}" class="{{ ($rtl ?? false) ? 'pdf-ar' : '' }}">{{ $noData ?? __('app.tracking.report_no_data') }}</td>
            </tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
