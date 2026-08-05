@php
    use App\Services\Mobile\MapRenderingSpec;
    use App\Support\VehicleIcons\VehicleIconLibrary;

    $appearance = $device->mapAppearancePayload();
    $sizeScales = VehicleIconLibrary::sizeScales();
@endphp
<script src="{{ protected_js('vehicle-marker.js') }}"></script>
<script src="{{ protected_js('map-marker-appearance.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('adminMapMarkerAppearanceForm');
    if (!form || !window.MapMarkerAppearance) return;

    const sizeOrder = window.MapMarkerAppearance.DEFAULT_SIZE_ORDER;
    const i18nSizes = {
        @foreach($sizeScales as $key => $scale)
        '{{ $key }}': @json(__('app.map.marker_size_'.$key)),
        @endforeach
    };

    window.MapMarkerAppearance.bindForm({
        form,
        saveUrl: form.dataset.saveUrl,
        uploadUrl: form.dataset.uploadUrl || null,
        deleteUrl: form.dataset.deleteUrl || null,
        csrf: @json(csrf_token()),
        initial: @json($appearance),
        mapRendering: @json(MapRenderingSpec::toArray()),
        previewTitle: @json($device->mapMarkerTitle()),
        previewPlate: @json($device->mapMarkerPlateLine()),
        sizeOrder,
        i18n: {
            saved: @json(__('app.map.marker_appearance_saved')),
            failed: @json(__('app.map.marker_appearance_save_failed')),
            uploaded: @json(__('app.map.custom_icon_uploaded')),
            resized: @json(__('app.map.custom_icon_resized_notice')),
            liveScale: @json(__('app.map.map_live_scale')),
            sizes: i18nSizes,
        },
        onSaved(appearance) {
            window.MapMarkerAppearance.renderPreview?.(form, {
                initial: appearance,
                mapRendering: @json(MapRenderingSpec::toArray()),
                sizeOrder,
                i18n: { sizes: i18nSizes },
                previewTitle: @json($device->mapMarkerTitle()),
                previewPlate: @json($device->mapMarkerPlateLine()),
            });
        },
    });
});
</script>
