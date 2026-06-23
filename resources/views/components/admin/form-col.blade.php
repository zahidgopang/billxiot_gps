@props([
    'cols' => 6,
    'full' => false,
])

@php
    $colClass = $full
        ? 'col-12'
        : match ((int) $cols) {
            12 => 'col-12',
            4 => 'col-12 col-md-4',
            8 => 'col-12 col-md-8',
            default => 'col-12 col-md-6',
        };
@endphp

<div {{ $attributes->merge(['class' => $colClass . ' admin-field']) }}>
    {{ $slot }}
</div>
