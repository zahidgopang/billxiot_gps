@php
    $isBlocked = ($device->status ?? '') === 'blocked';
    $isActive = ($device->status ?? '') === 'active';
    $toggleUrl = $toggleUrl ?? '#';
@endphp
<div class="device-status-toggle d-flex align-items-center gap-2"
     data-url="{{ $toggleUrl }}"
     data-blocked="{{ $isBlocked ? '1' : '0' }}">
    <div class="form-check form-switch mb-0">
        <input class="form-check-input device-status-switch" type="checkbox" role="switch"
               id="device-status-{{ $device->id }}"
               {{ $isActive ? 'checked' : '' }}
               {{ $isBlocked ? 'disabled' : '' }}
               aria-label="{{ __('app.toggle.active') }}">
        <label class="form-check-label small text-muted mb-0 status-toggle-label" for="device-status-{{ $device->id }}">
            @if($isBlocked)
                {{ __('app.toggle.blocked') }}
            @elseif($isActive)
                {{ __('app.toggle.active') }}
            @else
                {{ __('app.toggle.inactive') }}
            @endif
        </label>
    </div>
    @if($isBlocked)
        <span class="badge bg-danger" title="{{ __('app.toggle.change_via_edit') }}">{{ __('app.toggle.blocked_badge') }}</span>
    @endif
</div>
