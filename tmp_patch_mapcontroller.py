from pathlib import Path

p = Path(r"app/Http/Controllers/MapController.php")
text = p.read_text(encoding="utf-8")
old = "DeviceLocationPayload::fromDeviceLocation($location, $device),\n            ["
new = "DeviceLocationPayload::fromDeviceLocation($location, $device),\n            $device->mapAppearancePayload(),\n            ["
if old not in text:
    raise SystemExit("OLD NOT FOUND")
p.write_text(text.replace(old, new, 1), encoding="utf-8")
print("UPDATED")
