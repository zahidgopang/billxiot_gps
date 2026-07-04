<?php

namespace App\Services\Tracking;

use App\Models\User;
use App\Services\Authorization\RbacService;

/**
 * Live tracking page UI gates (admin/tracking traccar layout).
 */
class TrackingUiPermissions
{
    public function __construct(
        private RbacService $rbac,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(?User $user): array
    {
        if (! $user) {
            return $this->denyAll();
        }

        if ($this->rbac->isSuperAdmin($user) || $this->rbac->isEndUser($user)) {
            return $this->fullAccess();
        }

        $can = fn (string $permission): bool => $this->rbac->hasPermission($user, $permission);

        if ($can('web.map.live_only')) {
            return $this->liveMapOnly($can);
        }

        $workspace = $can('web.map.workspace');

        $hub = [
            'reports' => $can('web.reports.view'),
            'geofences' => $can('web.geofence.view'),
            'maintenance' => $can('web.tracking.hub.maintenance'),
            'drivers' => $can('web.tracking.hub.drivers'),
            'commands' => $can('web.map.toolbar.commands'),
            'tasks' => $can('web.tracking.hub.tasks'),
            'notifications' => $can('web.tracking.hub.notifications'),
            'settings' => $can('web.settings.view'),
        ];

        $sidebarTabs = [
            'objects' => $can('web.map.sidebar.vehicle_list'),
            'events' => $can('web.map.sidebar.events'),
            'places' => $can('web.map.sidebar.places'),
            'history' => $can('web.map.sidebar.history'),
        ];

        $mapControls = [
            'zoom' => true,
            'fit' => $workspace && $can('web.map.sidebar.vehicle_list'),
            'follow' => $workspace && $can('web.map.sidebar.vehicle_list'),
            'refresh' => $can('web.map.toolbar.refresh') || $can('web.map.auto_refresh'),
            'traffic' => $can('web.map.toolbar.traffic'),
            'layers' => $can('web.map.toolbar.layers'),
            'capture' => $workspace && $can('web.map.toolbar.fullscreen'),
        ];

        return [
            'workspace' => $workspace,
            'live_only' => false,
            'map_only' => ! $workspace,
            'iconbar' => $workspace,
            'hub' => $hub,
            'alert_controls' => $workspace && $can('general.notifications.view'),
            'sidebar' => $workspace && count(array_filter($sidebarTabs)) > 0,
            'sidebar_tabs' => $sidebarTabs,
            'vehicle_list' => $can('web.map.sidebar.vehicle_list'),
            'vehicle_search' => $can('web.map.search_vehicles'),
            'vehicle_footer' => $workspace && ($can('web.map.sidebar.statistics') || $can('web.map.sidebar.vehicle_list')),
            'map_controls' => $mapControls,
            'show_map_controls' => count(array_filter($mapControls)) > 0,
            'route_progress' => $can('web.map.toolbar.route_progress'),
            'polyline' => $can('web.map.toolbar.polyline'),
            'driver' => $can('web.map.vehicle.driver'),
            'panel_toggle' => $workspace && count(array_filter($sidebarTabs)) > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fullAccess(): array
    {
        $hub = [
            'reports' => true,
            'geofences' => true,
            'maintenance' => true,
            'drivers' => true,
            'commands' => true,
            'tasks' => true,
            'notifications' => true,
            'settings' => true,
        ];

        $sidebarTabs = [
            'objects' => true,
            'events' => true,
            'places' => true,
            'history' => true,
        ];

        $mapControls = [
            'zoom' => true,
            'fit' => true,
            'follow' => true,
            'refresh' => true,
            'traffic' => true,
            'layers' => true,
            'capture' => true,
        ];

        return [
            'workspace' => true,
            'live_only' => false,
            'map_only' => false,
            'iconbar' => true,
            'hub' => $hub,
            'alert_controls' => true,
            'sidebar' => true,
            'sidebar_tabs' => $sidebarTabs,
            'vehicle_list' => true,
            'vehicle_search' => true,
            'vehicle_footer' => true,
            'map_controls' => $mapControls,
            'show_map_controls' => true,
            'route_progress' => true,
            'polyline' => true,
            'driver' => true,
            'panel_toggle' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function denyAll(): array
    {
        $falseHub = array_fill_keys([
            'reports', 'geofences', 'maintenance', 'drivers', 'commands', 'tasks', 'notifications', 'settings',
        ], false);

        $falseTabs = array_fill_keys(['objects', 'events', 'places', 'history'], false);

        return [
            'workspace' => false,
            'live_only' => false,
            'map_only' => true,
            'iconbar' => false,
            'hub' => $falseHub,
            'alert_controls' => false,
            'sidebar' => false,
            'sidebar_tabs' => $falseTabs,
            'vehicle_list' => false,
            'vehicle_search' => false,
            'vehicle_footer' => false,
            'map_controls' => ['zoom' => true, 'fit' => false, 'follow' => false, 'refresh' => false, 'traffic' => false, 'layers' => false, 'capture' => false],
            'show_map_controls' => true,
            'route_progress' => false,
            'polyline' => false,
            'driver' => false,
            'panel_toggle' => false,
        ];
    }

    /**
     * Map-only layout; individual map features still respect granular permissions.
     *
     * @param  callable(string): bool  $can
     * @return array<string, mixed>
     */
    private function liveMapOnly(callable $can): array
    {
        $falseHub = array_fill_keys([
            'reports', 'geofences', 'maintenance', 'drivers', 'commands', 'tasks', 'notifications', 'settings',
        ], false);

        $mapControls = [
            'zoom' => true,
            'fit' => false,
            'follow' => false,
            'refresh' => $can('web.map.toolbar.refresh') || $can('web.map.auto_refresh'),
            'traffic' => $can('web.map.toolbar.traffic'),
            'layers' => $can('web.map.toolbar.layers'),
            'capture' => $can('web.map.toolbar.fullscreen'),
        ];

        return [
            'workspace' => false,
            'live_only' => true,
            'map_only' => true,
            'iconbar' => false,
            'hub' => $falseHub,
            'alert_controls' => false,
            'sidebar' => false,
            'sidebar_tabs' => array_fill_keys(['objects', 'events', 'places', 'history'], false),
            'vehicle_list' => false,
            'vehicle_search' => false,
            'vehicle_footer' => false,
            'map_controls' => $mapControls,
            'show_map_controls' => count(array_filter($mapControls)) > 0,
            'route_progress' => $can('web.map.toolbar.route_progress'),
            'polyline' => $can('web.map.toolbar.polyline'),
            'driver' => $can('web.map.vehicle.driver'),
            'panel_toggle' => false,
        ];
    }
}
