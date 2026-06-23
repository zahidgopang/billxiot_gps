@php
    $classes = trim('brand-logo ' . ($class ?? ''));
    $onDark = !empty($onDark);
    $darkLogo = config('branding.logo_dark');
    $logoSrc = ($onDark && $darkLogo) ? $darkLogo : config('branding.logo');
    $usePlate = $onDark && !$darkLogo;
@endphp
@if($usePlate)
    <span class="brand-logo-wrap brand-logo-wrap--on-dark">
@endif
<img
    src="{{ asset($logoSrc) }}"
    alt="{{ config('branding.name') }} — GPS tracking and fleet management logo"
    class="{{ $classes }}"
    @if(!empty($style)) style="{{ $style }}" @endif
    loading="lazy"
>
@if($usePlate)
    </span>
@endif
