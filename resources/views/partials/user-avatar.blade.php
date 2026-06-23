@props([
    'user',
    'size' => 40,
    'class' => '',
    'style' => '',
])

@php
    $avatarUrl = $user->avatarUrl();
    $initial = strtoupper(substr($user->name ?? 'U', 0, 1));
    $px = (int) $size;
@endphp

@if ($avatarUrl)
    <img
        src="{{ $avatarUrl }}"
        alt="{{ $user->name }}"
        class="user-avatar-img {{ $class }}"
        style="width: {{ $px }}px; height: {{ $px }}px; object-fit: cover; border-radius: 50%; {{ $style }}"
    >
@else
    <div
        class="user-avatar {{ $class }}"
        style="width: {{ $px }}px; height: {{ $px }}px; font-size: {{ max(12, (int) round($px * 0.4)) }}px; {{ $style }}"
    >{{ $initial }}</div>
@endif
