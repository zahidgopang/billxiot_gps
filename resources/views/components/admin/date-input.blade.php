@props([
    'name',
    'id' => null,
    'value' => '',
    'required' => false,
    'readonly' => false,
    'allowFuture' => false,
    'static' => false,
    'minDateFrom' => null,
    'inputClass' => 'form-control form-control-sm',
])

@php
    $inputId = $id ?? $name;
@endphp

<div @class(['admin-date-wrap', 'admin-date-wrap--static' => $static])>
    <div class="admin-date-wrap__field">
        <input type="text"
               name="{{ $name }}"
               id="{{ $inputId }}"
               value="{{ $value }}"
               {{ $attributes->class([
                   $inputClass,
                   'js-date-picker',
                   'admin-ltr',
                   'no-flatpickr' => $static,
               ])->merge([
                   'dir' => 'ltr',
                   'autocomplete' => 'off',
               ]) }}
               @required($required)
               @readonly($readonly)
               @if($allowFuture) data-allow-future="true" @endif
               @if($static) data-flatpickr-static="true" @endif
               @if($minDateFrom) data-min-date-from="{{ $minDateFrom }}" @endif>
        <button type="button" class="admin-date-wrap__trigger" tabindex="-1" aria-hidden="true" @if($static) disabled @endif>
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
        </button>
    </div>
</div>
