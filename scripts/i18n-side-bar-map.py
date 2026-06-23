#!/usr/bin/env python3
from pathlib import Path

p = Path(__file__).resolve().parents[1] / "resources/views/user/side_bar_map.blade.php"
t = p.read_text(encoding="utf-8")

def b(key: str) -> str:
    return "{{ __('" + key + "') }}"

labels = {
    "Device Name": "app.map.device_name",
    "IMEI Number": "app.map.imei_number",
    "Last Update": "app.map.last_update",
    "Current Status": "app.map.current_status",
    "Speed": "app.map.speed",
    "Heading": "app.map.heading",
    "Battery": "app.map.battery",
    "Ignition": "app.map.ignition",
    "GSM Signal": "app.map.gsm_signal",
    "Satellites": "app.map.satellites",
    "Odometer": "app.map.odometer",
    "Total Distance": "app.map.total_distance",
    "Duration": "app.map.duration",
    "Average Speed": "app.map.average_speed",
    "Max Speed": "app.map.max_speed",
    "Moving Time": "app.map.moving_time",
    "Stopped Time": "app.map.stopped_time",
    "Overspeed Events": "app.map.overspeed_events",
    "Idle Events": "app.map.idle_events",
    "Total Idle Time": "app.map.total_idle_time",
}

headers = {
    "Live Telemetry": "app.map.live_telemetry",
    "Route Summary": "app.map.route_summary",
    "Trip Events": "app.map.trip_events",
    "Export": "app.map.export",
    "Idle Summary": "app.map.idle_summary",
    "Geofences": "app.map.geofences",
    "Address Lookup": "app.map.address_lookup",
}

for old, key in labels.items():
    t = t.replace(
        '<div class="info-label">' + old + '</div>',
        '<div class="info-label">' + b(key) + '</div>',
    )

for old, key in headers.items():
    t = t.replace('>' + old + '</h6>', '>' + b(key) + '</h6>')

t = t.replace("Load history to see stops", b("app.map.load_history_stops"))
t = t.replace("Loading geofences...", b("app.map.loading_geofences"))
t = t.replace("Get Current Address", b("app.map.get_address"))
t = t.replace(">CSV</button>", ">" + b("app.map.export_csv") + "</button>")
t = t.replace(">GPX</button>", ">" + b("app.map.export_gpx") + "</button>")

rtl_css = """
    html[dir="rtl"] .filter-panel {
        left: auto;
        right: 0;
        border-right: none;
        border-left: 1px solid rgba(255, 255, 255, 0.1);
        transform: translateX(100%);
    }
    html[dir="rtl"] .filter-panel.show {
        transform: translateX(0);
    }
    html[dir="rtl"] .sidebar-back-link i.fa-arrow-left {
        transform: scaleX(-1);
    }
    html[dir="rtl"] .info-value {
        text-align: left;
    }
"""

if 'html[dir="rtl"] .filter-panel' not in t:
    t = t.replace(
        "    .filter-panel.show {\n        transform: translateX(0);\n    }",
        "    .filter-panel.show {\n        transform: translateX(0);\n    }\n" + rtl_css,
    )

p.write_text(t, encoding="utf-8")
print("OK")
