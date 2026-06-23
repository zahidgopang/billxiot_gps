@extends('admin.layouts.app')
@php
    $activityLog = app(\App\Services\ActivityLogService::class);
@endphp
@section('title', __('app.admin.activity_log.title'))
@section('page-title', __('app.admin.activity_log.page_title'))

@section('content')
    <div class="card p-3">
        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">
                    @if($clientScoped ?? false)
                        {{ __('app.admin.activity_log.heading_client') }}
                    @else
                        {{ __('app.admin.activity_log.heading') }}
                    @endif
                </h5>
                <p class="text-muted small mb-0">
                    @if(($clientScoped ?? false) && ($clientCompany ?? null))
                        {{ __('app.admin.activity_log.subtitle_client', ['company' => $clientCompany->name]) }}
                    @else
                        {{ __('app.admin.activity_log.subtitle') }}
                    @endif
                </p>
            </div>
        </div>

        <form method="GET" class="admin-filter-bar row g-2 mb-3 align-items-end">
            <div class="col-12 col-sm-6 col-lg-3">
                <label class="form-label small mb-1" for="activity-filter-q">{{ __('app.forms.search_activity') }}</label>
                <input type="text" name="q" id="activity-filter-q" value="{{ request('q') }}" class="form-control form-control-sm"
                       placeholder="{{ __('app.forms.search_activity') }}">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small mb-1" for="activity-filter-event">{{ __('app.forms.event') }}</label>
                <select name="event" id="activity-filter-event" class="form-select form-select-sm" data-search="false"
                        data-placeholder="{{ __('app.forms.all_events') }}">
                    <option value="">{{ __('app.forms.all_events') }}</option>
                    @foreach(['created', 'updated', 'deleted', 'renewed'] as $ev)
                        @php $key = 'event_' . $ev; @endphp
                        <option value="{{ $ev }}" @selected(request('event') === $ev)>
                            {{ __('app.admin.activity_log.' . $key) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small mb-1" for="activity-filter-category">{{ __('app.admin.activity_log.category') }}</label>
                <select name="category" id="activity-filter-category" class="form-select form-select-sm" data-search="false"
                        data-placeholder="{{ __('app.admin.activity_log.all_categories') }}">
                    <option value="">{{ __('app.admin.activity_log.all_categories') }}</option>
                    @foreach(\App\Services\ActivityLogService::filterCategoryKeys() as $cat)
                        <option value="{{ $cat }}" @selected(request('category') === $cat)>
                            {{ $activityLog->categoryDisplayLabel($cat) }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if(($showClientColumn ?? false) && ($filterClients ?? collect())->count())
                <div class="col-6 col-lg-2">
                    <label class="form-label small mb-1" for="activity-filter-client">{{ __('app.forms.client_company') }}</label>
                    <select name="client_id" id="activity-filter-client" class="form-select form-select-sm"
                            data-placeholder="{{ __('app.admin.activity_log.all_clients') }}">
                        <option value="">{{ __('app.admin.activity_log.all_clients') }}</option>
                        @foreach($filterClients as $client)
                            <option value="{{ $client->id }}" @selected((string) request('client_id') === (string) $client->id)>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-6 col-lg-2">
                <label class="form-label small mb-1" for="activity-filter-causer">{{ __('app.forms.who') }}</label>
                <select name="causer_id" id="activity-filter-causer" class="form-select form-select-sm"
                        data-placeholder="{{ $clientScoped ? __('app.forms.all_users') : __('app.forms.all_admins') }}">
                    <option value="">{{ $clientScoped ? __('app.forms.all_users') : __('app.forms.all_admins') }}</option>
                    @foreach($causers as $c)
                        <option value="{{ $c->id }}" @selected((string) request('causer_id') === (string) $c->id)>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small mb-1" for="activity-log-from">{{ __('app.forms.date_from') }}</label>
                <x-admin.date-input name="from" id="activity-log-from" :value="request('from')" />
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label small mb-1" for="activity-log-to">{{ __('app.forms.date_to') }}</label>
                <x-admin.date-input name="to" id="activity-log-to" :value="request('to')" />
            </div>
            <div class="col-12 col-lg-auto d-flex gap-1 admin-filter-actions">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1 flex-lg-grow-0">{{ __('app.common.filter') }}</button>
                @if(request()->hasAny(['q', 'event', 'category', 'client_id', 'causer_id', 'from', 'to']))
                    <a href="{{ route(request()->routeIs('client.*') ? 'client.activity-log.index' : 'admin.activity-log.index') }}"
                       class="btn btn-outline-secondary btn-sm" title="{{ __('app.common.clear') }}">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle">
                <thead>
                <tr>
                    <th>{{ __('app.forms.when') }}</th>
                    @if($showClientColumn ?? false)
                        <th>{{ __('app.forms.client_company') }}</th>
                    @endif
                    <th>{{ __('app.forms.who') }}</th>
                    <th>{{ __('app.forms.event') }}</th>
                    <th>{{ __('app.admin.activity_log.category') }}</th>
                    <th>{{ __('app.forms.description') }}</th>
                    <th>{{ __('app.forms.subject') }}</th>
                    <th>{{ __('app.forms.details') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    @php
                        $details = $activityLog->normalizedProperties($log);
                        $subjectLabel = $activityLog->subjectLabel($log);
                        $clientLabel = $activityLog->clientLabel($log);
                        $category = $activityLog->categoryLabel($log);
                        $eventBadge = match ($log->event) {
                            'created' => 'bg-success',
                            'updated' => 'bg-primary',
                            'deleted' => 'bg-danger',
                            'renewed' => 'bg-info text-dark',
                            default => 'bg-secondary',
                        };
                    @endphp
                    <tr>
                        <td class="text-nowrap">
                            <x-admin.ltr class="admin-ltr--block">
                                <span class="d-block">{{ app_datetime_format($log->created_at, 'date') }}</span>
                                <small class="text-muted">{{ app_datetime_format($log->created_at, 'time') }}</small>
                            </x-admin.ltr>
                        </td>
                        @if($showClientColumn ?? false)
                            <td>
                                @if($clientLabel)
                                    <span class="fw-semibold">{{ $clientLabel }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        @endif
                        <td>
                            @if($log->causer)
                                <span class="fw-semibold">{{ $log->causer->name }}</span>
                                <small class="d-block text-muted"><x-admin.ltr>{{ $log->causer->email }}</x-admin.ltr></small>
                            @else
                                <span class="text-muted">{{ __('app.forms.system') }}</span>
                            @endif
                        </td>
                        <td>
                            @if($log->event)
                                <span class="badge {{ $eventBadge }}">{{ __('app.admin.activity_log.event_' . $log->event) }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                {{ $activityLog->categoryDisplayLabel($category) }}
                            </span>
                        </td>
                        <td>{{ $log->description }}</td>
                        <td>
                            @if($log->subject_type)
                                <span class="badge bg-light text-dark border">
                                    {{ class_basename($log->subject_type) }}
                                </span>
                                <small class="d-block mt-1">{{ $subjectLabel }}</small>
                            @else
                                <small>{{ $subjectLabel }}</small>
                            @endif
                        </td>
                        <td>
                            @if(!empty($details))
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        data-bs-toggle="collapse" data-bs-target="#log-props-{{ $log->id }}">
                                    {{ __('app.forms.view') }}
                                </button>
                                <div class="collapse mt-2" id="log-props-{{ $log->id }}">
                                    <dl class="small mb-0 bg-light p-2 rounded">
                                        @foreach($details as $key => $value)
                                            <dt class="text-muted mb-0">{{ str_replace('_', ' ', ucfirst($key)) }}</dt>
                                            <dd class="mb-1 admin-ltr" dir="ltr">
                                                @if(is_array($value))
                                                    {{ json_encode($value, JSON_UNESCAPED_UNICODE) }}
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </dd>
                                        @endforeach
                                    </dl>
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($showClientColumn ?? false) ? 8 : 7 }}" class="text-center text-muted py-4">
                            {{ __('app.admin.activity_log.no_activity') }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="mt-3 admin-pagination d-flex flex-column flex-sm-row align-items-sm-center justify-content-sm-between gap-2">
                <p class="small text-muted mb-0">
                    {{ __('app.pagination.showing', [
                        'from' => $logs->firstItem() ?? 0,
                        'to' => $logs->lastItem() ?? 0,
                        'total' => $logs->total(),
                    ]) }}
                </p>
                {{ $logs->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endsection
