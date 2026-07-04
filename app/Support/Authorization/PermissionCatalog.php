<?php

namespace App\Support\Authorization;

/**
 * Developer registry for seeding the permissions table.
 * Run: php artisan permissions:sync
 *
 * The Permission Management UI reads from the database, not this file.
 */
final class PermissionCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        $items = [];

        $add = static function (
            string $key,
            string $module,
            string $category,
            string $displayName,
            string $description,
            string $platform = 'both',
            ?string $groupKey = null,
            ?string $groupLabel = null,
            ?string $icon = null,
            int $sort = 0,
        ) use (&$items): void {
            $items[] = [
                'key' => $key,
                'module' => $module,
                'category' => $category,
                'group_key' => $groupKey,
                'group_label' => $groupLabel,
                'display_name' => $displayName,
                'description' => $description,
                'platform' => $platform,
                'icon' => $icon,
                'sort_order' => $sort,
            ];
        };

        // ── General ──────────────────────────────────────────────
        $add('general.dashboard.view', 'general', 'Dashboard', 'View Dashboard', 'Access the main dashboard and summary widgets.', 'both', null, null, 'fa-gauge-high', 1);
        $add('general.profile.view', 'general', 'Profile', 'View Profile', 'View own profile information.', 'both', null, null, 'fa-user', 2);
        $add('general.profile.edit', 'general', 'Profile', 'Edit Profile', 'Update name, email, phone, and password.', 'both', null, null, 'fa-user-pen', 3);
        $add('general.notifications.view', 'general', 'Notifications', 'View Notifications', 'See in-app notification feed and alerts.', 'both', null, null, 'fa-bell', 4);
        $add('general.notifications.manage', 'general', 'Notifications', 'Manage Notification Preferences', 'Configure push, email, and WhatsApp notification channels.', 'both', null, null, 'fa-sliders', 5);

        // ── Legacy / panel (administration backbone) ─────────────
        $add('panel.access', 'admin', 'Panel Access', 'Access Admin Panel', 'Sign in to the web administration panel.', 'web', null, null, 'fa-door-open', 1);
        $add('permissions.manage', 'admin', 'Permissions', 'Manage Permissions', 'Configure role permissions and permission templates.', 'web', null, null, 'fa-shield-halved', 2);

        // ── Web: Live Map ────────────────────────────────────────
        $cat = 'Live Map';
        $add('maps.view', 'web', $cat, 'Open Live Map', 'Access live GPS tracking maps for assigned vehicles.', 'web', 'general', 'General', 'fa-map-location-dot', 1);
        $add('maps.view_all', 'web', $cat, 'View All Devices on Map', 'View live maps for all devices in tenant scope.', 'web', 'general', 'General', 'fa-earth-americas', 2);
        $add('web.map.menu', 'web', $cat, 'View Live Tracking Menu', 'Show Live Tracking in the web navigation menu.', 'web', 'general', 'General', 'fa-bars', 3);
        $add('web.map.open', 'web', $cat, 'Open Live Map Screen', 'Launch the full-screen live tracking map.', 'web', 'general', 'General', 'fa-map', 4);
        $add('web.map.search_vehicles', 'web', $cat, 'Search Vehicles', 'Search and filter vehicles on the map.', 'web', 'general', 'General', 'fa-magnifying-glass', 5);
        $add('web.map.auto_refresh', 'web', $cat, 'Auto Refresh', 'Automatically poll for live GPS updates.', 'web', 'general', 'General', 'fa-rotate', 6);
        $add('web.map.workspace', 'web', $cat, 'Tracking Workspace UI', 'Show the vehicle list, hub toolbar, and map tools on the live tracking page. Without this, users see the map only.', 'web', 'general', 'General', 'fa-table-columns', 7);
        $add('web.map.live_only', 'web', $cat, 'Only Access Live Map', 'Restrict the user to the live map only — no sidebar, hub toolbar, or workspace panels. Map overlays still follow other live map permissions.', 'web', 'general', 'General', 'fa-map-marked-alt', 8);

        $add('web.tracking.hub.maintenance', 'web', $cat, 'Hub: Maintenance', 'Show Maintenance in the tracking top toolbar.', 'web', 'hub', 'Hub Navigation', 'fa-wrench', 50);
        $add('web.tracking.hub.drivers', 'web', $cat, 'Hub: Drivers', 'Show Drivers in the tracking top toolbar.', 'web', 'hub', 'Hub Navigation', 'fa-id-card', 51);
        $add('web.tracking.hub.tasks', 'web', $cat, 'Hub: Tasks', 'Show Tasks in the tracking top toolbar.', 'web', 'hub', 'Hub Navigation', 'fa-tasks', 52);
        $add('web.tracking.hub.notifications', 'web', $cat, 'Hub: Notification Settings', 'Show notification settings in the tracking top toolbar.', 'web', 'hub', 'Hub Navigation', 'fa-bell', 53);

        $add('web.map.toolbar.satellite', 'web', $cat, 'Satellite View', 'Switch map to satellite imagery.', 'web', 'toolbar', 'Toolbar', 'fa-satellite', 10);
        $add('web.map.toolbar.traffic', 'web', $cat, 'Traffic Layer', 'Show live traffic overlay on the map.', 'web', 'toolbar', 'Toolbar', 'fa-road', 11);
        $add('web.map.toolbar.street_view', 'web', $cat, 'Street View', 'Open Google Street View at vehicle location.', 'web', 'toolbar', 'Toolbar', 'fa-street-view', 12);
        $add('web.map.toolbar.playback', 'web', $cat, 'Route Playback', 'Replay historical route on the map.', 'web', 'toolbar', 'Toolbar', 'fa-clock-rotate-left', 13);
        $add('web.map.toolbar.commands', 'web', $cat, 'Send Commands', 'Send GPS device commands from the map.', 'web', 'toolbar', 'Toolbar', 'fa-terminal', 14);
        $add('web.map.toolbar.route_progress', 'web', $cat, 'Route Progress Bar', 'Shows the live route progress bar on the tracking screen.', 'web', 'toolbar', 'Toolbar', 'fa-bus', 15);
        $add('web.map.toolbar.polyline', 'web', $cat, 'Route Polyline', 'Display assigned route polyline on the map.', 'web', 'toolbar', 'Toolbar', 'fa-route', 16);
        $add('web.map.toolbar.geofence', 'web', $cat, 'Geofence Tools', 'Draw and manage geofences on the map.', 'web', 'toolbar', 'Toolbar', 'fa-draw-polygon', 17);
        $add('web.map.toolbar.measure', 'web', $cat, 'Measurement Tool', 'Measure distances on the map.', 'web', 'toolbar', 'Toolbar', 'fa-ruler', 18);
        $add('web.map.toolbar.refresh', 'web', $cat, 'Refresh Map', 'Manually refresh live map data.', 'web', 'toolbar', 'Toolbar', 'fa-arrows-rotate', 19);
        $add('web.map.toolbar.fullscreen', 'web', $cat, 'Full Screen', 'Expand map to full screen mode.', 'web', 'toolbar', 'Toolbar', 'fa-expand', 20);
        $add('web.map.toolbar.layers', 'web', $cat, 'Map Layers', 'Toggle map layers and overlays.', 'web', 'toolbar', 'Toolbar', 'fa-layer-group', 21);

        $add('web.map.sidebar.vehicle_list', 'web', $cat, 'Vehicle List Sidebar', 'Show vehicle list in the map sidebar.', 'web', 'sidebar', 'Sidebar', 'fa-list', 30);
        $add('web.map.sidebar.statistics', 'web', $cat, 'Vehicle Statistics', 'View trip statistics in the sidebar.', 'web', 'sidebar', 'Sidebar', 'fa-chart-line', 31);
        $add('web.map.sidebar.events', 'web', $cat, 'Events Panel', 'View vehicle events and alerts on the map.', 'web', 'sidebar', 'Sidebar', 'fa-bell', 32);
        $add('web.map.sidebar.places', 'web', $cat, 'Places', 'Manage saved places on the map.', 'web', 'sidebar', 'Sidebar', 'fa-location-dot', 33);
        $add('web.map.sidebar.history', 'web', $cat, 'History Panel', 'Open route history from the map sidebar.', 'web', 'sidebar', 'Sidebar', 'fa-clock', 34);

        $add('web.map.vehicle.speed', 'web', $cat, 'Vehicle Speed', 'Display live speed on the vehicle panel.', 'web', 'vehicle', 'Vehicle Info', 'fa-gauge-high', 40);
        $add('web.map.vehicle.address', 'web', $cat, 'Vehicle Address', 'Show reverse-geocoded address.', 'web', 'vehicle', 'Vehicle Info', 'fa-map-pin', 41);
        $add('web.map.vehicle.driver', 'web', $cat, 'Driver Info', 'Show assigned driver name and contact.', 'web', 'vehicle', 'Vehicle Info', 'fa-id-card', 42);
        $add('web.map.vehicle.fuel', 'web', $cat, 'Fuel Level', 'Display fuel sensor readings.', 'web', 'vehicle', 'Vehicle Info', 'fa-gas-pump', 43);
        $add('web.map.vehicle.battery', 'web', $cat, 'Battery Level', 'Display device battery status.', 'web', 'vehicle', 'Vehicle Info', 'fa-battery-half', 44);
        $add('web.map.vehicle.ignition', 'web', $cat, 'Ignition Status', 'Show ignition on/off status.', 'web', 'vehicle', 'Vehicle Info', 'fa-key', 45);
        $add('web.map.vehicle.sensors', 'web', $cat, 'Sensors', 'View additional vehicle sensors.', 'web', 'vehicle', 'Vehicle Info', 'fa-microchip', 46);
        $add('web.map.vehicle.device_status', 'web', $cat, 'Device Status', 'Show online/offline and connectivity status.', 'web', 'vehicle', 'Vehicle Info', 'fa-signal', 47);

        // ── Web: Vehicles ────────────────────────────────────────
        $add('devices.view', 'web', 'Vehicles', 'View Vehicles', 'View vehicle/device list and details.', 'web', null, null, 'fa-car', 1);
        $add('devices.manage', 'web', 'Vehicles', 'Manage Vehicles', 'Create, edit, and configure vehicles.', 'web', null, null, 'fa-pen-to-square', 2);
        $add('web.vehicles.add', 'web', 'Vehicles', 'Add Vehicle', 'Register new GPS devices and vehicles.', 'web', null, null, 'fa-plus', 3);
        $add('web.vehicles.edit', 'web', 'Vehicles', 'Edit Vehicle', 'Modify vehicle settings and metadata.', 'web', null, null, 'fa-edit', 4);
        $add('web.vehicles.delete', 'web', 'Vehicles', 'Delete Vehicle', 'Remove vehicles from the system.', 'web', null, null, 'fa-trash', 5);
        $add('web.vehicles.assign_route', 'web', 'Vehicles', 'Assign Route', 'Assign a planned route to a vehicle.', 'web', null, null, 'fa-route', 6);
        $add('devices.manage_map_icons', 'web', 'Vehicles', 'Change Vehicle Icon', 'Select vehicle type and marker style.', 'web', null, null, 'fa-icons', 7);
        $add('web.vehicles.upload_custom_icon', 'web', 'Vehicles', 'Upload Custom Icon', 'Upload a custom map marker image.', 'web', null, null, 'fa-upload', 8);
        $add('web.vehicles.change_icon_size', 'web', 'Vehicles', 'Change Icon Size', 'Adjust map marker size scale.', 'web', null, null, 'fa-up-right-and-down-left-from-center', 9);
        $add('web.vehicles.view_details', 'web', 'Vehicles', 'View Vehicle Details', 'Open full vehicle detail pages.', 'web', null, null, 'fa-circle-info', 10);
        $add('web.vehicles.view_sensors', 'web', 'Vehicles', 'View Sensors', 'Access sensor telemetry for a vehicle.', 'web', null, null, 'fa-microchip', 11);
        $add('web.vehicles.view_fuel', 'web', 'Vehicles', 'View Fuel', 'Access fuel reports and live fuel data.', 'web', null, null, 'fa-gas-pump', 12);
        $add('web.vehicles.view_temperature', 'web', 'Vehicles', 'View Temperature', 'Access temperature sensor data.', 'web', null, null, 'fa-temperature-half', 13);
        $add('web.vehicles.send_commands', 'web', 'Vehicles', 'Send Commands', 'Send remote commands to GPS devices.', 'web', null, null, 'fa-terminal', 14);
        $add('web.vehicles.immobilizer', 'web', 'Vehicles', 'Immobilizer', 'Control vehicle immobilizer output.', 'web', null, null, 'fa-lock', 15);
        $add('web.vehicles.share', 'web', 'Vehicles', 'Share Vehicle', 'Generate share links for vehicle tracking.', 'web', null, null, 'fa-share-nodes', 16);

        // ── Web: Trips & Routes ──────────────────────────────────
        $add('routes.view', 'web', 'Trips & Routes', 'View Routes', 'View transport route definitions.', 'web', null, null, 'fa-route', 1);
        $add('routes.manage', 'web', 'Trips & Routes', 'Manage Routes', 'Create and edit routes and checkpoints.', 'web', null, null, 'fa-map-signs', 2);
        $add('web.trips.view', 'web', 'Trips & Routes', 'View Trips', 'View active and completed trip logs.', 'web', null, null, 'fa-road', 3);
        $add('web.trips.complete', 'web', 'Trips & Routes', 'Complete Trip', 'Manually mark a trip as completed.', 'web', null, null, 'fa-flag-checkered', 4);

        // ── Web: Reports ───────────────────────────────────────
        $add('web.reports.view', 'web', 'Reports', 'View Reports', 'Access the reports section.', 'web', null, null, 'fa-chart-bar', 1);
        $add('web.reports.export_pdf', 'web', 'Reports', 'Export PDF', 'Download reports as PDF files.', 'web', null, null, 'fa-file-pdf', 2);
        $add('web.reports.export_excel', 'web', 'Reports', 'Export Excel', 'Download reports as Excel spreadsheets.', 'web', null, null, 'fa-file-excel', 3);
        $add('web.reports.trips', 'web', 'Reports', 'Trip Reports', 'View trip summary and analytics reports.', 'web', null, null, 'fa-route', 4);
        $add('web.reports.fuel', 'web', 'Reports', 'Fuel Reports', 'View fuel consumption reports.', 'web', null, null, 'fa-gas-pump', 5);
        $add('web.reports.drivers', 'web', 'Reports', 'Driver Reports', 'View driver activity reports.', 'web', null, null, 'fa-id-card', 6);
        $add('web.reports.history', 'web', 'Reports', 'History Reports', 'View GPS history and playback reports.', 'web', null, null, 'fa-clock-rotate-left', 7);
        $add('billing.view', 'web', 'Reports', 'View Billing Reports', 'View invoices and profit/loss reports.', 'web', null, null, 'fa-file-invoice-dollar', 8);

        // ── Web: History & Events ────────────────────────────────
        $add('web.history.view', 'web', 'History', 'View History', 'Access GPS history and route playback.', 'web', null, null, 'fa-clock-rotate-left', 1);
        $add('web.events.view', 'web', 'Events', 'View Events', 'View vehicle events and alert history.', 'web', null, null, 'fa-bell', 1);
        $add('web.geofence.view', 'web', 'Geofence', 'View Geofences', 'View geofence definitions.', 'web', null, null, 'fa-draw-polygon', 1);
        $add('web.geofence.manage', 'web', 'Geofence', 'Manage Geofences', 'Create, edit, and delete geofences.', 'web', null, null, 'fa-pen-ruler', 2);
        $add('web.places.view', 'web', 'Places', 'View Places', 'View saved places and POIs.', 'web', null, null, 'fa-location-dot', 1);
        $add('web.places.manage', 'web', 'Places', 'Manage Places', 'Create and edit saved places.', 'web', null, null, 'fa-map-pin', 2);
        $add('web.settings.view', 'web', 'Settings', 'View Settings', 'Access application settings pages.', 'web', null, null, 'fa-gear', 1);

        // ── Mobile navigation ────────────────────────────────────
        $m = 'mobile';
        $add('mobile.nav.home', $m, 'Home', 'Home Tab Visible', 'Show Home in mobile bottom navigation.', 'mobile', null, null, 'fa-house', 1);
        $add('mobile.nav.live_map', $m, 'Live Map', 'Live Map Tab Visible', 'Show Live Map in mobile navigation.', 'mobile', null, null, 'fa-map-location-dot', 2);
        $add('mobile.nav.history', $m, 'History', 'History Tab Visible', 'Show History in mobile navigation.', 'mobile', null, null, 'fa-clock-rotate-left', 3);
        $add('mobile.nav.trips', $m, 'Trips', 'Trips Tab Visible', 'Show Trips in mobile navigation.', 'mobile', null, null, 'fa-route', 4);
        $add('mobile.nav.notifications', $m, 'Notifications', 'Notifications Tab Visible', 'Show Notifications in mobile navigation.', 'mobile', null, null, 'fa-bell', 5);
        $add('mobile.nav.reports', $m, 'Reports', 'Reports Tab Visible', 'Show Reports in mobile navigation.', 'mobile', null, null, 'fa-chart-bar', 6);
        $add('mobile.nav.commands', $m, 'Commands', 'Commands Tab Visible', 'Show Commands in mobile navigation.', 'mobile', null, null, 'fa-terminal', 7);
        $add('mobile.nav.profile', $m, 'Profile', 'Profile Tab Visible', 'Show Profile in mobile navigation.', 'mobile', null, null, 'fa-user', 8);
        $add('mobile.nav.settings', $m, 'Settings', 'Settings Tab Visible', 'Show Settings in mobile navigation.', 'mobile', null, null, 'fa-gear', 9);

        // ── Mobile live map features ─────────────────────────────
        $add('mobile.map.open', $m, 'Live Map', 'Open Live Map', 'Access the mobile live tracking screen.', 'mobile', 'general', 'General', 'fa-map', 10);
        $add('mobile.map.route_progress', $m, 'Live Map', 'Route Progress', 'Show route progress bar on mobile live map.', 'mobile', 'general', 'General', 'fa-bus', 11);
        $add('mobile.map.polyline', $m, 'Live Map', 'Route Polyline', 'Show assigned route polyline on mobile map.', 'mobile', 'general', 'General', 'fa-route', 12);
        $add('mobile.map.custom_icon', $m, 'Live Map', 'Custom Vehicle Icon', 'Display custom uploaded vehicle icons.', 'mobile', 'general', 'General', 'fa-icons', 13);

        // ── Administration ───────────────────────────────────────
        $add('users.view', 'admin', 'Users', 'View Users', 'View user accounts in the admin panel.', 'web', null, null, 'fa-users', 1);
        $add('users.manage', 'admin', 'Users', 'Manage Users', 'Create, edit, and deactivate user accounts.', 'web', null, null, 'fa-user-gear', 2);
        $add('clients.view', 'admin', 'Clients', 'View Clients', 'View client companies.', 'web', null, null, 'fa-building', 1);
        $add('clients.manage', 'admin', 'Clients', 'Manage Clients', 'Create and edit client companies.', 'web', null, null, 'fa-building-circle-check', 2);
        $add('subscriptions.view', 'admin', 'Subscriptions', 'View Subscriptions', 'View device subscriptions.', 'web', null, null, 'fa-credit-card', 1);
        $add('subscriptions.manage', 'admin', 'Subscriptions', 'Manage Subscriptions', 'Create and renew subscriptions.', 'web', null, null, 'fa-file-contract', 2);
        $add('billing.manage', 'admin', 'Billing', 'Manage Billing', 'Manage plans, invoices, and payments.', 'web', null, null, 'fa-money-bill-wave', 1);
        $add('stock.view', 'admin', 'Device Stock', 'View Device Stock', 'View GPS device inventory.', 'web', null, null, 'fa-boxes-stacked', 1);
        $add('stock.manage', 'admin', 'Device Stock', 'Manage Device Stock', 'Add, sell, and repair stock devices.', 'web', null, null, 'fa-warehouse', 2);
        $add('activity.view', 'admin', 'Audit Logs', 'View Audit Logs', 'View system activity and audit trail.', 'web', null, null, 'fa-clipboard-list', 1);

        // ── User preferences (not RBAC gates — informational grouping) ──
        $add('pref.auto_open_live_map', 'general', 'User Preferences', 'Auto Open Live Map After Login', 'Automatically open the live map after signing in.', 'both', 'preferences', 'User Preferences', 'fa-map', 50);
        $add('pref.remember_last_vehicle', 'general', 'User Preferences', 'Remember Last Selected Vehicle', 'Restore the last viewed vehicle on the map.', 'both', 'preferences', 'User Preferences', 'fa-bookmark', 51);
        $add('pref.push_notifications', 'general', 'User Preferences', 'Receive Push Notifications', 'Enable mobile push notifications.', 'mobile', 'preferences', 'User Preferences', 'fa-mobile-screen', 52);
        $add('pref.email_notifications', 'general', 'User Preferences', 'Receive Email Notifications', 'Enable email alert notifications.', 'both', 'preferences', 'User Preferences', 'fa-envelope', 53);
        $add('pref.whatsapp_notifications', 'general', 'User Preferences', 'Receive WhatsApp Notifications', 'Enable WhatsApp alert notifications.', 'both', 'preferences', 'User Preferences', 'fa-brands fa-whatsapp', 54);

        return $items;
    }

    /**
     * Default role → permission keys (used when seeding role_permissions).
     *
     * @return array<string, list<string>>
     */
    public static function defaultRoleGrants(): array
    {
        $allKeys = array_column(self::definitions(), 'key');

        $adminKeys = array_filter($allKeys, fn ($k) => ! str_starts_with($k, 'permissions.manage'));

        $clientKeys = array_values(array_filter($adminKeys, fn ($k) => ! in_array($k, [
            'clients.view', 'clients.manage', 'stock.view', 'stock.manage', 'billing.manage', 'permissions.manage',
        ], true)));

        $endUserKeys = array_values(array_filter($allKeys, fn ($k) => str_starts_with($k, 'general.')
            || str_starts_with($k, 'mobile.')
            || str_starts_with($k, 'maps.view')
            || str_starts_with($k, 'web.map.')
            || str_starts_with($k, 'web.history.')
            || str_starts_with($k, 'web.events.')
            || str_starts_with($k, 'web.trips.view')
            || str_starts_with($k, 'pref.')
        ));

        return [
            'super_admin' => ['*'],
            'admin' => array_values(array_filter($adminKeys, fn ($k) => ! str_starts_with($k, 'pref.'))),
            'client' => array_values(array_filter($clientKeys, fn ($k) => ! str_starts_with($k, 'pref.'))),
            'user' => $endUserKeys,
        ];
    }

    /**
     * @return list<array{slug: string, name: string, description: string, permission_keys: list<string>}>
     */
    public static function templates(): array
    {
        $all = array_column(self::definitions(), 'key');
        $webMap = fn ($prefix) => array_values(array_filter($all, fn ($k) => str_starts_with($k, $prefix)));

        return [
            [
                'slug' => 'select_all',
                'name' => 'Select All',
                'description' => 'Grant every permission in the catalog.',
                'permission_keys' => $all,
                'sort_order' => 1,
            ],
            [
                'slug' => 'read_only',
                'name' => 'Read Only',
                'description' => 'View-only access across modules.',
                'permission_keys' => array_values(array_filter($all, fn ($k) => str_contains($k, '.view') || $k === 'maps.view' || str_starts_with($k, 'mobile.nav.'))),
                'sort_order' => 2,
            ],
            [
                'slug' => 'tracking_only',
                'name' => 'Tracking Only',
                'description' => 'Live map and history without admin features.',
                'permission_keys' => array_merge(
                    ['maps.view', 'web.map.open', 'web.map.auto_refresh'],
                    $webMap('web.map.'),
                    $webMap('mobile.map.'),
                    $webMap('mobile.nav.'),
                    ['web.history.view', 'web.events.view', 'general.dashboard.view', 'general.profile.view'],
                ),
                'sort_order' => 3,
            ],
            [
                'slug' => 'fleet_manager',
                'name' => 'Fleet Manager',
                'description' => 'Full fleet operations without billing or stock.',
                'permission_keys' => array_values(array_filter($all, fn ($k) => ! in_array($k, [
                    'billing.manage', 'stock.view', 'stock.manage', 'permissions.manage', 'clients.manage',
                ], true) && ! str_starts_with($k, 'pref.'))),
                'sort_order' => 4,
            ],
            [
                'slug' => 'operations',
                'name' => 'Operations',
                'description' => 'Routes, trips, vehicles, and live tracking.',
                'permission_keys' => array_merge(
                    ['devices.view', 'devices.manage', 'routes.view', 'routes.manage', 'maps.view'],
                    $webMap('web.map.'),
                    $webMap('web.vehicles.'),
                    $webMap('web.trips.'),
                    $webMap('mobile.nav.'),
                    $webMap('mobile.map.'),
                ),
                'sort_order' => 5,
            ],
        ];
    }
}
