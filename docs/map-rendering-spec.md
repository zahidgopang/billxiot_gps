# Map rendering specification

Web and mobile apps must render fleet maps using the same rules so live, history, playback, and device-detail screens look identical.

## Architecture (web)

| Layer | File | Role |
|-------|------|------|
| Primitives | `public/js/vehicle-marker.js` | SVG vehicle icon, GPS anchor, heading, screen-fixed pulse |
| Renderer | `public/js/fleet-map-renderer.js` | Current vehicle, route, start/end, history dots |
| App | `public/js/device-map-tracker.js` | Live poll, history load, playback, UI |

Styles: `public/css/fleet-map.css`

Load order on the map page:

1. `vehicle-marker.js`
2. `fleet-map-renderer.js`
3. `device-map-tracker.js`

After changing JS, run `npm run build:protect` for production obfuscated assets.

## Modes (single web page)

All modes use `FleetMapRenderer` on `device-map.blade.php`:

- **Live tracking** — `setCurrentVehicle` + pulse + optional live trail segments
- **History** — `drawRoute` + start/end markers + history dots; vehicle at route end
- **Playback** — same vehicle marker, `setPlaybackActive(true)` (hides LIVE badge)
- **Device details** — same map; HUD/sidebar only

There are no separate map implementations per screen.

## GPS anchor

- Marker anchor = **center of vehicle body** (not label, not image corner).
- Pulse overlay center = **same lat/lng** as marker anchor.
- Pulse size is **screen pixels** (does not scale with map zoom).

## API (`map_rendering`)

Mobile live/detail/list responses include `map_rendering` from `App\Services\Mobile\MapRenderingSpec` with sizes, colors, and connectivity tiers aligned with `MobileMapStatusResolver`.

Native apps should implement:

- Large oriented vehicle sprite at `(lat, lng)`
- Concentric pulse rings (screen-fixed diameter ~210px)
- Route polyline with speed colors
- 48px start/end markers
- Subtle history sample dots (optional)

## Status colors

| Key | Hex |
|-----|-----|
| moving | `#22c55e` |
| idle | `#f97316` |
| stopped | `#ef4444` |
| parked | `#3b82f6` |
| offline | `#94a3b8` |
| delayed | `#eab308` |
| alert | `#ef4444` |

## Legacy code

Do not use `public/js/map/*.js` or Leaflet backup blades under `resources/views/user/device-map.blade*.php` — they are unmaintained.
