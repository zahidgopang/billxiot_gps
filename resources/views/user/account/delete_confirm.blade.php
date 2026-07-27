@extends('user.layout_user')

@section('title', __('app.user.account_delete.confirm_title') . ' — ' . __('app.brand'))

@push('styles')
<style>
    .delete-account-card {
        background: #fff;
        border-radius: 20px;
        padding: 2rem;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.25);
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
        background: linear-gradient(135deg, #EF4444, #B91C1C);
        box-shadow: 0 8px 25px rgba(239, 68, 68, 0.35);
        margin-inline-end: 1.25rem;
        flex-shrink: 0;
    }
    .delete-summary {
        background: #FEF2F2;
        border: 1px solid #FECACA;
        border-radius: 14px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
    }
    .form-control-premium {
        background: #fff;
        border: 2px solid rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        padding: 0.75rem 1rem;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        letter-spacing: 0.04em;
    }
    .form-control-premium:focus {
        border-color: #EF4444;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
    }
    .confirm-word {
        display: inline-block;
        padding: 0.15rem 0.5rem;
        border-radius: 6px;
        background: #FEE2E2;
        color: #991B1B;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-weight: 700;
    }
</style>
@endpush

@section('content')
<div class="container dashboard-container py-4">
    <div class="d-flex align-items-center mb-4">
        <div class="delete-account-icon"><i class="fas fa-trash-alt"></i></div>
        <div>
            <h1 class="h4 mb-1 fw-bold">{{ __('app.user.account_delete.confirm_title') }}</h1>
            <p class="text-muted mb-0">{{ __('app.user.account_delete.step2_subtitle') }}</p>
        </div>
    </div>

    <div class="delete-account-card">
        <div class="alert alert-danger" role="alert">
            <strong>{{ __('app.user.account_delete.final_warning_title') }}</strong>
            <div class="mt-1">{{ __('app.user.account_delete.final_warning_body') }}</div>
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

        <form method="POST" action="{{ route('user.account.delete.destroy') }}" autocomplete="off"
              onsubmit="return confirm(@json(__('app.user.account_delete.browser_confirm')));">
            @csrf
            @method('DELETE')

            <div class="mb-3">
                <label class="form-label fw-semibold" for="deleteReason">{{ __('app.user.account_delete.reason') }}</label>
                <textarea
                    name="reason"
                    id="deleteReason"
                    rows="3"
                    class="form-control form-control-premium @error('reason') is-invalid @enderror"
                    placeholder="{{ __('app.user.account_delete.reason_hint') }}"
                >{{ old('reason') }}</textarea>
                @error('reason')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold" for="deleteConfirmation">
                    {!! __('app.user.account_delete.type_delete_label', ['word' => '<span class="confirm-word">delete</span>']) !!}
                </label>
                <input
                    type="text"
                    name="confirmation"
                    id="deleteConfirmation"
                    class="form-control form-control-premium @error('confirmation') is-invalid @enderror"
                    placeholder="delete"
                    required
                    autofocus
                    autocomplete="off"
                >
                @error('confirmation')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex flex-wrap gap-2 justify-content-between">
                <a href="{{ route('user.account.delete') }}" class="btn btn-outline-secondary">
                    {{ __('app.common.back') }}
                </a>
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-paper-plane me-2"></i>{{ __('app.user.account_delete.submit_request') }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
