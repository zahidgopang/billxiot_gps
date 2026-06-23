@props([
    'action',
    'method' => 'POST',
    'cancelUrl' => '#',
    'wide' => false,
    'enctype' => null,
])

@php
    $httpMethod = strtoupper($method);
    $formMethod = in_array($httpMethod, ['GET', 'POST'], true) ? $httpMethod : 'POST';
@endphp

<div @class(['admin-form-page', 'admin-form-page--wide' => $wide])>
    <form
        action="{{ $action }}"
        method="{{ $formMethod }}"
        @if($enctype) enctype="{{ $enctype }}" @endif
        {{ $attributes->merge(['class' => 'admin-form']) }}
    >
        @csrf
        @if(! in_array($httpMethod, ['GET', 'POST'], true))
            @method($httpMethod)
        @endif

        <div class="admin-form__body">
            {{ $slot }}
        </div>

        @if(isset($footer))
            <div class="admin-form__footer">
                {{ $footer }}
            </div>
        @endif
    </form>
</div>
