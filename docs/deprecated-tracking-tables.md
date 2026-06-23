# Deprecated Laravel tracking tables

When `TRACCAR_MODE=full`, these tables are **no longer written**. Reads go through `tc_*` repositories.

| Table | Replacement | Keep row data? |
|-------|-------------|----------------|
| `device_locations` | `tc_positions` | Archive optional; not required for runtime |
| `vehicle_events` | `tc_events` | Archive optional |
| `geofence_events` | unused (never wired) | Safe to drop |
| `devices` | **Keep** — business registry (subscription FK, owner, status) + `tc_devices` via map |
| `geofences` | **Registry only** — API id/name; geometry in `tc_geofences` |
| `traccar_entity_map` | **Keep** — Laravel id ↔ Traccar id |

## Before dropping tables

1. `TRACCAR_MODE=full` in production for at least one release cycle
2. `php artisan traccar:legacy-status` — all checks green
3. Backup database
4. Optional: `mysqldump` archive of `device_locations` / `vehicle_events`

## Fresh installs

Laravel migrations no longer create or drop legacy tracking tables. Use Traccar `tc_*` tables only (`TRACCAR_MODE=full`).
