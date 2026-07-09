<?php

use App\Enums\AppRole;

return [

    /*
    |--------------------------------------------------------------------------
    | Application roles (stored in tc_users.attributes.laravel_role)
    |--------------------------------------------------------------------------
    */
    'roles' => [
        AppRole::SuperAdmin->value => [
            'label' => 'Super Admin',
            'panel' => 'admin',
            'permissions' => ['*'],
        ],
        AppRole::Admin->value => [
            'label' => 'Admin',
            'panel' => 'admin',
            'permissions' => [
                'panel.access',
                'clients.view',
                'clients.manage',
                'users.view',
                'users.manage',
                'devices.view',
                'devices.manage',
                'devices.manage_map_icons',
                'routes.view',
                'routes.manage',
                'maps.view',
                'web.map.workspace',
                'web.map.toolbar.geofence',
                'web.geofence.view',
                'web.geofence.manage',
                'stock.manage',
                'subscriptions.view',
                'subscriptions.manage',
                'billing.view',
                'billing.manage',
                'activity.view',
            ],
        ],
        AppRole::Client->value => [
            'label' => 'Client',
            'panel' => 'client',
            'permissions' => [
                'panel.access',
                'users.view',
                'users.manage',
                'devices.view',
                'devices.manage',
                'devices.manage_map_icons',
                'routes.view',
                'routes.manage',
                'maps.view',
                'web.map.workspace',
                'web.map.toolbar.geofence',
                'web.geofence.view',
                'web.geofence.manage',
                'web.history.view',
                'web.events.view',
                'subscriptions.view',
                'subscriptions.manage',
                'billing.view',
                'activity.view',
            ],
        ],
        AppRole::EndUser->value => [
            'label' => 'End User',
            'panel' => 'user',
            // Baseline keys are always granted in RbacService for end users.
            'permissions' => [
                'maps.view',
                'web.map.open',
                'web.map.workspace',
                'web.map.auto_refresh',
                'web.map.sidebar.vehicle_list',
                'web.map.toolbar.commands',
                'web.reports.view',
                'web.history.view',
                'web.events.view',
                'web.vehicles.send_commands',
                'mobile.nav.live_map',
                'mobile.nav.commands',
                'mobile.nav.reports',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional per-user permission grants (stored in laravel_permissions JSON)
    |--------------------------------------------------------------------------
    */
    'grantable_permissions' => [
        'maps.view' => 'Track user devices on the live map',
        'maps.view_all' => 'View maps for all devices in scope (admin)',
        'clients.manage' => 'Create and edit client companies',
        'users.manage' => 'Create and edit users',
        'devices.manage' => 'Create and edit devices',
        'devices.manage_map_icons' => 'Upload and manage custom vehicle map icons',
        'routes.view' => 'View transport routes',
        'routes.manage' => 'Create and manage transport routes',
        'web.geofence.view' => 'View geofence definitions',
        'web.geofence.manage' => 'Create, edit, and delete geofences',
        'web.map.toolbar.geofence' => 'Draw and manage geofences on the map',
        'web.history.view' => 'Access GPS history and route playback',
        'web.events.view' => 'View vehicle events and alert history',
        'stock.manage' => 'Manage GPS device inventory stock',
        'subscriptions.manage' => 'Manage subscriptions',
        'billing.view' => 'View invoices and profit/loss reports',
        'billing.manage' => 'Manage subscription plans and record payments',
        'sub_accounts.create' => 'Create sub accounts with vehicle and permission assignment',
        'sub_accounts.manage' => 'Edit and delete sub accounts',
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy: tc_users.administrator = 1 maps to this role when laravel_role empty
    |--------------------------------------------------------------------------
    */
    'legacy_administrator_role' => AppRole::SuperAdmin->value,

];
