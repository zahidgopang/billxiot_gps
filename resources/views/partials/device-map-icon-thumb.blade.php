@php
    $fallback = '/icons/builtin/Vehicles/car.svg';
    $url = $device->listMapIconUrl() ?: $fallback;
    $size = (int) ($size ?? 36);
    $class = trim('device-map-icon-thumb '.($class ?? ''));
    $alt = $alt ?? $device->listPrimaryLabel();
@endphp
<img src="{{ $url }}"
     alt="{{ $alt }}"
     class="{{ $class }}"
     width="{{ $size }}"
     height="{{ $size }}"
     loading="lazy"
     decoding="async"
     data-fallback-icon="{{ $fallback }}"
     onerror="if(this.dataset.fallbackApplied){return;} this.dataset.fallbackApplied='1'; this.src=this.dataset.fallbackIcon || '{{ $fallback }}';">
