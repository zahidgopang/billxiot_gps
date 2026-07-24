<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="ltr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>
    <style>
        @page { margin: 12px 10px; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #1e293b;
            margin: 0;
            direction: ltr;
        }
        h1 {
            font-size: 13px;
            margin: 0 0 2px;
        }
        .meta {
            color: #64748b;
            margin: 0 0 6px;
            direction: ltr;
            unicode-bidi: embed;
        }
        .vehicle-title {
            font-size: 11px;
            font-weight: 700;
            margin: 0 0 6px;
        }
        /*
         * Side-by-side hero: DomPDF needs table-layout:fixed + col widths.
         * Do not use page-break-inside:avoid on this block (blank first page).
         */
        table.hero {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0 0 8px;
        }
        table.hero col.map-col { width: 58%; }
        table.hero col.info-col { width: 42%; }
        table.hero td {
            border: 1px solid #cbd5e1;
            vertical-align: top;
            padding: 5px 6px;
        }
        td.hero-map {
            background: #f8fafc;
            text-align: center;
        }
        td.hero-map img {
            width: 100%;
            max-width: 100%;
            height: auto;
            display: block;
        }
        td.hero-map .placeholder {
            padding: 28px 8px;
            color: #64748b;
        }
        td.hero-info {
            background: #ffffff;
        }
        .addr-block {
            margin: 0 0 8px;
        }
        .addr-label {
            color: #64748b;
            font-size: 8px;
            margin: 0 0 2px;
            font-weight: 700;
        }
        .addr-value {
            margin: 0;
            font-size: 9px;
            line-height: 1.45;
            color: #0f172a;
            word-wrap: break-word;
            overflow-wrap: anywhere;
            white-space: normal;
        }
        .stats-block {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid #e2e8f0;
        }
        .stat-line {
            margin: 0 0 3px;
            font-size: 9px;
            line-height: 1.35;
            word-wrap: break-word;
        }
        .stat-line .k { color: #64748b; }
        .stat-line .v {
            direction: ltr;
            unicode-bidi: embed;
            font-weight: 700;
            color: #0f172a;
        }
        .map-legend {
            margin: 4px 0 0;
            font-size: 7px;
            color: #64748b;
            text-align: left;
            word-wrap: break-word;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 4px;
        }
        table.data th, table.data td {
            border: 1px solid #cbd5e1;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.data th {
            background: #f1f5f9;
            font-weight: 700;
            font-size: 8px;
        }
        .pdf-ar { direction: ltr; unicode-bidi: embed; text-align: right; }
        .pdf-ltr { direction: ltr; unicode-bidi: embed; text-align: left; }
        .num { direction: ltr; unicode-bidi: embed; text-align: right; }
        .note {
            color: #64748b;
            font-size: 8px;
            margin: 6px 0 0;
        }
    </style>
</head>
<body>
@php
    $textClass = ($rtl ?? false) ? 'pdf-ar' : 'pdf-ltr';
    $arClass = 'pdf-ar';
@endphp

@forelse($sections as $sectionIndex => $section)
    @php
        $device = $section['device'] ?? [];
        $name = (string) ($device['device_name'] ?? $device['device_id'] ?? '');
        $plate = (string) ($device['plate'] ?? '');
        $startAddr = trim((string) ($section['start_address'] ?? ''));
        $endAddr = trim((string) ($section['end_address'] ?? ''));
        $titleClass = ($rtl ?? false) || preg_match('/\p{Arabic}/u', $name.$plate) ? $arClass : $textClass;
        $addrClass = ($rtl ?? false) || preg_match('/\p{Arabic}/u', $startAddr.$endAddr) ? $arClass : $textClass;
    @endphp

    @if($sectionIndex === 0)
        <h1 class="{{ $textClass }}">{{ $title }}</h1>
        <p class="meta">{{ $from }} — {{ $to }}</p>
    @else
        <div style="page-break-before: always;"></div>
        <h1 class="{{ $textClass }}">{{ $title }}</h1>
        <p class="meta">{{ $from }} — {{ $to }}</p>
    @endif

    <div class="vehicle-title {{ $titleClass }}">
        {{ $name }}@if($plate !== '') — {{ $plate }}@endif
    </div>

    {{-- Map (left) + full addresses (right) — visible together --}}
    <table class="hero">
        <colgroup>
            <col class="map-col"/>
            <col class="info-col"/>
        </colgroup>
        <tr>
            <td class="hero-map">
                @if(!empty($section['map_ok']) && !empty($section['map_data_uri']))
                    <img src="{{ $section['map_data_uri'] }}" alt="Location map"/>
                    <p class="map-legend">
                        {{ $labels['map_legend'] ?? '' }}
                    </p>
                @else
                    <div class="placeholder {{ $textClass }}">{{ $mapUnavailable }}</div>
                    @if(!empty($section['start_lat']) && !empty($section['start_lng']))
                        <p class="map-legend" style="margin-top:8px;">
                            S: {{ number_format((float) $section['start_lat'], 5) }}, {{ number_format((float) $section['start_lng'], 5) }}
                            @if(!empty($section['end_lat']) && !empty($section['end_lng']))
                                <br/>E: {{ number_format((float) $section['end_lat'], 5) }}, {{ number_format((float) $section['end_lng'], 5) }}
                            @endif
                        </p>
                    @endif
                @endif
            </td>
            <td class="hero-info">
                <div class="addr-block">
                    <p class="addr-label {{ $textClass }}">{{ $labels['start_address'] ?? '' }}</p>
                    <p class="addr-value {{ $addrClass }}">{{ $startAddr !== '' ? $startAddr : '—' }}</p>
                </div>

                <div class="addr-block">
                    <p class="addr-label {{ $textClass }}">{{ $labels['end_address'] ?? '' }}</p>
                    <p class="addr-value {{ $addrClass }}">{{ $endAddr !== '' ? $endAddr : '—' }}</p>
                </div>

                <div class="stats-block">
                    <p class="stat-line {{ $textClass }}">
                        <span class="k">{{ $labels['distance'] ?? '' }}:</span>
                        <span class="v"> {{ number_format((float) ($section['distance_km'] ?? 0), 2) }}</span>
                    </p>
                    <p class="stat-line {{ $textClass }}">
                        <span class="k">{{ $labels['moving_time'] ?? '' }}:</span>
                        <span class="v"> {{ $section['moving_time'] ?? '—' }}</span>
                    </p>
                    <p class="stat-line {{ $textClass }}">
                        <span class="k">{{ $labels['stopped_time'] ?? '' }}:</span>
                        <span class="v"> {{ $section['stopped_time'] ?? '—' }}</span>
                    </p>
                    <p class="stat-line {{ $textClass }}">
                        <span class="k">{{ $labels['trips'] ?? '' }}:</span>
                        <span class="v"> {{ (int) ($section['trip_count'] ?? 0) }}</span>
                    </p>
                    <p class="stat-line {{ $textClass }}">
                        <span class="k">{{ $labels['stops'] ?? '' }}:</span>
                        <span class="v"> {{ (int) ($section['stop_count'] ?? 0) }}</span>
                    </p>
                    <p class="stat-line {{ $textClass }}">
                        <span class="k">{{ $labels['points'] ?? '' }}:</span>
                        <span class="v"> {{ (int) ($section['point_count'] ?? 0) }}</span>
                    </p>
                </div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th class="{{ $textClass }}" style="width:11%">{{ $labels['segment'] ?? '' }}</th>
                <th class="{{ $textClass }}" style="width:15%">{{ $labels['start'] ?? '' }}</th>
                <th class="{{ $textClass }}" style="width:15%">{{ $labels['end'] ?? '' }}</th>
                <th class="{{ $textClass }}" style="width:11%">{{ $labels['duration'] ?? '' }}</th>
                <th class="{{ $textClass }}" style="width:10%">{{ $labels['distance'] ?? '' }}</th>
                <th class="{{ $textClass }}" style="width:38%">{{ $labels['address'] ?? '' }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($section['rows'] ?? []) as $row)
                <tr>
                    <td class="{{ $textClass }}">{{ $row['kind'] }}</td>
                    <td class="num">{{ $row['start'] }}</td>
                    <td class="num">{{ $row['end'] }}</td>
                    <td class="num">{{ $row['duration'] }}</td>
                    <td class="num">{{ $row['distance'] }}</td>
                    <td class="{{ $textClass }}">{{ $row['location'] !== '' ? $row['location'] : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="{{ $textClass }}">{{ $noData }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if(!empty($section['rows_truncated']))
        <p class="note {{ $textClass }}">{{ $labels['table_truncated'] ?? '' }}</p>
    @endif
@empty
    <h1 class="{{ $textClass }}">{{ $title }}</h1>
    <p class="meta">{{ $from }} — {{ $to }}</p>
    <p class="{{ $textClass }}">{{ $noData }}</p>
@endforelse
</body>
</html>
