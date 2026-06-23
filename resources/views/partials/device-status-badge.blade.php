@php
    $status = $device->status ?? 'inactive';
    $badgeClass = match ($status) {
        'active' => 'bg-success',
        'blocked' => 'bg-danger',
        default => 'bg-secondary',
    };
    $label = match ($status) {
        'active' => __('app.toggle.active'),
        'blocked' => __('app.toggle.blocked'),
        default => __('app.toggle.inactive'),
    };
@endphp
<span class="badge {{ $badgeClass }}">{{ $label }}</span>
@if($status === 'blocked')
    <small class="d-block text-muted mt-1" style="font-size: 0.7rem;">{{ __('app.toggle.change_via_edit') }}</small>
@endif
