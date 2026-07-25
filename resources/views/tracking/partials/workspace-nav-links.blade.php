{{-- Shared tracking module links — same items as /tracking workspace nav --}}
@php
    use Illuminate\Support\Facades\Route;

    $hub = $hubRoutes ?? [];
    $hubPerms = $trackingUi['hub'] ?? ($hubPerms ?? []);
    $activeKey = $activeHubKey ?? null;

    if ($activeKey === null) {
        if (request()->routeIs('*tracking.index')) {
            $activeKey = 'live';
        } elseif (request()->routeIs('*tracking.history*')) {
            $activeKey = 'history';
        } elseif (request()->routeIs('*tracking.reports.*')) {
            $activeKey = 'reports';
        } elseif (request()->routeIs('*tracking.odometer*')) {
            $activeKey = 'odometer';
        } elseif (request()->routeIs('*tracking.events.*')) {
            $activeKey = 'events';
        } elseif (request()->routeIs('*tracking.geofences.*')) {
            $activeKey = 'geofences';
        } elseif (request()->routeIs('*tracking.maintenance.*')) {
            $activeKey = 'maintenance';
        } elseif (request()->routeIs('*tracking.drivers.*')) {
            $activeKey = 'drivers';
        } elseif (request()->routeIs('*tracking.commands.*')) {
            $activeKey = 'commands';
        } elseif (request()->routeIs('*tracking.tasks.*')) {
            $activeKey = 'tasks';
        } elseif (request()->routeIs('*tracking.notifications.*')) {
            $activeKey = 'notifications';
        } elseif (request()->routeIs('*tracking.settings.*')) {
            $activeKey = 'settings';
        }
    }

    $iconLinks = [
        ['key' => 'live', 'icon' => 'fas fa-location-arrow', 'route' => $hub['live'] ?? null, 'label' => __('app.tracking.live_link'), 'hub' => true],
        ['key' => 'reports', 'icon' => 'fas fa-chart-bar', 'route' => $hub['reports'] ?? null, 'label' => __('app.tracking.reports_nav')],
        ['key' => 'odometer', 'icon' => 'fas fa-tachometer-alt', 'route' => $hub['odometer'] ?? null, 'label' => __('app.tracking.odometer_nav')],
        ['key' => 'geofences', 'icon' => 'fas fa-draw-polygon', 'route' => $hub['geofences'] ?? null, 'label' => __('app.tracking.geofences_nav')],
        ['key' => 'maintenance', 'icon' => 'fas fa-wrench', 'route' => $hub['maintenance'] ?? null, 'label' => __('app.tracking.maintenance_nav')],
        ['key' => 'drivers', 'icon' => 'fas fa-id-card', 'route' => $hub['drivers'] ?? null, 'label' => __('app.tracking.drivers_nav')],
        ['key' => 'commands', 'icon' => 'fas fa-terminal', 'route' => $hub['commands'] ?? null, 'label' => __('app.tracking.commands_nav')],
        ['key' => 'tasks', 'icon' => 'fas fa-tasks', 'route' => $hub['tasks'] ?? null, 'label' => __('app.tracking.tasks_nav')],
        ['key' => 'notifications', 'icon' => 'fas fa-bell', 'route' => $hub['notifications'] ?? null, 'label' => __('app.tracking.notifications_nav')],
        ['key' => 'settings', 'icon' => 'fas fa-sliders-h', 'route' => $hub['settings'] ?? null, 'label' => __('app.tracking.settings_nav')],
    ];

    $moduleEmbed = ! empty($moduleEmbed);
@endphp
@foreach($iconLinks as $link)
    @php
        $hubKey = $link['key'];
        $showHub = ! empty($link['hub']) || ! empty($hubPerms[$hubKey]);
        $isActive = $activeKey === $hubKey;
    @endphp
    @if($showHub && $link['route'] && Route::has($link['route']))
        @php
            // Live always navigates to the map page; other modules open in the same popup as /tracking.
            // Active item stays a plain link. moduleEmbed (iframe) keeps plain links so nested popups never appear.
            $usePlainLink = $moduleEmbed || $isActive || $hubKey === 'live';
        @endphp
        @if($usePlainLink)
            <a href="{{ route($link['route']) }}"
               class="tc-nav-pill{{ $isActive ? ' active' : '' }}"
               title="{{ $link['label'] }}"
               aria-label="{{ $link['label'] }}"
               @if($isActive) aria-current="page" @endif>
                <i class="{{ $link['icon'] }}" aria-hidden="true"></i>
                <span>{{ $link['label'] }}</span>
            </a>
        @else
            <a href="{{ route($link['route']) }}"
               class="tc-nav-pill"
               data-tc-module="{{ route($link['route']) }}"
               data-tc-module-title="{{ $link['label'] }}"
               data-tc-module-icon="{{ $link['icon'] }}"
               title="{{ $link['label'] }}"
               aria-label="{{ $link['label'] }}">
                <i class="{{ $link['icon'] }}" aria-hidden="true"></i>
                <span>{{ $link['label'] }}</span>
            </a>
        @endif
    @endif
@endforeach
