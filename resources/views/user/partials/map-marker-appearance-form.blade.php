@php
    use App\Services\Tracking\DeviceMapAppearanceService;
    use App\Services\Tracking\DeviceMapIconAuthorization;
    use App\Support\VehicleIcons\VehicleIconLibrary;

    $auth = app(DeviceMapIconAuthorization::class);
    $user = auth()->user();
    $canEditAppearance = $user && $auth->canEditAppearance($user, $device);
    $canChangeIcon = $user && $auth->canChangeVehicleIcon($user, $device);
    $canChangeSize = $user && $auth->canChangeIconSize($user, $device);
    $canUpload = $user && $auth->canUploadCustomIcon($user, $device);

    if (! $canEditAppearance) {
        return;
    }

    $formId = $formId ?? 'mapMarkerAppearanceForm';
    $showHead = $showHead ?? true;
    $showBack = $showBack ?? false;
    $mapAppearanceSaveUrl = $mapAppearanceSaveUrl ?? route('user.devices.map-appearance', $device);
    $mapCustomIconUploadUrl = $mapCustomIconUploadUrl ?? route('user.devices.map-custom-icon', $device);
    $mapCustomIconDeleteUrl = $mapCustomIconDeleteUrl ?? route('user.devices.map-custom-icon.delete', $device);
    $mapAppearanceBackUrl = $mapAppearanceBackUrl ?? null;
    $appearanceOptions = app(DeviceMapAppearanceService::class)->options($user, $device);
    $appearance = $device->mapAppearancePayload();
    $sizePresets = VehicleIconLibrary::sizeScales();
    $registry = VehicleIconLibrary::clientRegistry();
    // Bulk Change-icon modal must not seed one vehicle's custom/AI upload into the shared preview.
    $forceDefaultPreview = (bool) ($forceDefaultPreview ?? ($mapCustomIconDeleteUrl === ''));
    $hasCustomIcon = ! $forceDefaultPreview
        && ($appearance['map_icon_source'] ?? 'default') === 'custom'
        && ! empty($appearance['map_custom_icon_url']);
    $currentType = $forceDefaultPreview
        ? 'car'
        : ($appearance['vehicle_type'] ?? 'car');
    $currentBuiltinPath = $forceDefaultPreview
        ? VehicleIconLibrary::builtinPathFor('car')
        : ($appearance['map_builtin_icon_path'] ?? VehicleIconLibrary::builtinPathFor($currentType));
