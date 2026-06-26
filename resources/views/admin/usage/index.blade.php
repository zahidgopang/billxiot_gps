@extends('admin.layouts.app')

@section('title', __('app.admin.usage.title'))
@section('page-title', __('app.admin.usage.page_title'))

@push('styles')
<style>
    .usage-page { --usage-radius: 14px; }

    .usage-hero {
        background: linear-gradient(135deg, #1a237e 0%, #1976d2 45%, #42a5f5 100%);
        border-radius: var(--usage-radius);
        color: #fff;
        padding: 2rem 2.25rem;
        margin-bottom: 1.75rem;
        position: relative;
        overflow: hidden;
    }
    .usage-hero::after {
        content: '';
        position: absolute;
        inset-inline-end: -40px;
        top: -40px;
        width: 200px;
        height: 200px;
        border-radius: 50%;
        background: rgba(255,255,255,.08);
        pointer-events: none;
    }
    .usage-hero h1 { font-size: 1.65rem; font-weight: 800; margin-bottom: .5rem; }
    .usage-hero p { opacity: .92; max-width: 52rem; margin: 0; line-height: 1.55; }

    /* Horizontal flow strip */
    .usage-flow-strip {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: .35rem .15rem;
        padding: 1.25rem 1rem;
        background: var(--admin-card, #fff);
        border: 1px solid var(--admin-border, rgba(0,0,0,.08));
        border-radius: var(--usage-radius);
        margin-bottom: 2rem;
    }
    .usage-flow-node {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        min-width: 72px;
        max-width: 88px;
        text-decoration: none;
        color: inherit;
        transition: transform .15s ease;
    }
    a.usage-flow-node:hover { transform: translateY(-2px); color: inherit; }
    .usage-flow-node__icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        color: #fff;
        margin-bottom: .35rem;
        box-shadow: 0 4px 12px rgba(0,0,0,.15);
    }
    .usage-flow-node__num {
        font-size: .65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: var(--admin-text-muted, #6c757d);
    }
    .usage-flow-node__label {
        font-size: .68rem;
        font-weight: 600;
        line-height: 1.2;
        color: var(--admin-text, #212529);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .usage-flow-arrow {
        color: var(--admin-primary, #1976d2);
        font-size: .85rem;
        opacity: .7;
        padding: 0 .1rem;
        flex-shrink: 0;
    }
    html[dir="rtl"] .usage-flow-arrow .fa-arrow-right { transform: scaleX(-1); }

    /* Stock diagram */
    .usage-stock-diagram {
        display: grid;
        grid-template-columns: 1fr auto 1fr auto 1fr auto 1fr;
        align-items: center;
        gap: .5rem;
        padding: 1.5rem;
        background: linear-gradient(180deg, rgba(25,118,210,.04) 0%, transparent 100%);
        border: 1px dashed rgba(25,118,210,.25);
        border-radius: var(--usage-radius);
        margin-bottom: 2rem;
    }
    @media (max-width: 991px) {
        .usage-stock-diagram {
            grid-template-columns: 1fr;
            text-align: center;
        }
        .usage-stock-diagram .usage-flow-arrow { transform: rotate(90deg); justify-self: center; }
        html[dir="rtl"] .usage-stock-diagram .usage-flow-arrow .fa-arrow-right { transform: rotate(90deg) scaleX(-1); }
    }
    .usage-stock-box {
        background: var(--admin-card, #fff);
        border: 1px solid var(--admin-border, rgba(0,0,0,.1));
        border-radius: 12px;
        padding: 1rem .85rem;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,.04);
    }
    .usage-stock-box i { font-size: 1.5rem; margin-bottom: .5rem; display: block; }
    .usage-stock-box strong { display: block; font-size: .85rem; }
    .usage-stock-box span { font-size: .75rem; color: var(--admin-text-muted); }

    /* Hierarchy */
    .usage-hierarchy {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: center;
        gap: .5rem 1rem;
        margin-bottom: 2rem;
        padding: 1.25rem;
        background: var(--admin-card);
        border-radius: var(--usage-radius);
        border: 1px solid var(--admin-border, rgba(0,0,0,.08));
    }
    .usage-hierarchy-node {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .55rem 1rem;
        border-radius: 999px;
        font-size: .82rem;
        font-weight: 600;
        border: 1px solid transparent;
    }
    .usage-hierarchy-node--super { background: #fce4ec; color: #880e4f; border-color: #f48fb1; }
    .usage-hierarchy-node--admin { background: #e3f2fd; color: #0d47a1; border-color: #90caf9; }
    .usage-hierarchy-node--client { background: #ede7f6; color: #4a148c; border-color: #b39ddb; }
    .usage-hierarchy-node--end { background: #e8f5e9; color: #1b5e20; border-color: #a5d6a7; }
    .usage-hierarchy-arrow { color: var(--admin-text-muted); font-size: .75rem; }

    /* Step cards */
    .usage-step {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 0 1.25rem;
        margin-bottom: 1.5rem;
        position: relative;
    }
    .usage-step:not(:last-child)::before {
        content: '';
        position: absolute;
        inset-inline-start: 23px;
        top: 52px;
        bottom: -1.5rem;
        width: 2px;
        background: linear-gradient(180deg, var(--step-accent, #1976d2) 0%, rgba(0,0,0,.08) 100%);
        border-radius: 1px;
    }
    .usage-step__badge {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        color: #fff;
        background: var(--step-accent, #1976d2);
        box-shadow: 0 4px 14px color-mix(in srgb, var(--step-accent) 40%, transparent);
        position: relative;
        z-index: 1;
        flex-shrink: 0;
    }
    .usage-step__card {
        background: var(--admin-card, #fff);
        border: 1px solid var(--admin-border, rgba(0,0,0,.08));
        border-radius: var(--usage-radius);
        padding: 1.25rem 1.35rem;
        border-inline-start: 4px solid var(--step-accent, #1976d2);
    }
    .usage-step__head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .85rem;
    }
    .usage-step__title { font-size: 1.1rem; font-weight: 800; margin: 0; }
    .usage-step__meta {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-bottom: .85rem;
    }
    .usage-meta-pill {
        font-size: .75rem;
        padding: .25rem .65rem;
        border-radius: 6px;
        background: rgba(0,0,0,.04);
        color: var(--admin-text-muted);
    }
    .usage-meta-pill i { margin-inline-end: .3rem; opacity: .75; }
    .usage-step__grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    @media (max-width: 767px) {
        .usage-step__grid { grid-template-columns: 1fr; }
    }
    .usage-step__section h6 {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--admin-text-muted);
        margin-bottom: .5rem;
        font-weight: 700;
    }
    .usage-step__section ul {
        margin: 0;
        padding-inline-start: 1.15rem;
        font-size: .88rem;
        line-height: 1.55;
    }
    .usage-step__section ul li { margin-bottom: .35rem; }
    .usage-checks {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .usage-checks li {
        font-size: .84rem;
        padding: .35rem 0 .35rem 1.5rem;
        position: relative;
        line-height: 1.45;
    }
    .usage-checks li::before {
        content: '\f058';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        inset-inline-start: 0;
        color: #2e7d32;
        font-size: .9rem;
    }
    .usage-step__note {
        margin-top: .85rem;
        padding: .65rem .85rem;
        background: rgba(25, 118, 210, .07);
        border-radius: 8px;
        font-size: .84rem;
        border-inline-start: 3px solid var(--admin-primary);
    }
    .usage-optional-tag {
        font-size: .65rem;
        vertical-align: middle;
        margin-inline-start: .35rem;
        padding: .15rem .45rem;
        border-radius: 4px;
        background: #fff3e0;
        color: #e65100;
        font-weight: 700;
        text-transform: uppercase;
    }

    .usage-table-wrap { overflow-x: auto; margin-bottom: 2rem; }
    .usage-table { font-size: .88rem; }
    .usage-table th { white-space: nowrap; }
    .usage-yes { color: #2e7d32; }
    .usage-no { color: #bdbdbd; }

    .usage-checklist {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: .65rem;
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .usage-checklist li {
        display: flex;
        align-items: flex-start;
        gap: .6rem;
        padding: .75rem 1rem;
        background: var(--admin-card);
        border: 1px solid var(--admin-border, rgba(0,0,0,.08));
        border-radius: 10px;
        font-size: .88rem;
    }
    .usage-checklist li i { color: var(--admin-primary); margin-top: .15rem; }
</style>
@endpush

@section('content')
<div class="usage-page">

    <div class="d-flex justify-content-end mb-3">
        <a href="{{ route('admin.usage.pdf') }}" class="btn btn-danger">
            <i class="fas fa-file-pdf me-1"></i>{{ __('app.admin.usage.download_pdf') }}
        </a>
    </div>

    {{-- Hero --}}
    <div class="usage-hero">
        <h1><i class="fas fa-route me-2 opacity-75"></i>{{ __('app.admin.usage.hero_title') }}</h1>
        <p>{{ __('app.admin.usage.hero_subtitle') }}</p>
    </div>

    {{-- Quick flow strip --}}
    <nav class="usage-flow-strip" aria-label="{{ __('app.admin.usage.flow_aria') }}">
        @foreach($flowSteps as $i => $step)
            @if($i > 0)
                <span class="usage-flow-arrow" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
            @endif
            @if($step['route'])
                <a href="{{ $step['route'] }}" class="usage-flow-node" title="{{ $step['title'] }}">
            @else
                <div class="usage-flow-node">
            @endif
                <div class="usage-flow-node__icon" style="background: {{ $step['accent'] }}">
                    <i class="fas {{ $step['icon'] }}"></i>
                </div>
                <span class="usage-flow-node__num">{{ __('app.admin.usage.step_label', ['num' => $step['num']]) }}</span>
                <span class="usage-flow-node__label">{{ $step['title'] }}</span>
            @if($step['route'])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </nav>

    {{-- Stock diagram --}}
    <h5 class="fw-bold mb-3"><i class="fas fa-boxes-stacked me-2 text-primary"></i>{{ __('app.admin.usage.stock_diagram_title') }}</h5>
    <div class="usage-stock-diagram">
        <div class="usage-stock-box">
            <i class="fas fa-warehouse text-primary"></i>
            <strong>{{ __('app.admin.usage.stock_warehouse') }}</strong>
            <span>{{ __('app.admin.usage.stock_warehouse_desc') }}</span>
        </div>
        <span class="usage-flow-arrow"><i class="fas fa-arrow-right fa-lg"></i></span>
        <div class="usage-stock-box">
            <i class="fas fa-file-invoice-dollar text-success"></i>
            <strong>{{ __('app.admin.usage.stock_sale') }}</strong>
            <span>{{ __('app.admin.usage.stock_sale_desc') }}</span>
        </div>
        <span class="usage-flow-arrow"><i class="fas fa-arrow-right fa-lg"></i></span>
        <div class="usage-stock-box">
            <i class="fas fa-building text-info"></i>
            <strong>{{ __('app.admin.usage.stock_client') }}</strong>
            <span>{{ __('app.admin.usage.stock_client_desc') }}</span>
        </div>
        <span class="usage-flow-arrow"><i class="fas fa-arrow-right fa-lg"></i></span>
        <div class="usage-stock-box">
            <i class="fas fa-satellite-dish text-warning"></i>
            <strong>{{ __('app.admin.usage.stock_install') }}</strong>
            <span>{{ __('app.admin.usage.stock_install_desc') }}</span>
        </div>
    </div>
    <p class="text-muted small text-center mb-4">
        <i class="fas fa-calculator me-1"></i>{{ __('app.admin.usage.stock_formula') }}
    </p>

    {{-- Role hierarchy --}}
    <h5 class="fw-bold mb-3"><i class="fas fa-sitemap me-2 text-primary"></i>{{ __('app.admin.usage.hierarchy_title') }}</h5>
    <div class="usage-hierarchy mb-4">
        <span class="usage-hierarchy-node usage-hierarchy-node--super"><i class="fas fa-crown"></i> {{ __('app.admin.usage.hierarchy_super') }}</span>
        <span class="usage-hierarchy-arrow"><i class="fas fa-arrow-right"></i> {{ __('app.admin.usage.hierarchy_manages') }}</span>
        <span class="usage-hierarchy-node usage-hierarchy-node--admin"><i class="fas fa-user-shield"></i> {{ __('app.admin.usage.hierarchy_admin') }}</span>
        <span class="usage-hierarchy-arrow"><i class="fas fa-arrow-right"></i> {{ __('app.admin.usage.hierarchy_manages') }}</span>
        <span class="usage-hierarchy-node usage-hierarchy-node--client"><i class="fas fa-user-tie"></i> {{ __('app.admin.usage.hierarchy_client') }}</span>
        <span class="usage-hierarchy-arrow"><i class="fas fa-arrow-right"></i> {{ __('app.admin.usage.hierarchy_manages') }}</span>
        <span class="usage-hierarchy-node usage-hierarchy-node--end"><i class="fas fa-user"></i> {{ __('app.admin.usage.hierarchy_end') }}</span>
    </div>

    {{-- Detailed steps --}}
    <h5 class="fw-bold mb-3"><i class="fas fa-list-ol me-2 text-primary"></i>{{ __('app.admin.usage.page_title') }}</h5>

    @foreach($flowSteps as $step)
        <article class="usage-step" id="usage-step-{{ $step['num'] }}" style="--step-accent: {{ $step['accent'] }}">
            <div class="usage-step__badge" aria-hidden="true">
                <i class="fas {{ $step['icon'] }}"></i>
            </div>
            <div class="usage-step__card">
                <div class="usage-step__head">
                    <h3 class="usage-step__title">
                        {{ __('app.admin.usage.step_label', ['num' => $step['num']]) }} — {{ $step['title'] }}
                    </h3>
                    @if($step['route'])
                        <a href="{{ $step['route'] }}" class="btn btn-sm btn-primary">
                            <i class="fas fa-external-link-alt me-1"></i>{{ $step['route_label'] }}
                        </a>
                    @endif
                </div>

                <div class="usage-step__meta">
                    <span class="usage-meta-pill"><i class="fas fa-user-check"></i>{{ __('app.admin.usage.who_label') }}: <strong>{{ $step['who'] }}</strong></span>
                    <span class="usage-meta-pill"><i class="fas fa-window-maximize"></i>{{ __('app.admin.usage.panel_label') }}: <strong>{{ $step['panel'] }}</strong></span>
                </div>

                <div class="usage-step__grid">
                    <div class="usage-step__section">
                        <h6>{{ __('app.admin.usage.actions_label') }}</h6>
                        <ul>
                            @foreach($step['bullets'] as $bullet)
                                <li>{{ $bullet }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="usage-step__section">
                        <h6>{{ __('app.admin.usage.checks_label') }}</h6>
                        <ul class="usage-checks">
                            @foreach($step['checks'] as $check)
                                <li>{{ $check }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                @if($step['note'])
                    <div class="usage-step__note">
                        <i class="fas fa-lightbulb me-1 text-warning"></i>{{ $step['note'] }}
                    </div>
                @endif
            </div>
        </article>
    @endforeach

    {{-- Role matrix --}}
    <h5 class="fw-bold mb-3 mt-2"><i class="fas fa-table me-2 text-primary"></i>{{ __('app.admin.usage.roles_title') }}</h5>
    <div class="usage-table-wrap card p-0 mb-4">
        <table class="table table-hover usage-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('app.admin.usage.role_action') }}</th>
                    <th class="text-center">{{ __('app.admin.usage.role_super') }}</th>
                    <th class="text-center">{{ __('app.admin.usage.role_admin') }}</th>
                    <th class="text-center">{{ __('app.admin.usage.role_client') }}</th>
                    <th class="text-center">{{ __('app.admin.usage.role_end') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roles as $row)
                    <tr>
                        <td>{{ $row['action'] }}</td>
                        @foreach(['super', 'admin', 'client', 'end'] as $key)
                            <td class="text-center">
                                @if($row[$key])
                                    <i class="fas fa-check-circle usage-yes"></i>
                                @else
                                    <i class="fas fa-minus-circle usage-no"></i>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Troubleshooting --}}
    <h5 class="fw-bold mb-3"><i class="fas fa-wrench me-2 text-primary"></i>{{ __('app.admin.usage.troubleshooting_title') }}</h5>
    <div class="row g-3 mb-4">
        @foreach($troubleshooting as $ts)
            <div class="col-md-6">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <i class="fas fa-circle-exclamation text-danger mt-1"></i>
                            <strong class="small">{{ __('app.admin.usage.ts_symptom') }}:</strong>
                            <span class="small">{{ $ts['symptom'] }}</span>
                        </div>
                        <div class="small text-muted mb-1"><strong>{{ __('app.admin.usage.ts_cause') }}:</strong> {{ $ts['cause'] }}</div>
                        <div class="small"><strong class="text-success">{{ __('app.admin.usage.ts_fix') }}:</strong> {{ $ts['fix'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Checklist --}}
    <h5 class="fw-bold mb-3"><i class="fas fa-clipboard-check me-2 text-primary"></i>{{ __('app.admin.usage.checklist_title') }}</h5>
    <ul class="usage-checklist mb-2">
        @foreach(__('app.admin.usage.checklist_items') as $item)
            <li><i class="fas fa-square-check"></i><span>{{ $item }}</span></li>
        @endforeach
    </ul>

</div>
@endsection
