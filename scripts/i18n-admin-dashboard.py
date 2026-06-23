#!/usr/bin/env python3
from pathlib import Path

p = Path(__file__).resolve().parents[1] / "resources/views/admin/dashboard.blade.php"
t = p.read_text(encoding="utf-8")

pairs = [
    ("Admin Dashboard</h1>", "{{ __('app.admin.dashboard.title') }}</h1>"),
    ("Platform overview — users, fleet, GPS ingest, and alerts", "{{ __('app.admin.dashboard.subtitle') }}"),
    ("> Add Device</a>", "> {{ __('app.admin.dashboard.add_device') }}</a>"),
    ("> Users</a>", "> {{ __('app.common.users') }}</a>"),
    (">Database</div>", ">{{ __('app.admin.dashboard.database') }}</div>"),
    ("{{ $dbOk ? 'Connected' : 'Error' }}", "{{ $dbOk ? __('app.admin.dashboard.connected') : __('app.admin.dashboard.error') }}"),
    (">GPS ingest</div>", ">{{ __('app.admin.dashboard.gps_ingest') }}</div>"),
    ("{{ $gpsLive ? 'Live' : 'Idle' }}", "{{ $gpsLive ? __('app.common.live') : __('app.admin.dashboard.idle') }}"),
    (">API</div>", ">{{ __('app.admin.dashboard.api') }}</div>"),
    ("> Operational</div>", "> {{ __('app.admin.dashboard.operational') }}</div>"),
    (">Contact inbox</div>", ">{{ __('app.admin.dashboard.contact_inbox') }}</div>"),
    ("{{ $pendingContacts }} pending", "{{ __('app.admin.dashboard.pending', ['count' => $pendingContacts]) }}"),
    (">Fleet users</div>", ">{{ __('app.admin.dashboard.fleet_users') }}</div>"),
    (">Total devices</div>", ">{{ __('app.admin.dashboard.total_devices') }}</div>"),
    ("{{ $activeDevices }} active · {{ $inactiveDevices }} inactive",
     "{{ __('app.admin.dashboard.active_inactive', ['active' => $activeDevices, 'inactive' => $inactiveDevices]) }}"),
    ('class="stats-label">Online now ({{ \\App\\Services\\AdminDashboardService::ONLINE_MINUTES }}m)</div>',
     'class="stats-label">{{ __(\'app.admin.dashboard.online_now\', [\'minutes\' => \\App\\Services\\AdminDashboardService::ONLINE_MINUTES]) }}</div>'),
    ("{{ $movingNow }} moving · {{ $offlineDevices }} offline",
     "{{ __('app.admin.dashboard.moving_offline', ['moving' => $movingNow, 'offline' => $offlineDevices]) }}"),
    (">Active subscriptions</div>", ">{{ __('app.admin.dashboard.active_subscriptions') }}</div>"),
    ("{{ $totalSubscriptions }} total · {{ $expiredSubscriptions }} expired",
     "{{ __('app.admin.dashboard.subs_total_expired', ['total' => $totalSubscriptions, 'expired' => $expiredSubscriptions]) }}"),
    (">Alerts today</small>", ">{{ __('app.admin.dashboard.alerts_today') }}</small>"),
    (">Alerts (7 days)</small>", ">{{ __('app.admin.dashboard.alerts_week') }}</small>"),
    (">GPS points today</small>", ">{{ __('app.admin.dashboard.gps_points_today') }}</small>"),
    (">Geofences</small>", ">{{ __('app.admin.dashboard.geofences') }}</small>"),
    (">Blocked devices</small>", ">{{ __('app.admin.dashboard.blocked_devices') }}</small>"),
    (">Unassigned devices</small>", ">{{ __('app.admin.dashboard.unassigned_devices') }}</small>"),
    ("> GPS activity — last 30 days</h5>", "> {{ __('app.admin.dashboard.gps_activity_chart') }}</h5>"),
    ("> Recent activity</h5>", "> {{ __('app.admin.dashboard.recent_activity') }}</h5>"),
    ("No recent activity yet.", "{{ __('app.admin.dashboard.no_recent_activity') }}"),
    ("> Recent devices</h5>", "> {{ __('app.admin.dashboard.recent_devices') }}</h5>"),
    (">View all</a>", ">{{ __('app.admin.dashboard.view_all') }}</a>"),
]

for old, new in pairs:
    if old not in t:
        print("MISSING:", old[:50])
    else:
        t = t.replace(old, new)

p.write_text(t, encoding="utf-8")
print("done")
