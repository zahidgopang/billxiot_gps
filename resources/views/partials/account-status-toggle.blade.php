@php
    $isActive = ($user->status ?? 'active') === 'active';
    $toggleUrl = $toggleUrl ?? '#';
    $toggleDisabled = $toggleDisabled ?? false;
@endphp
<div class="device-status-toggle d-flex align-items-center gap-2"
     data-url="{{ $toggleUrl }}"
     data-blocked="{{ $toggleDisabled ? '1' : '0' }}">
    <div class="form-check form-switch mb-0">
        <input class="form-check-input device-status-switch" type="checkbox" role="switch"
               id="user-status-{{ $user->id }}"
               {{ $isActive ? 'checked' : '' }}
               {{ $toggleDisabled ? 'disabled' : '' }}
               aria-label="{{ __('app.toggle.account_active') }}">
        <label class="form-check-label small text-muted mb-0 status-toggle-label" for="user-status-{{ $user->id }}">
            {{ $isActive ? __('app.toggle.active') : __('app.toggle.inactive') }}
        </label>
    </div>
</div>
