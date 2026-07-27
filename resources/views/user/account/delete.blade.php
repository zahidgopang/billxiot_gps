@extends('user.layout_user')

@section('title', __('app.user.account_delete.title') . ' — ' . __('app.brand'))

@push('styles')
<style>
    .delete-account-card {
        background: #fff;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.18);
        max-width: 640px;
        margin: 0 auto;
    }
    .delete-account-icon {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.5rem;
        background: linear-gradient(135deg, #EF4444, #DC2626);
        box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        margin-inline-end: 1.25rem;
        flex-shrink: 0;
    }
    .delete-summary {
        background: rgba(239, 68, 68, 0.05);
        border: 1px solid rgba(239, 68, 68, 0.15);
        border-radius: 14px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
    }
    .delete-summary li { margin-bottom: 0.35rem; }
    .form-control-premium {
        background: #fff;
        border: 2px solid rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        padding: 0.75rem 1rem;
    }
    .form-control-premium:focus {
        border-color: #EF4444;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
    }
</style>
@endpush

@section('content')
<div class="container dashboard-container py-4">
    <div class="d-flex align-items-center mb-4">
        <div class="delete-account-icon"><i class="fas fa-user-slash"></i></div>
        <div>
            <h1 class="h4 mb-1 fw-bold">{{ __('app.user.account_delete.title') }}</h1>
            <p class="text-muted mb-0">{{ __('app.user.account_delete.step1_subtitle') }}</p>
        </div>
    </div>

    <div class="delete-account-card">
        @if(!empty($pendingRequest))
            <div class="alert alert-warning" role="alert">
                <strong>{{ __('app.user.account_delete.request_pending_title') }}</strong>
                <div class="mt-1">
                    {{ __('app.user.account_delete.request_pending_body', ['ticket' => $pendingRequest->ticketNumber()]) }}
                </div>
            </div>
            <a href="{{ route('user.profile') }}" class="btn btn-outline-secondary">{{ __('app.common.back') }}</a>
        @else
            <div class="alert alert-warning d-flex align-items-start" role="alert">
                <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
                <div>{{ __('app.user.account_delete.warning') }}</div>
            </div>

            <div class="delete-summary">
                <strong class="d-block mb-2">{{ __('app.user.account_delete.will_remove') }}</strong>
                <ul class="mb-0 ps-3">
                    <li>{{ __('app.user.account_delete.summary_vehicles', ['count' => $summary['exclusive_vehicles']]) }}</li>
                    @if($summary['exclusive_vehicles'] > 0)
                        <li>{{ __('app.user.account_delete.summary_history') }}</li>
                    @endif
                    @if($summary['shared_vehicles'] > 0)
                        <li>{{ __('app.user.account_delete.summary_shared', ['count' => $summary['shared_vehicles']]) }}</li>
                    @endif
                    <li>{{ __('app.user.account_delete.summary_sub_accounts', ['count' => $summary['sub_accounts']]) }}</li>
                    <li>{{ __('app.user.account_delete.summary_subscriptions', ['count' => $summary['subscriptions']]) }}</li>
                    <li>{{ __('app.user.account_delete.summary_account') }}</li>
                </ul>
            </div>

            @if($errors->any())
                <div class="alert alert-danger">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('user.account.delete.verify') }}" autocomplete="off">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="deleteEmail">{{ __('app.user.account_delete.email') }}</label>
                    <input
                        type="email"
                        name="email"
                        id="deleteEmail"
                        value="{{ old('email') }}"
                        class="form-control form-control-premium @error('email') is-invalid @enderror"
                        placeholder="{{ $user->email }}"
                        required
                        autofocus
                    >
                    <small class="text-muted">{{ __('app.user.account_delete.email_hint') }}</small>
                    @error('email')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold" for="deletePassword">{{ __('app.user.account_delete.password') }}</label>
                    <input
                        type="password"
                        name="password"
                        id="deletePassword"
                        class="form-control form-control-premium @error('password') is-invalid @enderror"
                        required
                    >
                    @error('password')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-between">
                    <a href="{{ route('user.profile') }}" class="btn btn-outline-secondary">
                        {{ __('app.common.cancel') }}
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-arrow-right me-2"></i>{{ __('app.user.account_delete.continue') }}
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
