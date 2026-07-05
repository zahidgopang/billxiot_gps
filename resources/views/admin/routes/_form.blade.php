@php
    use App\Support\Geo\PolylineDecoder;
    $checkpointsJson = old('checkpoints', ($route->relationLoaded('checkpoints') ? $route->checkpoints : collect())->map(fn ($cp) => [
        'sequence' => $cp->sequence,
        'from_location' => $cp->from_location,
        'to_location' => $cp->to_location,
        'from_lat' => $cp->from_lat,
        'from_lng' => $cp->from_lng,
        'to_lat' => $cp->to_lat,
        'to_lng' => $cp->to_lng,
        'distance_km' => $cp->distance_km,
        'expected_duration_minutes' => $cp->expected_duration_minutes,
        'speed_limit_kmh' => $cp->speed_limit_kmh,
        'notes' => $cp->notes,
    ])->values()->all());
    $guidedPolylineJson = ($route->encoded_polyline ?? null)
        ? PolylineDecoder::decode($route->encoded_polyline)
        : [];
@endphp

<form method="post" action="{{ $action }}" id="routeAdminForm">
    @csrf
    @if(($method ?? 'POST') !== 'POST')
        @method($method)
    @endif

    <input type="hidden" name="checkpoints" id="routeCheckpointsJson" value="{{ json_encode($checkpointsJson) }}">

    <div class="row g-3">
        <div class="col-lg-5 order-lg-2">
            <div class="card shadow-sm route-map-card sticky-lg-top">
                <div class="card-header py-2">
                    <strong>{{ __('app.routes.pick_on_map') }}</strong>
                </div>
                <div class="card-body p-0">
                    <div class="route-map-toolbar p-2 border-bottom bg-light">
                        <label class="form-label small mb-1" for="routeMapSearch">{{ __('app.routes.map_search') }}</label>
                        <input type="text"
                               id="routeMapSearch"
                               class="form-control form-control-sm"
                               placeholder="{{ __('app.routes.map_search_placeholder') }}"
                               autocomplete="off">
                        <div class="form-text small mb-2">{{ __('app.routes.map_search_help') }}</div>
                        <div class="btn-group btn-group-sm w-100 route-pick-btn-group" role="group" aria-label="{{ __('app.routes.pick_on_map') }}">
                            <button type="button" class="btn btn-outline-success route-pick-btn active" id="pickStartBtn" data-pick-mode="start">
                                <i class="fas fa-play me-1"></i>{{ __('app.routes.pick_start') }}
                            </button>
                            <button type="button" class="btn btn-outline-danger route-pick-btn" id="pickDestBtn" data-pick-mode="dest">
                                <i class="fas fa-flag-checkered me-1"></i>{{ __('app.routes.pick_destination') }}
                            </button>
                        </div>
                        <div id="routePickHint" class="small text-primary mt-2 fw-semibold">{{ __('app.routes.pick_mode_start') }}</div>
                        <div id="routePolylineStatus" class="small text-muted mt-2" hidden></div>
                    </div>
                    <div id="routeAdminMap" class="route-admin-map" role="region" aria-label="{{ __('app.routes.pick_on_map') }}"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-7 order-lg-1">
            <div class="card shadow-sm mb-3">
                <div class="card-header py-2">
                    <strong>{{ __('app.routes.route_details') }}</strong>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label">{{ __('app.routes.title') }}</label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name', $route->name) }}" placeholder="{{ __('app.routes.name_placeholder') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('app.common.status') }}</label>
                            <select name="status" class="form-select">
                                <option value="active" @selected(old('status', $route->status) === 'active')>{{ __('app.common.active') }}</option>
                                <option value="inactive" @selected(old('status', $route->status) === 'inactive')>{{ __('app.common.inactive') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header py-2">
                    <strong>{{ __('app.routes.endpoints') }}</strong>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">{{ __('app.routes.endpoint_help') }}</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="routeStartCity">
                                <span class="badge bg-success me-1">S</span>{{ __('app.routes.start_location') }}
                            </label>
                            <input type="text"
                                   name="start_city"
                                   id="routeStartCity"
                                   class="form-control route-place-input"
                                   required
                                   value="{{ old('start_city', $route->start_city) }}"
                                   placeholder="{{ __('app.routes.location_search_placeholder') }}"
                                   autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="routeDestCity">
                                <span class="badge bg-danger me-1">D</span>{{ __('app.routes.destination_location') }}
                            </label>
                            <input type="text"
                                   name="destination_city"
                                   id="routeDestCity"
                                   class="form-control route-place-input"
                                   required
                                   value="{{ old('destination_city', $route->destination_city) }}"
                                   placeholder="{{ __('app.routes.location_search_placeholder') }}"
                                   autocomplete="off">
                        </div>
                    </div>

                    <div class="accordion mt-3" id="routeCoordsAccordion">
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-2 px-3 bg-light rounded" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#routeCoordsCollapse">
                                    {{ __('app.routes.coordinates') }}
                                </button>
                            </h2>
                            <div id="routeCoordsCollapse" class="accordion-collapse collapse" data-bs-parent="#routeCoordsAccordion">
                                <div class="accordion-body px-0 pt-2 pb-0">
                                    <p class="form-text small">{{ __('app.routes.coordinates_help') }}</p>
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <label class="form-label small">{{ __('app.routes.start_lat') }}</label>
                                            <input type="number" step="any" name="start_lat" id="routeStartLat" class="form-control form-control-sm" required value="{{ old('start_lat', $route->start_lat) }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">{{ __('app.routes.start_lng') }}</label>
                                            <input type="number" step="any" name="start_lng" id="routeStartLng" class="form-control form-control-sm" required value="{{ old('start_lng', $route->start_lng) }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">{{ __('app.routes.dest_lat') }}</label>
                                            <input type="number" step="any" name="destination_lat" id="routeDestLat" class="form-control form-control-sm" required value="{{ old('destination_lat', $route->destination_lat) }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">{{ __('app.routes.dest_lng') }}</label>
                                            <input type="number" step="any" name="destination_lng" id="routeDestLng" class="form-control form-control-sm" required value="{{ old('destination_lng', $route->destination_lng) }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header py-2">
                    <strong>{{ __('app.routes.settings') }}</strong>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('app.routes.expected_distance') }}</label>
                            <input type="number" step="0.01" name="expected_distance_km" id="routeExpectedDistance" class="form-control" value="{{ old('expected_distance_km', $route->expected_distance_km) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('app.routes.expected_duration') }}</label>
                            <input type="number" name="expected_duration_minutes" id="routeExpectedDuration" class="form-control" value="{{ old('expected_duration_minutes', $route->expected_duration_minutes) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('app.routes.arrival_radius') }}</label>
                            <input type="number" name="arrival_radius_meters" class="form-control" value="{{ old('arrival_radius_meters', $route->arrival_radius_meters ?: 500) }}">
                        </div>
                        <div class="col-md-4 form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="show_polyline" value="1" id="showPolyline" @checked(old('show_polyline', $route->show_polyline))>
                            <label class="form-check-label" for="showPolyline">{{ __('app.routes.show_polyline') }}</label>
                            <div class="form-text small">{{ __('app.routes.show_polyline_help') }}</div>
                        </div>
                        <div class="col-md-4 form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="show_progress_bar" value="1" id="showProgressBar" @checked(old('show_progress_bar', $route->show_progress_bar))>
                            <label class="form-check-label" for="showProgressBar">{{ __('app.routes.show_progress_bar') }}</label>
                        </div>
                        <div class="col-md-4 form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="auto_complete_on_arrival" value="1" id="autoComplete" @checked(old('auto_complete_on_arrival', $route->auto_complete_on_arrival))>
                            <label class="form-check-label" for="autoComplete">{{ __('app.routes.auto_complete_on_arrival') }}</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <strong>{{ __('app.routes.checkpoints') }}</strong>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addCheckpointBtn">
                        <i class="fas fa-plus me-1"></i>{{ __('app.routes.add_checkpoint') }}
                    </button>
                </div>
                <div class="card-body p-0">
                    <p class="small text-muted px-3 pt-2 mb-0">{{ __('app.routes.checkpoint_help') }}</p>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle" id="checkpointsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('app.routes.from') }}</th>
                                    <th>{{ __('app.routes.to') }}</th>
                                    <th>{{ __('app.routes.dist_km') }}</th>
                                    <th>{{ __('app.routes.minutes') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>{{ __('app.map.save') }}
            </button>
        </div>
    </div>
</form>
@can('permission', 'routes.manage')
    @if($route->exists)
        <form action="{{ route($panel . '.routes.destroy', $route) }}" method="post" class="d-inline mt-2"
              onsubmit="return confirm(@json(__('app.routes.delete_confirm')))">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger ms-2">
                <i class="fas fa-trash-alt me-1"></i>{{ __('app.common.delete') }}
            </button>
        </form>
    @endif
@endcan

@push('styles')
<style>
    .route-map-card .pac-container { z-index: 2000 !important; }
    .route-pick-btn.active { font-weight: 600; }
    .route-map-card.sticky-lg-top { top: 1rem; }
    .route-admin-map {
        width: 100%;
        height: clamp(280px, 50vh, 420px);
        min-height: 280px;
        touch-action: manipulation;
    }
    @media (min-width: 992px) {
        .route-admin-map {
            height: 420px;
        }
    }
    @media (max-width: 991.98px) {
        .route-map-card {
            margin-bottom: 0.5rem;
        }
        .route-map-toolbar {
            padding: 0.75rem !important;
        }
    }
    @media (max-width: 575.98px) {
        .route-pick-btn-group {
            flex-direction: column;
            gap: 0.35rem;
        }
        .route-pick-btn-group .btn {
            width: 100%;
            border-radius: 0.375rem !important;
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
        .route-admin-map {
            height: clamp(260px, 45vh, 380px);
        }
        #checkpointsTable {
            font-size: 0.85rem;
        }
        #checkpointsTable .btn-sm {
            padding: 0.35rem 0.5rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
window.ROUTE_ADMIN_CONFIG = {
    googleMapsKey: @json($googleMapsKey ?? config('services.google.maps_key')),
    googleMapsMapId: @json(config('services.google.maps_map_id')),
    checkpoints: @json($checkpointsJson),
    guidedPolyline: @json($guidedPolylineJson),
    guidedDistanceKm: @json(old('guided_distance_km', $route->guided_distance_km)),
    guidedDurationMinutes: @json(old('guided_duration_minutes', $route->guided_duration_minutes)),
    directionsPreviewUrl: @json(route($panel . '.routes.directions-preview')),
    csrfToken: @json(csrf_token()),
    labels: {
        pickStart: @json(__('app.routes.pick_mode_start')),
        pickDest: @json(__('app.routes.pick_mode_dest')),
        pickCheckpoint: @json(__('app.routes.pick_mode_checkpoint')),
        from: @json(__('app.routes.from')),
        to: @json(__('app.routes.to')),
        distKm: @json(__('app.routes.dist_km')),
        minutes: @json(__('app.routes.minutes')),
        pickOnMap: @json(__('app.routes.pick_on_map')),
        remove: @json(__('app.common.delete')),
        polylineLoading: @json(__('app.routes.polyline_loading')),
        polylineLoaded: @json(__('app.routes.polyline_loaded')),
        polylineError: @json(__('app.routes.polyline_error')),
        polylineDisabled: @json(__('app.routes.polyline_disabled')),
    },
};
</script>
@include('partials.google-maps-platform')
<script src="{{ protected_js('vehicle-marker.js') }}"></script>
<script src="{{ protected_js('route-admin.js') }}"></script>
@endpush
