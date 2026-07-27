@extends('admin.layouts.app')

@section('title', __('app.admin.account_deletion_requests.view_title'))
@section('page-title', __('app.admin.account_deletion_requests.view_title'))

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.account-deletion-requests.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>{{ __('app.admin.account_deletion_requests.back') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h4 class="mb-1">{{ __('app.admin.account_deletion_requests.request_heading') }}</h4>
                        <p class="text-muted small mb-0">
                            {{ $item->ticketNumber() }}
                            · {{ $item->created_at?->format('M d, Y \a\t h:i A') }}
                        </p>
                    </div>
                    @php
                        $badge = match($item->status) {
                            'pending' => 'danger',
                            'approved' => 'success',
                            'rejected' => 'secondary',
                            default => 'light',
                        };
                    @endphp
                    <span class="badge bg-{{ $badge }} fs-6">
                        {{ __('app.admin.account_deletion_requests.status_' . $item->status) }}
                    </span>
                </div>

                <div class="row g-3 small mb-4">
                    <div class="col-md-6">
                        <strong>{{ __('app.admin.account_deletion_requests.col_source') }}:</strong>
                        {{ __('app.admin.account_deletion_requests.source_' . $item->source) }}
                    </div>
                    <div class="col-md-6">
                        <strong>{{ __('app.common.name') }}:</strong> {{ $item->name ?: '—' }}
                    </div>
                    <div class="col-md-6">
                        <strong>{{ __('app.public_account_deletion.username') }}:</strong> {{ $item->username ?: '—' }}
                    </div>
                    <div class="col-md-6">
                        <strong>{{ __('app.forms.email') }}:</strong>
                        <a href="mailto:{{ $item->email }}">{{ $item->email }}</a>
                    </div>
                    <div class="col-md-6">
                        <strong>{{ __('app.forms.phone') }}:</strong> {{ $item->phone ?: '—' }}
                    </div>
                    <div class="col-md-6">
                        <strong>{{ __('app.admin.account_deletion_requests.matched_user') }}:</strong>
                        @if($item->user)
                            #{{ $item->user->id }} — {{ $item->user->name }}
                        @elseif($item->user_id)
                            #{{ $item->user_id }} ({{ __('app.admin.account_deletion_requests.user_missing') }})
                        @else
                            {{ __('app.admin.account_deletion_requests.user_unmatched') }}
                        @endif
                    </div>
                    @if($item->ip_address)
                        <div class="col-md-6">
                            <strong>IP:</strong> {{ $item->ip_address }}
                        </div>
                    @endif
                </div>

                @if($item->reason)
                    <div class="mb-4">
                        <h6 class="text-muted text-uppercase small">{{ __('app.public_account_deletion.reason') }}</h6>
                        <div class="p-3 rounded bg-light border" style="white-space: pre-wrap;">{{ $item->reason }}</div>
                    </div>
                @endif

                @if(is_array($item->summary) && $item->summary !== [])
                    <div class="mb-2">
                        <h6 class="text-muted text-uppercase small">{{ __('app.user.account_delete.will_remove') }}</h6>
                        <ul class="mb-0">
                            <li>{{ __('app.user.account_delete.summary_vehicles', ['count' => $item->summary['exclusive_vehicles'] ?? 0]) }}</li>
                            <li>{{ __('app.user.account_delete.summary_history') }}</li>
                            <li>{{ __('app.user.account_delete.summary_sub_accounts', ['count' => $item->summary['sub_accounts'] ?? 0]) }}</li>
                            <li>{{ __('app.user.account_delete.summary_subscriptions', ['count' => $item->summary['subscriptions'] ?? 0]) }}</li>
                        </ul>
                    </div>
                @endif

                @if($item->reviewed_at)
                    <hr>
                    <div class="small text-muted">
                        {{ __('app.admin.account_deletion_requests.reviewed_by') }}:
                        {{ $item->reviewed_by_name ?: '—' }}
                        · {{ $item->reviewed_at->format('M d, Y H:i') }}
                        @if($item->review_note)
                            <div class="mt-2 p-2 border rounded bg-light">{{ $item->review_note }}</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card p-4">
                <h6 class="mb-3">{{ __('app.admin.account_deletion_requests.actions') }}</h6>

                @if($item->isPending())
                    <form method="POST" action="{{ route('admin.account-deletion-requests.approve', $item) }}" class="mb-3"
                          onsubmit="return confirm(@json(__('app.admin.account_deletion_requests.approve_confirm')));">
                        @csrf
                        <label class="form-label small" for="approveNote">{{ __('app.admin.account_deletion_requests.review_note') }}</label>
                        <textarea name="review_note" id="approveNote" rows="3" class="form-control mb-2"
                                  placeholder="{{ __('app.admin.account_deletion_requests.review_note_hint') }}"></textarea>
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="fas fa-check me-1"></i>{{ __('app.admin.account_deletion_requests.approve_delete') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.account-deletion-requests.reject', $item) }}">
                        @csrf
                        <label class="form-label small" for="rejectNote">{{ __('app.admin.account_deletion_requests.review_note') }}</label>
                        <textarea name="review_note" id="rejectNote" rows="3" class="form-control mb-2"></textarea>
                        <button type="submit" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-times me-1"></i>{{ __('app.admin.account_deletion_requests.reject') }}
                        </button>
                    </form>
                @else
                    <p class="text-muted small mb-0">{{ __('app.admin.account_deletion_requests.already_reviewed') }}</p>
                @endif
            </div>
        </div>
    </div>
@endsection
