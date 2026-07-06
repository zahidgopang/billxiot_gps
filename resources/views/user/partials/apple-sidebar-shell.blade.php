<aside class="filter-panel user-dashboard-sidebar" id="filterPanel" role="navigation" aria-label="{{ __('app.user.nav.menu') }}" dir="{{ $htmlDir ?? 'ltr' }}">
    <div class="filter-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">{{ __('app.user.nav.menu') }}</h5>
        <button type="button" class="close-sidebar" id="closeSidebar" aria-label="{{ __('app.map.close_panel') }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    @include('user.partials.apple-sidebar-nav')
    <div class="mt-4 pt-3 ud-sidebar-logout" style="border-top: 1px solid var(--apple-border);">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="ud-btn ud-btn--secondary w-100" style="color: var(--apple-red);">
                {{ __('app.common.logout') }}
            </button>
        </form>
    </div>
</aside>
