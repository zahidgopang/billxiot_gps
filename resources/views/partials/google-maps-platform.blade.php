{{-- Shared Google Maps bootstrap: async loader + map ID for Advanced Markers --}}
@once
<script>
window.GOOGLE_MAPS_CONFIG = Object.assign(window.GOOGLE_MAPS_CONFIG || {}, {
    key: @json(config('services.google.maps_key')),
    mapId: @json(config('services.google.maps_map_id')),
});
</script>
<script src="{{ protected_js('google-maps-platform.js') }}"></script>
@endonce
