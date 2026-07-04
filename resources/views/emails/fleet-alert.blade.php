@component('mail::message')
# {{ $alertTitle }}

{{ $alertBody }}

@if(!empty($context['device_name']))
**{{ __('app.tracking.vehicle') }}:** {{ $context['device_name'] }}
@endif

@if(!empty($context['time_display']))
**{{ __('app.tracking.col_time') }}:** {{ $context['time_display'] }}
@endif

@component('mail::button', ['url' => config('app.url')])
{{ __('app.tracking.notifications_nav') }}
@endcomponent

{{ config('app.name') }}
@endcomponent
