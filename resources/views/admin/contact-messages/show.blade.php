@extends('admin.layouts.app')

@section('title', __('app.admin.contact_messages.view_title'))
@section('page-title', __('app.admin.contact_messages.view_title'))

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.contact-messages.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>{{ __('app.admin.contact_messages.back_to_inbox') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h4 class="mb-1">{{ $message->subject }}</h4>
                        <p class="text-muted small mb-0">
                            {{ __('app.admin.contact_messages.ticket', ['number' => 'TP-' . str_pad($message->id, 6, '0', STR_PAD_LEFT)]) }}
                            · {{ $message->created_at?->format('M d, Y \a\t h:i A') }}
                        </p>
                    </div>
                    @php
                        $badge = match($message->status) {
                            'new' => 'danger',
                            'read' => 'info',
                            'replied' => 'success',
                            'closed' => 'secondary',
                            default => 'light',
                        };
                    @endphp
                    <span class="badge bg-{{ $badge }} fs-6">{{ __('app.admin.contact_messages.status_' . $message->status) }}</span>
                </div>

                <div class="mb-4">
                    <h6 class="text-muted text-uppercase small">Message</h6>
                    <div class="p-3 rounded bg-light border" style="white-space: pre-wrap;">{{ $message->message }}</div>
                </div>

                <div class="row g-3 small">
                    <div class="col-md-6">
                        <strong>{{ __('app.common.name') }}:</strong> {{ $message->name }}
                    </div>
                    <div class="col-md-6">
                        <strong>{{ __('app.forms.email') }}:</strong>
                        <a href="mailto:{{ $message->email }}">{{ $message->email }}</a>
                    </div>
                    @if($message->phone)
                        <div class="col-md-6">
                            <strong>{{ __('app.forms.phone') }}:</strong> {{ $message->phone }}
                        </div>
                    @endif
                    @if($message->company)
                        <div class="col-md-6">
                            <strong>Company:</strong> {{ $message->company }}
                        </div>
                    @endif
                    @if($message->ip_address)
                        <div class="col-md-6">
                            <strong>IP:</strong> {{ $message->ip_address }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card p-4">
                <h6 class="mb-3">{{ __('app.admin.contact_messages.update_status') }}</h6>
                <form method="POST" action="{{ route('admin.contact-messages.update', $message) }}">
                    @csrf
                    @method('PATCH')
                    <select name="status" class="form-select mb-3">
                        @foreach(['new', 'read', 'replied', 'closed'] as $st)
                            <option value="{{ $st }}" @selected($message->status === $st)>
                                {{ __('app.admin.contact_messages.status_' . $st) }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary w-100">{{ __('app.common.save') }}</button>
                </form>

                <hr>

                <a href="mailto:{{ $message->email }}?subject=Re: {{ rawurlencode($message->subject) }}"
                   class="btn btn-outline-primary w-100 mb-2">
                    <i class="fas fa-reply me-1"></i>{{ __('app.admin.contact_messages.reply_email') }}
                </a>
            </div>
        </div>
    </div>
@endsection
