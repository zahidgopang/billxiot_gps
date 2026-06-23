@extends('admin.layouts.app')

@section('title', __('app.admin.contact_messages.title'))
@section('page-title', __('app.admin.contact_messages.page_title'))

@section('content')
    <div class="card p-3">
        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">{{ __('app.admin.contact_messages.heading') }}</h5>
                <p class="text-muted small mb-0">{{ __('app.admin.contact_messages.subtitle') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-danger">{{ __('app.admin.contact_messages.new_count', ['count' => $counts['new']]) }}</span>
                <span class="badge bg-secondary">{{ __('app.admin.contact_messages.total_count', ['count' => array_sum($counts)]) }}</span>
            </div>
        </div>

        <form method="GET" class="admin-filter-bar row g-2 mb-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small mb-1" for="contact-q">{{ __('app.common.search') }}</label>
                <input type="text" name="q" id="contact-q" value="{{ $search }}" class="form-control form-control-sm"
                       placeholder="{{ __('app.admin.contact_messages.search_placeholder') }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1" for="contact-status">{{ __('app.common.status') }}</label>
                <select name="status" id="contact-status" class="form-select form-select-sm">
                    <option value="">{{ __('app.admin.contact_messages.all_statuses') }}</option>
                    @foreach(['new', 'read', 'replied', 'closed'] as $st)
                        <option value="{{ $st }}" @selected($status === $st)>{{ __('app.admin.contact_messages.status_' . $st) }}</option>
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
                        <th>Date</th>
                        <th>{{ __('app.common.name') }}</th>
                        <th>{{ __('app.forms.email') }}</th>
                        <th>{{ __('app.forms.subject') }}</th>
                        <th>{{ __('app.common.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($messages as $msg)
                        <tr class="{{ $msg->status === 'new' ? 'table-warning' : '' }}">
                            <td class="text-nowrap small">{{ $msg->created_at?->format('M d, Y H:i') }}</td>
                            <td>{{ $msg->name }}</td>
                            <td><a href="mailto:{{ $msg->email }}">{{ $msg->email }}</a></td>
                            <td>{{ \Illuminate\Support\Str::limit($msg->subject, 50) }}</td>
                            <td>
                                @php
                                    $badge = match($msg->status) {
                                        'new' => 'danger',
                                        'read' => 'info',
                                        'replied' => 'success',
                                        'closed' => 'secondary',
                                        default => 'light',
                                    };
                                @endphp
                                <span class="badge bg-{{ $badge }}">{{ __('app.admin.contact_messages.status_' . $msg->status) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.contact-messages.show', $msg) }}" class="btn btn-sm btn-outline-primary">
                                    {{ __('app.common.view') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('app.admin.contact_messages.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $messages->links() }}
        </div>
    </div>
@endsection