@endphp
<form id="{{ $formId }}"
      class="map-marker-appearance"
      enctype="multipart/form-data"
      data-save-url="{{ $mapAppearanceSaveUrl }}"
      data-upload-url="{{ $canUpload ? $mapCustomIconUploadUrl : '' }}"
      data-delete-url="{{ $canUpload ? $mapCustomIconDeleteUrl : '' }}"
      data-upload-max="{{ $appearanceOptions['upload']['max_width'] ?? 256 }}"
      data-can-change-icon="{{ $canChangeIcon ? '1' : '0' }}"
      data-can-change-size="{{ $canChangeSize ? '1' : '0' }}"
      data-can-upload="{{ $canUpload ? '1' : '0' }}"
      data-icon-registry='@json($registry)'
      @if($hasCustomIcon)
          data-custom-active="1"
          data-custom-preview-url="{{ $appearance['map_custom_icon_url'] }}"
      @endif>
    @if($showHead)
        <div class="map-marker-appearance__head">
            <div class="d-flex align-items-start justify-content-between gap-2">
                <div>
                    <strong>{{ __('app.map.vehicle_icon') }}</strong>
                    <p class="small text-muted mb-0">{{ __('app.map.vehicle_icon_picker_shared_hint') }}</p>
                </div>
                @if($showBack)
                    @if($mapAppearanceBackUrl)
                        <a href="{{ $mapAppearanceBackUrl }}" class="btn btn-sm btn-outline-secondary flex-shrink-0">
                            <i class="fas fa-arrow-left me-1"></i>{{ __('app.map.back_devices') }}
                        </a>
                    @else
                        <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" data-map-appearance-back>
                            <i class="fas fa-arrow-left me-1"></i>{{ __('app.map.back_devices') }}
                        </button>
                    @endif
                @endif
            </div>
        </div>
    @endif

    <div class="map-marker-appearance__preview-wrap">
        <div class="map-marker-appearance__preview-pair">
            <div class="map-marker-appearance__art-block">
                <span class="map-marker-appearance__label">{{ __('app.map.artwork_as_drawn') }}</span>
                <div class="map-marker-appearance__art-scene" data-map-art-preview aria-live="polite">
                    <span class="map-marker-appearance__compass map-marker-appearance__compass--n" aria-hidden="true">N</span>
                    <span class="map-marker-appearance__compass map-marker-appearance__compass--e" aria-hidden="true">E</span>
                    <span class="map-marker-appearance__compass map-marker-appearance__compass--s" aria-hidden="true">S</span>
                    <span class="map-marker-appearance__compass map-marker-appearance__compass--w" aria-hidden="true">W</span>
                    <div class="map-marker-appearance__art-pin map-marker-appearance__art-pin--custom" data-map-art-custom hidden>
                        <img data-map-art-custom-img alt="" width="72" height="72">
                    </div>
                    <div class="map-marker-appearance__art-pin map-marker-appearance__art-pin--default" data-map-art-default></div>
                </div>
                <p class="small text-muted mb-0">{{ __('app.map.artwork_as_drawn_hint') }}</p>
            </div>
            <div class="map-marker-appearance__live-map" data-map-live-preview>
                <span class="map-marker-appearance__label">{{ __('app.map.map_live_preview_sample') }}</span>
                <div class="map-marker-appearance__live-map-scene">
                    <div class="map-marker-appearance__live-map-pin map-marker-appearance__live-map-pin--custom" data-map-live-custom hidden>
                        <img data-map-live-custom-img alt="" width="64" height="64">
                    </div>
                    <div class="map-marker-appearance__live-map-pin map-marker-appearance__live-map-pin--default" data-map-live-default></div>
                </div>
                <p class="map-marker-appearance__live-scale small text-muted mb-0" data-map-live-scale></p>
            </div>
        </div>
        <p class="small text-info mb-0" data-map-resize-notice hidden role="status"></p>
    </div>

    @if($canChangeIcon)
        <div class="map-marker-appearance__row" data-icon-library>
            <span class="map-marker-appearance__label">{{ __('app.map.shared_icon_suggestions') }}</span>
            <div class="vehicle-icon-picker" data-vehicle-icon-picker>
                <div class="vehicle-icon-picker__search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search"
                           class="form-control form-control-sm"
                           data-icon-search
                           placeholder="{{ __('app.map.search_vehicle_icon') }}"
                           autocomplete="off">
                </div>
                <div class="vehicle-icon-picker__cats" data-icon-categories role="tablist"></div>
                <div class="vehicle-icon-picker__meta-row">
                    <button type="button" class="vehicle-icon-picker__chip" data-icon-filter="all">{{ __('app.map.icon_filter_all') }}</button>
                    <button type="button" class="vehicle-icon-picker__chip" data-icon-filter="recent">{{ __('app.map.icon_filter_recent') }}</button>
                    <button type="button" class="vehicle-icon-picker__chip" data-icon-filter="favorites">{{ __('app.map.icon_filter_favorites') }}</button>
                </div>
                <div class="vehicle-icon-picker__grid" data-icon-grid role="listbox" aria-label="{{ __('app.map.built_in_icon_library') }}"></div>
            </div>
            <input type="hidden" name="vehicle_type" data-map-vehicle-type value="{{ $currentType }}">
            <input type="hidden" name="map_builtin_icon_path" data-map-builtin-icon-path value="{{ $currentBuiltinPath }}">
        </div>
    @else
        <input type="hidden" name="vehicle_type" data-map-vehicle-type value="{{ $currentType }}">
        <input type="hidden" name="map_builtin_icon_path" data-map-builtin-icon-path value="{{ $currentBuiltinPath }}">
    @endif

    @if($canUpload)
        <div class="map-marker-appearance__row map-marker-appearance__upload">
            <span class="map-marker-appearance__label">{{ __('app.map.custom_icon_title') }}</span>
            <label class="btn btn-outline-secondary btn-sm mb-0" for="{{ $formId }}-file">
                <i class="fas fa-upload me-1"></i>{{ __('app.map.custom_icon_upload') }}
            </label>
            <input type="file"
                   id="{{ $formId }}-file"
                   class="d-none"
                   data-map-custom-file
                   accept=".png,.svg,image/png,image/svg+xml">
            <div class="map-marker-appearance__upload-actions mt-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-map-revert-custom @if(! $hasCustomIcon) hidden @endif>
                    {{ __('app.map.custom_icon_remove') }}
                </button>
            </div>
            <p class="small text-muted mb-0 mt-1">
                {{ __('app.map.custom_icon_upload_apply_hint') }}
                PNG / SVG · max {{ $appearanceOptions['upload']['max_kb'] ?? 512 }} KB ·
                {{ __('app.map.custom_icon_auto_resize_hint', ['max' => $appearanceOptions['upload']['max_width'] ?? 256]) }}
            </p>
        </div>
    @endif

    @if($canChangeIcon)
        @php $currentOffset = (string) ((int) ($appearance['map_icon_rotation_offset'] ?? 0)); @endphp
        <div class="map-marker-appearance__row map-marker-appearance__orientation">
            <span class="map-marker-appearance__label">{{ __('app.map.icon_default_orientation') }}</span>
            <div class="alert alert-light border small mb-2 py-2 px-3" role="note">
                {{ __('app.map.icon_orientation_hint') }}
            </div>
            <select class="form-select form-select-sm" data-map-rotation-offset name="map_icon_rotation_offset" aria-describedby="{{ $formId }}-orient-help">
                <option value="-90" @selected($currentOffset === '-90')>{{ __('app.map.icon_orient_east') }}</option>
                <option value="0" @selected($currentOffset === '0')>{{ __('app.map.icon_orient_north') }}</option>
                <option value="180" @selected($currentOffset === '180')>{{ __('app.map.icon_orient_south') }}</option>
                <option value="90" @selected($currentOffset === '90')>{{ __('app.map.icon_orient_west') }}</option>
            </select>
            <p class="small text-muted mb-0 mt-1" id="{{ $formId }}-orient-help">{{ __('app.map.icon_orientation_example') }}</p>
        </div>
    @endif

    @if($canChangeSize)
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
    @endif

    @if($canChangeSize || $canChangeIcon || $canUpload)
        <div class="map-marker-appearance__foot">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-save me-1"></i>{{ __('app.map.save') }}
            </button>
            <span class="small text-success" data-map-save-status role="status"></span>
        </div>
    @endif
</form>
