from pathlib import Path
import re

p = Path("resources/views/user/side_bar_map.blade.php")
t = p.read_text(encoding="utf-8")
dash = "{{ __('app.map.dash') }}"
for el in [
    "telemetrySpeed",
    "telemetryHeading",
    "telemetryBattery",
    "telemetryIgnition",
    "telemetryGsm",
    "telemetrySatellites",
    "telemetryOdometer",
    "movingTime",
    "stoppedTime",
]:
    t = re.sub(
        rf'(id="{el}")>[^<]+<',
        rf"\1>{dash}<",
        t,
    )
p.write_text(t, encoding="utf-8")
print("fixed dashes")
