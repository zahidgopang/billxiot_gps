{{-- Same top action bar as /tracking (tc-workspace-nav) for module pages --}}
@php
    $showPanelToggle = $showPanelToggle ?? false;
    $showCollapse = $showCollapse ?? false;
    $isEmbed = request()->boolean('embed');
@endphp
<nav class="tc-workspace-nav tc-workspace-nav--modules" id="tcWorkspaceNav" role="navigation" aria-label="{{ __('app.tracking.hub_nav') }}">
    @if($showPanelToggle)
        <a href="{{ !empty($hubRoutes['live']) && \Illuminate\Support\Facades\Route::has($hubRoutes['live']) ? route($hubRoutes['live']) : url('/tracking') }}"
           class="tc-workspace-nav__panel-btn"
           title="{{ __('app.tracking.tab_objects') }}"
           aria-label="{{ __('app.tracking.tab_objects') }}">
            <i class="fas fa-list-ul"></i>
        </a>
    @endif
    <div class="tc-workspace-nav__track">
        <div class="tc-workspace-nav__links" id="tcIconbarLinks">
            @include('tracking.partials.workspace-nav-links', [
                'hubRoutes' => $hubRoutes ?? [],
                'trackingUi' => $trackingUi ?? [],
                'moduleEmbed' => $isEmbed,
                'activeHubKey' => $activeHubKey ?? null,
            ])
        </div>
    </div>
    @if($showCollapse)
        <div class="tc-workspace-nav__actions">
            <button type="button" class="tc-workspace-nav__collapse-btn" id="tcNavClose"
                    aria-label="{{ __('app.tracking.nav_collapse') }}" title="{{ __('app.tracking.nav_collapse') }}">
                <i class="fas fa-chevron-up"></i>
            </button>
        </div>
    @endif
</nav>
{{-- Modal in page DOM (not only @push) so popup always has a target; script loads after Bootstrap --}}
@unless($isEmbed)
    @include('tracking.partials.module-popup-modal')
    @once('tc-module-popup-script')
        @push('scripts')
            <script src="{{ asset('js/tracking-module-popup.js') }}?v={{ @filemtime(public_path('js/tracking-module-popup.js')) }}"></script>
        @endpush
    @endonce
@endunless
