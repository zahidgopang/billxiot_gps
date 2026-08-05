@php
    use App\Services\Tracking\DeviceMapIconAuthorization;

    $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin');
    $canEdit = app(DeviceMapIconAuthorization::class)->canEditAppearance(auth()->user(), $device);
@endphp

@if($canEdit)
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/map-marker-appearance.css') }}?v={{ @filemtime(public_path('css/map-marker-appearance.css')) ?: 1 }}">
    @endpush

    <div class="mt-3">
        <x-admin.form-section
        :title="__('app.map.custom_icon_title')"
        icon="fas fa-upload"
        :description="__('app.map.custom_icon_upload_hint')"
    >
        <div class="col-12">
            @include('user.partials.map-marker-appearance-form', [
                'device' => $device,
                'formId' => 'adminMapMarkerAppearanceForm',
                'mapAppearanceSaveUrl' => route($panel . '.devices.map-appearance', $device),
                'mapCustomIconUploadUrl' => route($panel . '.devices.map-custom-icon', $device),
                'mapCustomIconDeleteUrl' => route($panel . '.devices.map-custom-icon.delete', $device),
            ])
        </div>
        </x-admin.form-section>
    </div>
@endif
