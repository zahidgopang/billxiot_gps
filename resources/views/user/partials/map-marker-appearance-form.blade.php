@php
    use App\Models\Device;
    use App\Services\Tracking\DeviceMapAppearanceService;
    use App\Services\Tracking\DeviceMapIconAuthorization;

    $formId = $formId ?? 'mapMarkerAppearanceForm';
    $mapAppearanceSaveUrl = $mapAppearanceSaveUrl ?? route('user.devices.map-appearance', $device);
    $mapCustomIconUploadUrl = $mapCustomIconUploadUrl ?? route('user.devices.map-custom-icon', $device);
    $mapCustomIconDeleteUrl = $mapCustomIconDeleteUrl ?? route('user.devices.map-custom-icon.delete', $device);
    $mapAppearanceBackUrl = $mapAppearanceBackUrl ?? null;
    $appearanceOptions = app(DeviceMapAppearanceService::class)->options(auth()->user(), $device);
    $canUpload = app(DeviceMapIconAuthorization::class)->canUploadCustomIcon(auth()->user(), $device);
    $appearance = $device->mapAppearancePayload();
    $sizePresets = \App\Support\VehicleIcons\VehicleIconLibrary::sizeScales();
    $hasCustomIcon = ($appearance['map_icon_source'] ?? 'default') === 'custom' && ! empty($appearance['map_custom_icon_url']);
@endphp
<form id="{{ $formId }}"
      class="map-marker-appearance map-marker-appearance--upload-only"
      enctype="multipart/form-data"
      data-save-url="{{ $mapAppearanceSaveUrl }}"
      data-upload-url="{{ $canUpload ? $mapCustomIconUploadUrl : '' }}"
      data-delete-url="{{ $canUpload ? $mapCustomIconDeleteUrl : '' }}"
      data-upload-max="{{ $appearanceOptions['upload']['max_width'] ?? 256 }}"
      data-upload-only="1">
    <div class="map-marker-appearance__head">
        <div class="d-flex align-items-start justify-content-between gap-2">
            <div>
                <strong>{{ __('app.map.custom_icon_title') }}</strong>
                <p class="small text-muted mb-0">{{ __('app.map.custom_icon_upload_hint') }}</p>
            </div>
            @if($mapAppearanceBackUrl)
                <a href="{{ $mapAppearanceBackUrl }}" class="btn btn-sm btn-outline-secondary flex-shrink-0">
                    <i class="fas fa-arrow-left me-1"></i>{{ __('app.map.back_devices') }}
                </a>
            @else
                <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" data-map-appearance-back>
                    <i class="fas fa-arrow-left me-1"></i>{{ __('app.map.back_devices') }}
                </button>
            @endif
        </div>
    </div>

    <div class="map-marker-appearance__preview-wrap">
        <span class="map-marker-appearance__label">{{ __('app.map.map_live_preview') }}</span>
        <div class="map-marker-appearance__live-map" data-map-live-preview aria-live="polite">
            <div class="map-marker-appearance__live-map-scene">
                <div class="map-marker-appearance__live-map-pin map-marker-appearance__live-map-pin--custom" data-map-live-custom hidden>
                    <img data-map-live-custom-img alt="" width="64" height="64">
                </div>
                <div class="map-marker-appearance__live-map-pin map-marker-appearance__live-map-pin--default" data-map-live-default></div>
            </div>
            <p class="map-marker-appearance__live-scale small text-muted mb-0" data-map-live-scale></p>
        </div>
        <p class="small text-muted mb-0">{{ __('app.map.custom_icon_size_tip') }}</p>
        <p class="small text-info mb-0" data-map-resize-notice hidden role="status"></p>
    </div>

    @if($canUpload)
        <div class="map-marker-appearance__row map-marker-appearance__upload">
            <label class="map-marker-appearance__label" for="{{ $formId }}-file">{{ __('app.map.custom_icon_upload') }}</label>
            <input type="file"
                   id="{{ $formId }}-file"
                   class="form-control form-control-sm"
                   data-map-custom-file
                   accept=".png,.svg,.webp,image/png,image/svg+xml,image/webp">
            <div class="map-marker-appearance__upload-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-map-revert-custom @if(! $hasCustomIcon) hidden @endif>
                    {{ __('app.map.custom_icon_remove') }}
                </button>
            </div>
            <p class="small text-muted mb-0">
                PNG / SVG / WebP · max {{ $appearanceOptions['upload']['max_kb'] ?? 512 }} KB ·
                {{ __('app.map.custom_icon_auto_resize_hint', ['max' => $appearanceOptions['upload']['max_width'] ?? 256]) }}
            </p>
        </div>
    @else
        <div class="alert alert-light border small mb-0">{{ __('app.map.icon_upload_permission_denied') }}</div>
    @endif

    <input type="hidden" name="vehicle_type" data-map-vehicle-type value="{{ $appearance['vehicle_type'] ?? 'car' }}">

    <div class="map-marker-appearance__row map-marker-appearance__size">
        <span class="map-marker-appearance__label">{{ __('app.map.marker_icon_size') }}</span>
        <p class="small text-muted mb-1">{{ __('app.map.marker_icon_size_hint') }}</p>
        <input type="range" class="form-range" data-map-size-range
               min="0" max="{{ count($sizePresets) - 1 }}" step="1"
               value="{{ array_search($appearance['map_marker_size'] ?? '100', array_keys($sizePresets), true) ?: 2 }}">
        <div class="map-marker-appearance__size-controls">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-map-size-dec title="{{ __('app.map.marker_size_decrease') }}">−</button>
            <span data-map-size-label>{{ __('app.map.marker_size_'.($appearance['map_marker_size'] ?? '100')) }}</span>
            <span data-map-size-value hidden>{{ $appearance['map_marker_size'] ?? '100' }}</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-map-size-inc title="{{ __('app.map.marker_size_increase') }}">+</button>
        </div>
    </div>

    <div class="map-marker-appearance__row">
        <label class="form-check-label">
            <input type="checkbox" class="form-check-input" data-map-rotation
                   @checked($appearance['map_icon_rotation_enabled'] ?? true)>
            {{ __('app.map.icon_rotation') }}
        </label>
    </div>

    <div class="map-marker-appearance__foot">
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-save me-1"></i>{{ __('app.map.save') }}
        </button>
        <span class="small text-success" data-map-save-status role="status"></span>
    </div>
</form>
