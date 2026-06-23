#!/usr/bin/env python3
"""Batch i18n replacements in blade views."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "resources" / "views"

def patch(rel: str, pairs: list[tuple[str, str]]) -> None:
    p = ROOT / rel
    t = p.read_text(encoding="utf-8")
    for old, new in pairs:
        if old not in t:
            print(f"MISSING in {rel}: {old[:60]!r}")
        t = t.replace(old, new)
    p.write_text(t, encoding="utf-8")
    print(f"patched {rel}")

patch("partials/device-status-toggle.blade.php", [
    ('aria-label="Device active or inactive"', 'aria-label="{{ __(\'app.toggle.active\') }}"'),
    ("Blocked\n            @elseif($isActive)\n                Active\n            @else\n                Inactive",
     "{{ __('app.toggle.blocked') }}\n            @elseif($isActive)\n                {{ __('app.toggle.active') }}\n            @else\n                {{ __('app.toggle.inactive') }}"),
    ('title="Change via Edit device">blocked', 'title="{{ __(\'app.toggle.change_via_edit\') }}">{{ __(\'app.toggle.blocked_badge\') }}'),
])

patch("partials/account-status-toggle.blade.php", [
    ("Active account", "{{ __('app.toggle.account_active') }}"),
    ("Inactive account", "{{ __('app.toggle.account_inactive') }}"),
])

patch("admin/devices/index.blade.php", [
    ("<h5 class=\"mb-0\">Devices</h5>", "<h5 class=\"mb-0\">{{ __('app.admin.devices.title') }}</h5>"),
    (">Add Device</a>", ">{{ __('app.admin.devices.add') }}</a>"),
    ("type=\"submit\">Search</button>", "type=\"submit\">{{ __('app.common.search') }}</button>"),
    ("<th>IMEI</th>", "<th>{{ __('app.admin.devices.imei') }}</th>"),
    ("<th>Name</th>", "<th>{{ __('app.admin.devices.name') }}</th>"),
    ("<th>Type</th>", "<th>{{ __('app.admin.devices.type') }}</th>"),
    ("<th>User</th>", "<th>{{ __('app.admin.devices.user') }}</th>"),
    ("<th>Status</th>", "<th>{{ __('app.common.status') }}</th>"),
    ("<th>Last Known</th>", "<th>{{ __('app.admin.devices.last_known') }}</th>"),
    ("<th>Actions</th>", "<th>{{ __('app.common.actions') }}</th>"),
    (">Edit</a>", ">{{ __('app.common.edit') }}</a>"),
    (">Delete</button>", ">{{ __('app.common.delete') }}</button>"),
])

patch("admin/locations/index.blade.php", [
    ("<h5 class=\"mb-1\">Location History</h5>", "<h5 class=\"mb-1\">{{ __('app.admin.locations.title') }}</h5>"),
    (">Total devices</small>", ">{{ __('app.admin.locations.total_devices') }}</small>"),
    (">Online now</small>", ">{{ __('app.admin.locations.online_now') }}</small>"),
    (">Moving</small>", ">{{ __('app.admin.locations.moving') }}</small>"),
    (">Offline</small>", ">{{ __('app.admin.locations.offline') }}</small>"),
    ('value="">All device statuses', 'value="">{{ __(\'app.admin.locations.all_statuses\') }}'),
    (">Active</option>", ">{{ __('app.common.active') }}</option>"),
    (">Inactive</option>", ">{{ __('app.common.inactive') }}</option>"),
    (">Blocked</option>", ">{{ __('app.common.blocked') }}</option>"),
    (">Filter</button>", ">{{ __('app.common.filter') }}</button>"),
    ("<th>Device</th>", "<th>{{ __('app.admin.locations.device') }}</th>"),
    ("<th>Type</th>", "<th>{{ __('app.admin.devices.type') }}</th>"),
    ("<th>Owner</th>", "<th>{{ __('app.admin.locations.owner') }}</th>"),
    ("<th>Live status</th>", "<th>{{ __('app.admin.locations.live_status') }}</th>"),
    ("<th>Last position</th>", "<th>{{ __('app.admin.locations.last_position') }}</th>"),
    ("<th>Speed</th>", "<th>{{ __('app.admin.locations.speed') }}</th>"),
    ("<th>Last update</th>", "<th>{{ __('app.admin.locations.last_update') }}</th>"),
    ("<th>Subscription</th>", "<th>{{ __('app.admin.locations.subscription') }}</th>"),
    ("<th class=\"text-end\">Actions</th>", "<th class=\"text-end\">{{ __('app.common.actions') }}</th>"),
    (">Unassigned</span>", ">{{ __('app.admin.locations.unassigned') }}</span>"),
])

patch("user/devices.blade.php", [
    ("My Tracking Devices", "{{ __('app.user.devices.title') }}"),
    ("Manage and monitor all your registered GPS tracking devices", "{{ __('app.user.devices.subtitle') }}"),
    ("> Add Device", "> {{ __('app.user.devices.add') }}"),
    (">Total Devices</small>", ">{{ __('app.user.devices.total') }}</small>"),
    (">Online Now</small>", ">{{ __('app.user.devices.online_now') }}</small>"),
    (">Moving</small>", ">{{ __('app.user.devices.moving') }}</small>"),
    (">Parked / Idle</small>", ">{{ __('app.user.devices.parked_idle') }}</small>"),
])

patch("user/dashboard.blade.php", [
    ("<strong>Email Verified Successfully", "<strong>{{ __('app.user.dashboard.email_verified') }}"),
    ("Your email address has been verified. You now have full access.", "{{ __('app.user.dashboard.email_verified_msg') }}"),
    ("Welcome back,", "{{ __('app.user.dashboard.welcome_back') }}"),
    ("Track your vehicles in real-time with advanced analytics and insights.", "{{ __('app.user.dashboard.tagline') }}"),
    (">LIVE\n                        </span>", ">{{ __('app.common.live') }}\n                        </span>"),
    (">Active Vehicles</h6>", ">{{ __('app.user.dashboard.active_vehicles') }}</h6>"),
    (">Total Distance</h6>", ">{{ __('app.user.dashboard.total_distance') }}</h6>"),
    (">Last 30 days</span>", ">{{ __('app.user.dashboard.last_30_days') }}</span>"),
])

patch("admin/partials/sidebar.blade.php", [
    ("<span>GPS Tracker Pro</span>", "<span>{{ __('app.brand') }}</span>"),
    (">Soon</span>", ">{{ __('app.common.soon') }}</span>"),
])

patch("user/layout_user.blade.php", [
    ("<strong>GPS Tracker Pro</strong>", "<strong>{{ __('app.brand') }}</strong>"),
    (">Logout</span>", ">{{ __('app.common.logout') }}</span>"),
    (">Dashboard</h6>", ">{{ __('app.common.dashboard') }}</h6>"),
    (">My Devices</h6>", ">{{ __('app.user.devices.title') }}</h6>"),
    (">My Profile</h6>", ">{{ __('app.user.nav.my_profile') }}</h6>"),
    (">Security</h6>", ">{{ __('app.user.nav.security') }}</h6>"),
    (">Account</h6>", ">{{ __('app.user.nav.manage_account') }}</h6>"),
])

map_nav = [
    ('aria-label="Toggle filters panel" title="Filters &amp; history"', 'aria-label="{{ __(\'app.map.filters_history\') }}" title="{{ __(\'app.map.filters_history\') }}"'),
    ("? 'Location History' : 'Dashboard' }}", "? __('app.admin.locations.title') : __('app.common.dashboard') }}"),
    (">Admin</span>", ">{{ __('app.common.admin') }}</span>"),
    ('title="IMEI"', 'title="{{ __(\'app.map.imei_number\') }}"'),
    (">Waiting</span>", ">{{ __('app.common.waiting') }}</span>"),
    ("<span>GPS Tracker Pro</span>", "<span>{{ __('app.brand') }}</span>"),
    ('title="Map guided tour" aria-label="Start map tour"', 'title="{{ __(\'app.map.map_tour_title\') }}" aria-label="{{ __(\'app.map.map_tour\') }}"'),
    (">Map tour</span>", ">{{ __('app.map.map_tour') }}</span>"),
    ('aria-label="Alerts"', 'aria-label="{{ __(\'app.map.alerts\') }}"'),
    ('title="Alerts"', 'title="{{ __(\'app.map.alerts\') }}"'),
    (">Alerts</h6>", ">{{ __('app.map.alerts') }}</h6>"),
    ("No new alerts", "{{ __('app.map.no_new_alerts') }}"),
    ("No alerts yet", "{{ __('app.map.no_alerts_yet') }}"),
    ("Alerts for this device", "{{ __('app.map.alerts_for_device') }}"),
    ("View all alerts", "{{ __('app.map.view_all_alerts') }}"),
]
patch("user/layout.blade.php", map_nav)

# device-map key strings
dm = [
    ("@section('title', $device->name . ' - Live Tracking')", "@section('title', $device->name . ' - ' . __('app.map.live_tracking'))"),
    (">Loading map data...</div>", ">{{ __('app.map.loading_map') }}</div>"),
    ('title="Click to show or hide live vehicle panel"', 'title="{{ __(\'app.map.hud_toggle_title\') }}"'),
    (">Live vehicle</span>", ">{{ __('app.map.live_vehicle') }}</span>"),
    ("><span>Speed</span>", "><span>{{ __('app.map.speed') }}</span>"),
    ("><span>Heading</span>", "><span>{{ __('app.map.heading') }}</span>"),
    ("><span>Updated</span>", "><span>{{ __('app.map.updated') }}</span>"),
    ("><span>Coords</span>", "><span>{{ __('app.map.coords') }}</span>"),
    (">Address loading…</div>", ">{{ __('app.map.address_loading') }}</div>"),
    ("><i class=\"fas fa-copy\"></i> Copy</button>", "><i class=\"fas fa-copy\"></i> {{ __('app.map.copy') }}</button>"),
    ("><i class=\"fas fa-external-link-alt\"></i> Maps</button>", "><i class=\"fas fa-external-link-alt\"></i> {{ __('app.map.open_maps') }}</button>"),
    ("><i class=\"fas fa-street-view\"></i> View</button>", "><i class=\"fas fa-street-view\"></i> {{ __('app.map.street_view') }}</button>"),
    ("><span>Route density</span>", "><span>{{ __('app.map.route_density') }}</span>"),
    ('aria-label="Open route playback"', 'aria-label="{{ __(\'app.map.open_playback\') }}"'),
    (">Play route</span>", ">{{ __('app.map.play_route_lower') }}</span>"),
    (">Route Playback</h6>", ">{{ __('app.map.route_playback') }}</h6>"),
    (">Load history to start</p>", ">{{ __('app.map.load_history') }}</p>"),
    ('aria-label="Close playback"', 'aria-label="{{ __(\'app.map.playback_close\') }}"'),
    ('aria-label="Playback position"', 'aria-label="{{ __(\'app.map.playback_position\') }}"'),
    ('title="Restart"', 'title="{{ __(\'app.map.restart\') }}"'),
    ('title="Play"', 'title="{{ __(\'app.map.play\') }}"'),
    ('title="Stop"', 'title="{{ __(\'app.map.stop\') }}"'),
    (">Speed</span>", ">{{ __('app.map.speed_label') }}</span>"),
    ("> Draw Geofence</div>", "> {{ __('app.map.draw_geofence') }}</div>"),
    (">Polygon</span>", ">{{ __('app.map.polygon') }}</span>"),
    (">Circle</span>", ">{{ __('app.map.circle') }}</span>"),
    (">Select a shape to draw</div>", ">{{ __('app.map.geofence_select_shape') }}</div>"),
    (">Save</button>", ">{{ __('app.map.save') }}</button>"),
    (">Cancel</button>", ">{{ __('app.common.cancel') }}</button>"),
    ('title="Recenter"', 'title="{{ __(\'app.map.recenter\') }}"'),
    ('title="Follow"', 'title="{{ __(\'app.map.follow\') }}"'),
    ('title="Geofence"', 'title="{{ __(\'app.map.geofences\') }}"'),
    ('title="Clear Route"', 'title="{{ __(\'app.map.clear_route\') }}"'),
    ('title="Traffic"', 'title="{{ __(\'app.map.traffic\') }}"'),
    ('title="Road map"', 'title="{{ __(\'app.map.road_map\') }}"'),
    ('title="Satellite"', 'title="{{ __(\'app.map.satellite_layer\') }}"'),
    ('title="Hybrid"', 'title="{{ __(\'app.map.hybrid\') }}"'),
    ('title="Fit route"', 'title="{{ __(\'app.map.fit_route\') }}"'),
    ('title="Heatmap"', 'title="{{ __(\'app.map.heatmap\') }}"'),
    ('title="Night mode"', 'title="{{ __(\'app.map.night_mode\') }}"'),
    ('title="Export CSV"', 'title="{{ __(\'app.map.export_csv_title\') }}"'),
    ('title="Parking stops"', 'title="{{ __(\'app.map.parking_stops\') }}"'),
    (">Route Segment Details</h3>", ">{{ __('app.map.route_segment_details') }}</h3>"),
    (">Speed (km/h)</div>", ">{{ __('app.map.speed_kmh') }}</div>"),
    (">Distance</div>", ">{{ __('app.map.distance_label') }}</div>"),
    (">Start Time:</span>", ">{{ __('app.map.start_time') }}</span>"),
    (">End Time:</span>", ">{{ __('app.map.end_time') }}</span>"),
    (">Speed Status:</span>", ">{{ __('app.map.speed_status') }}</span>"),
    (">Normal</span>", ">{{ __('app.map.normal') }}</span>"),
    (">Coordinates:</span>", ">{{ __('app.map.coordinates') }}</span>"),
]
patch("user/device-map.blade.php", dm)
