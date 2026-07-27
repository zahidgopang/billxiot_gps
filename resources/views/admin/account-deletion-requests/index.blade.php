@extends('admin.layouts.app')

@section('title', __('app.admin.account_deletion_requests.title'))
@section('page-title', __('app.admin.account_deletion_requests.page_title'))

@section('content')
    <div class="card p-3">
        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">{{ __('app.admin.account_deletion_requests.heading') }}</h5>
                <p class="text-muted small mb-0">{{ __('app.admin.account_deletion_requests.subtitle') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-danger">{{ __('app.admin.account_deletion_requests.pending_count', ['count' => $counts['pending']]) }}</span>
                <span class="badge bg-success">{{ __('app.admin.account_deletion_requests.approved_count', ['count' => $counts['approved']]) }}</span>
                <span class="badge bg-secondary">{{ __('app.admin.account_deletion_requests.rejected_count', ['count' => $counts['rejected']]) }}</span>
            </div>
        </div>

        <form method="GET" class="admin-filter-bar row g-2 mb-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small mb-1" for="adr-q">{{ __('app.common.search') }}</label>
                <input type="text" name="q" id="adr-q" value="{{ $search }}" class="form-control form-control-sm"
                       placeholder="{{ __('app.admin.account_deletion_requests.search_placeholder') }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1" for="adr-status">{{ __('app.common.status') }}</label>
                <select name="status" id="adr-status" class="form-select form-select-sm">
                    <option value="">{{ __('app.admin.account_deletion_requests.all_statuses') }}</option>
                    @foreach(['pending', 'approved', 'rejected'] as $st)
                        <option value="{{ $st }}" @selected($status === $st)>
                            {{ __('app.admin.account_deletion_requests.status_' . $st) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('app.common.filter') }}</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('app.admin.account_deletion_requests.col_date') }}</th>
                        <th>{{ __('app.admin.account_deletion_requests.col_source') }}</th>
                        <th>{{ __('app.common.name') }}</th>
                        <th>{{ __('app.forms.email') }}</th>
                        <th>{{ __('app.forms.phone') }}</th>
                        <th>{{ __('app.common.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr class="{{ $item->status === 'pending' ? 'table-warning' : '' }}">
                            <td class="text-nowrap small">{{ $item->created_at?->format('M d, Y H:i') }}</td>
                            <td>
                                <span class="badge bg-{{ $item->source === 'public' ? 'info' : 'primary' }}">
                                    {{ __('app.admin.account_deletion_requests.source_' . $item->source) }}
                                </span>
                            </td>
                            <td>{{ $item->name ?: '—' }}</td>
                            <td><a href="mailto:{{ $item->email }}">{{ $item->email }}</a></td>
                            <td>{{ $item->phone ?: '—' }}</td>
                            <td>
                                @php
                                    $badge = match($item->status) {
                                        'pending' => 'danger',
                                        'approved' => 'success',
                                        'rejected' => 'secondary',
                                        default => 'light',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badge }}">
                                    {{ __('app.admin.account_deletion_requests.status_' . $item->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.account-deletion-requests.show', $item) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    {{ __('app.common.view') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                {{ __('app.admin.account_deletion_requests.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $items->links() }}</div>
    </div>
@endsection
