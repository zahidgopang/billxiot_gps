<?php

return [
    'step_label' => 'Step :current of :total',
    'next' => 'Next',
    'back' => 'Back',
    'finish' => 'Finish',
    'skip' => 'Skip tour',
    'dont_show_again' => 'Don\'t show again',
    'skip_title' => 'Skip tour',
    'skip_body' => 'Choose how often you want to see this tour when you open the map. You can replay it anytime from the Map tour button in the top bar.',
    'prefs_lead' => 'When you open this map again:',
    'pref_repeat' => 'Show tour every time (recommended for new users)',
    'pref_dismiss' => 'Don\'t show automatically — use Map tour button to replay',
    'target_missing' => 'This section is not visible on screen. Open the history panel or resize the window, then press Next.',

    'finish_step' => [
        'icon' => 'fa-check-circle',
        'title' => 'You\'re ready to track',
        'body' => 'Choose how often you want to see this tour, then press Finish. You can reopen it anytime with the Map tour button in the top bar.',
    ],

    'user' => [
        'welcome' => [
            'icon' => 'fa-map-marked-alt',
            'title' => 'Welcome to Live Vehicle Tracking',
            'body' => 'This guided tour walks you through every part of the tracking map — live position, history, routes, geofences, and alerts. Use Next to continue, Skip tour if you want fewer prompts, or Don\'t show again to stop the tour from opening automatically.',
        ],
        'nav' => [
            'icon' => 'fa-truck',
            'title' => 'Vehicle header',
            'body' => 'The vehicle name, IMEI, and live status badge appear here. The badge turns green when the device is online and reporting, amber when idle, or gray when offline.',
        ],
        'sidebar' => [
            'icon' => 'fa-sliders-h',
            'title' => 'History & filters panel',
            'body' => 'Open this panel to search trip history by date, view device telemetry, route statistics, trip events, exports, and saved geofences.',
        ],
        'date' => [
            'icon' => 'fa-calendar-alt',
            'title' => 'Date range search',
            'body' => 'Pick a single day or a date range, then tap Search History. Leave the field empty to load the last 24 hours automatically.',
        ],
        'device_summary' => [
            'icon' => 'fa-microchip',
            'title' => 'Device summary',
            'body' => 'Quick snapshot of the device name, IMEI, last GPS update time, and current status for the selected period.',
        ],
        'route_summary' => [
            'icon' => 'fa-chart-line',
            'title' => 'Route statistics',
            'body' => 'After history loads, see total distance, trip duration, average and maximum speed, moving vs stopped time, and overspeed event counts.',
        ],
        'trip_events' => [
            'icon' => 'fa-list-ul',
            'title' => 'Trip events & export',
            'body' => 'Review stops, starts, and parking events in the list. Export the loaded route as CSV or GPX for reports and fleet records.',
        ],
        'geofence_list' => [
            'icon' => 'fa-draw-polygon',
            'title' => 'Geofence list',
            'body' => 'All geofences saved for this device appear here. Draw new zones from the map and manage existing ones from this list.',
        ],
        'hud' => [
            'icon' => 'fa-tachometer-alt',
            'title' => 'Live vehicle panel',
            'body' => 'Real-time speed, heading, last update, coordinates, and address. Use Copy, Open in Maps, or Street View for quick actions.',
        ],
        'alerts' => [
            'icon' => 'fa-bell',
            'title' => 'Vehicle alerts',
            'body' => 'Recent alerts (overspeed, geofence enter/exit, stops, and more) appear in this bell menu. Open View all alerts for the full history.',
        ],
        'controls_right' => [
            'icon' => 'fa-layer-group',
            'title' => 'Map controls (right)',
            'body' => 'Recenter on the vehicle, toggle follow mode, open geofence drawing, clear the route, enable traffic, and switch map type.',
        ],
        'controls_left' => [
            'icon' => 'fa-toolbox',
            'title' => 'Map tools (left)',
            'body' => 'Fit the entire route in view, show a speed heatmap, toggle night-style map, export route data, and highlight parking stops.',
        ],
        'playback' => [
            'icon' => 'fa-play-circle',
            'title' => 'Route playback',
            'body' => 'After history is loaded, use Play Route to animate the vehicle along the path with adjustable playback speed.',
        ],
        'geofence_draw' => [
            'icon' => 'fa-vector-square',
            'title' => 'Draw geofences',
            'body' => 'Create circular or polygon geofences on the map. Name and save each zone to receive enter/exit alerts.',
        ],
        'route_click' => [
            'icon' => 'fa-route',
            'title' => 'Route segments on the map',
            'body' => 'Click any colored segment of the history line to open segment details: speed, distance, time window, and speed status.',
        ],
    ],

    'admin' => [
        'welcome' => [
            'icon' => 'fa-user-shield',
            'title' => 'Admin map view',
            'body' => 'You are viewing this device as an administrator. This tour explains the map tools for fleet support and investigations without subscription restrictions.',
        ],
        'nav' => [
            'icon' => 'fa-truck',
            'title' => 'Device & owner context',
            'body' => 'See the device name, IMEI, assigned user when available, and live status. The Admin badge confirms unrestricted location-history mode.',
        ],
        'back' => [
            'icon' => 'fa-arrow-left',
            'title' => 'Return to Location History',
            'body' => 'Use this link to return to the admin device list and open another vehicle map.',
        ],
        'sidebar' => [
            'icon' => 'fa-sliders-h',
            'title' => 'History & filters panel',
            'body' => 'Date filters, telemetry, route analytics, trip events, exports, and geofence management for this device.',
        ],
        'date' => [
            'icon' => 'fa-calendar-alt',
            'title' => 'Date range search',
            'body' => 'Filter GPS history by day or range, then Search History. An empty field defaults to the last 24 hours.',
        ],
        'device_summary' => [
            'icon' => 'fa-microchip',
            'title' => 'Device summary',
            'body' => 'IMEI, last report time, and operational status for support calls and audit trails.',
        ],
        'route_summary' => [
            'icon' => 'fa-chart-line',
            'title' => 'Route statistics',
            'body' => 'Distance, duration, speed metrics, and overspeed counts for compliance or dispute resolution.',
        ],
        'trip_events' => [
            'icon' => 'fa-list-ul',
            'title' => 'Trip events & export',
            'body' => 'Inspect stops and export CSV/GPX when sharing data with customers or internal teams.',
        ],
        'geofence_list' => [
            'icon' => 'fa-draw-polygon',
            'title' => 'Geofence list',
            'body' => 'View and verify geofences configured on this device.',
        ],
        'hud' => [
            'icon' => 'fa-tachometer-alt',
            'title' => 'Live telemetry HUD',
            'body' => 'Current position, speed, heading, and address for live monitoring during support sessions.',
        ],
        'alerts' => [
            'icon' => 'fa-bell',
            'title' => 'Device alerts',
            'body' => 'Preview recent vehicle events from the bell menu for investigation across the fleet.',
        ],
        'controls_right' => [
            'icon' => 'fa-layer-group',
            'title' => 'Map controls (right)',
            'body' => 'Recenter, follow, geofences, clear route, traffic layer, and basemap type.',
        ],
        'controls_left' => [
            'icon' => 'fa-toolbox',
            'title' => 'Map tools (left)',
            'body' => 'Fit route, heatmap, night mode, export, and parking-stop highlights.',
        ],
        'playback' => [
            'icon' => 'fa-play-circle',
            'title' => 'Route playback',
            'body' => 'Replay historical movement for driver behavior review or incident reconstruction.',
        ],
        'geofence_draw' => [
            'icon' => 'fa-vector-square',
            'title' => 'Draw geofences',
            'body' => 'Create or adjust geofences on behalf of the customer when providing setup assistance.',
        ],
        'route_click' => [
            'icon' => 'fa-route',
            'title' => 'Route segment details',
            'body' => 'Click route segments on the map for per-section speed and timing. Combine with playback for a complete movement picture.',
        ],
    ],
];
