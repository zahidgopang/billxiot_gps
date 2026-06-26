<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('app.admin.usage.title') }} — {{ $brandName }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: #1a1a2e;
            margin: 0;
            padding: 0;
        }
        .page { padding: 28px 32px; }
        h1 { font-size: 20px; margin: 0 0 6px; color: #1565c0; }
        h2 { font-size: 14px; margin: 22px 0 10px; color: #1565c0; border-bottom: 2px solid #e3f2fd; padding-bottom: 4px; }
        h3 { font-size: 12px; margin: 0 0 6px; color: #0d47a1; }
        p { margin: 0 0 8px; }
        .muted { color: #616161; font-size: 10px; }
        .hero {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 6px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .flow-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .flow-table td {
            text-align: center;
            vertical-align: middle;
            padding: 6px 4px;
            font-size: 9px;
        }
        .flow-num {
            display: inline-block;
            width: 22px;
            height: 22px;
            line-height: 22px;
            border-radius: 50%;
            background: #1976d2;
            color: #fff;
            font-weight: bold;
            font-size: 10px;
        }
        .flow-arrow { color: #1976d2; font-size: 14px; font-weight: bold; }
        .stock-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .stock-table td {
            border: 1px solid #bdbdbd;
            padding: 10px 8px;
            text-align: center;
            background: #fafafa;
            font-size: 10px;
        }
        .stock-arrow { text-align: center; font-size: 16px; color: #1976d2; font-weight: bold; width: 28px; }
        .step {
            border: 1px solid #e0e0e0;
            border-left: 4px solid #1976d2;
            border-radius: 4px;
            padding: 12px 14px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .step-meta { margin-bottom: 8px; font-size: 10px; color: #424242; }
        .step-meta strong { color: #212121; }
        .two-col { width: 100%; border-collapse: collapse; }
        .two-col td { width: 50%; vertical-align: top; padding: 0 8px 0 0; }
        .two-col td + td { padding: 0 0 0 8px; }
        ul { margin: 4px 0 0; padding-left: 16px; }
        li { margin-bottom: 3px; }
        .checks { list-style: none; padding-left: 0; }
        .checks li::before { content: "✓ "; color: #2e7d32; font-weight: bold; }
        .note {
            margin-top: 8px;
            padding: 8px 10px;
            background: #fff8e1;
            border-left: 3px solid #ffa000;
            font-size: 10px;
        }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 10px; }
        .data-table th, .data-table td { border: 1px solid #e0e0e0; padding: 6px 8px; }
        .data-table th { background: #f5f5f5; text-align: left; }
        .data-table .center { text-align: center; }
        .yes { color: #2e7d32; font-weight: bold; }
        .no { color: #9e9e9e; }
        .ts-box {
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }
        .checklist li { margin-bottom: 4px; }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e0e0e0;
            font-size: 9px;
            color: #757575;
            text-align: center;
        }
        .hierarchy { font-size: 10px; margin-bottom: 12px; padding: 10px; background: #f5f5f5; border-radius: 4px; }
    </style>
</head>
<body>
<div class="page">

    <h1>{{ __('app.admin.usage.hero_title') }}</h1>
    <p class="muted">{{ __('app.admin.usage.pdf_generated', ['date' => $generatedAt->format('Y-m-d H:i'), 'brand' => $brandName]) }}</p>

    <div class="hero">
        <p>{{ __('app.admin.usage.hero_subtitle') }}</p>
    </div>

    <h2>{{ __('app.admin.usage.flow_aria') }}</h2>
    <table class="flow-table">
        <tr>
            @foreach($flowSteps as $i => $step)
                @if($i > 0)
                    <td class="flow-arrow">→</td>
                @endif
                <td>
                    <div class="flow-num">{{ $step['num'] }}</div>
                    <div style="margin-top:4px;font-weight:bold;">{{ $step['title'] }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    <h2>{{ __('app.admin.usage.stock_diagram_title') }}</h2>
    <table class="stock-table">
        <tr>
            <td>
                <strong>{{ __('app.admin.usage.stock_warehouse') }}</strong><br>
                <span class="muted">{{ __('app.admin.usage.stock_warehouse_desc') }}</span>
            </td>
            <td class="stock-arrow">→</td>
            <td>
                <strong>{{ __('app.admin.usage.stock_sale') }}</strong><br>
                <span class="muted">{{ __('app.admin.usage.stock_sale_desc') }}</span>
            </td>
            <td class="stock-arrow">→</td>
            <td>
                <strong>{{ __('app.admin.usage.stock_client') }}</strong><br>
                <span class="muted">{{ __('app.admin.usage.stock_client_desc') }}</span>
            </td>
            <td class="stock-arrow">→</td>
            <td>
                <strong>{{ __('app.admin.usage.stock_install') }}</strong><br>
                <span class="muted">{{ __('app.admin.usage.stock_install_desc') }}</span>
            </td>
        </tr>
    </table>
    <p class="muted" style="text-align:center;">{{ __('app.admin.usage.stock_formula') }}</p>

    <h2>{{ __('app.admin.usage.hierarchy_title') }}</h2>
    <div class="hierarchy">
        {{ __('app.admin.usage.hierarchy_super') }}
        → {{ __('app.admin.usage.hierarchy_admin') }}
        → {{ __('app.admin.usage.hierarchy_client') }}
        → {{ __('app.admin.usage.hierarchy_end') }}
    </div>

    <h2>{{ __('app.admin.usage.page_title') }}</h2>

    @foreach($flowSteps as $step)
        <div class="step" style="border-left-color: {{ $step['accent'] }};">
            <h3>{{ __('app.admin.usage.step_label', ['num' => $step['num']]) }} — {{ $step['title'] }}</h3>
            <div class="step-meta">
                <strong>{{ __('app.admin.usage.who_label') }}:</strong> {{ $step['who'] }}
                &nbsp;|&nbsp;
                <strong>{{ __('app.admin.usage.panel_label') }}:</strong> {{ $step['panel'] }}
                @if($step['route'])
                    &nbsp;|&nbsp;
                    <strong>{{ __('app.admin.usage.open_module') }}:</strong> {{ $step['route_label'] }}
                @endif
            </div>
            <table class="two-col">
                <tr>
                    <td>
                        <strong>{{ __('app.admin.usage.actions_label') }}</strong>
                        <ul>
                            @foreach($step['bullets'] as $bullet)
                                <li>{{ $bullet }}</li>
                            @endforeach
                        </ul>
                    </td>
                    <td>
                        <strong>{{ __('app.admin.usage.checks_label') }}</strong>
                        <ul class="checks">
                            @foreach($step['checks'] as $check)
                                <li>{{ $check }}</li>
                            @endforeach
                        </ul>
                    </td>
                </tr>
            </table>
            @if($step['note'])
                <div class="note">{{ $step['note'] }}</div>
            @endif
        </div>
    @endforeach

    <h2>{{ __('app.admin.usage.roles_title') }}</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('app.admin.usage.role_action') }}</th>
                <th class="center">{{ __('app.admin.usage.role_super') }}</th>
                <th class="center">{{ __('app.admin.usage.role_admin') }}</th>
                <th class="center">{{ __('app.admin.usage.role_client') }}</th>
                <th class="center">{{ __('app.admin.usage.role_end') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($roles as $row)
                <tr>
                    <td>{{ $row['action'] }}</td>
                    @foreach(['super', 'admin', 'client', 'end'] as $key)
                        <td class="center">
                            @if($row[$key])
                                <span class="yes">✓</span>
                            @else
                                <span class="no">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('app.admin.usage.troubleshooting_title') }}</h2>
    @foreach($troubleshooting as $ts)
        <div class="ts-box">
            <p><strong>{{ __('app.admin.usage.ts_symptom') }}:</strong> {{ $ts['symptom'] }}</p>
            <p><strong>{{ __('app.admin.usage.ts_cause') }}:</strong> {{ $ts['cause'] }}</p>
            <p><strong>{{ __('app.admin.usage.ts_fix') }}:</strong> {{ $ts['fix'] }}</p>
        </div>
    @endforeach

    <h2>{{ __('app.admin.usage.checklist_title') }}</h2>
    <ul class="checklist">
        @foreach(__('app.admin.usage.checklist_items') as $item)
            <li>☑ {{ $item }}</li>
        @endforeach
    </ul>

    <div class="footer">
        {{ $brandName }} — {{ __('app.admin.usage.title') }} — {{ $generatedAt->format('Y-m-d') }}
    </div>
</div>
</body>
</html>
