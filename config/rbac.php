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
                'stock.view',
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
                'subscriptions.view',
                'subscriptions.manage',
                'billing.view',
                'activity.view',
            ],
        ],
        AppRole::EndUser->value => [
            'label' => 'End User',
            'panel' => 'user',
            'permissions' => [],
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
        'stock.manage' => 'Manage GPS device inventory stock',
        'subscriptions.manage' => 'Manage subscriptions',
        'billing.view' => 'View invoices and profit/loss reports',
        'billing.manage' => 'Manage subscription plans and record payments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy: tc_users.administrator = 1 maps to this role when laravel_role empty
    |--------------------------------------------------------------------------
    */
    'legacy_administrator_role' => AppRole::SuperAdmin->value,

];
