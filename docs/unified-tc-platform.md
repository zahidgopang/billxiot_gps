# Unified platform: Laravel + Traccar on `tc_*` tables

Laravel and Traccar share **one database** and **one set of tracking tables**. Duplicate Laravel tracking tables are removed.

## Table map (final)

| Removed Laravel table | Replaced by |
|----------------------|-------------|
| `users` | `tc_users` (+ app fields in `attributes` JSON) |
| `devices` | `tc_devices` (`uniqueid` = IMEI) |
| `geofences` | `tc_geofences` (`area` = WKT) |
| `device_requests` | unused — dropped |
| `traccar_entity_map` | not needed — model `id` **is** `tc_*` id |
| `device_locations` | `tc_positions` |
| `vehicle_events` | `tc_events` |

## Kept (business only)

- `subscriptions`, `subscription_histories` — `user_id` / `device_id` reference **`tc_users.id`** / **`tc_devices.id`**
- `password_reset_tokens`, `sessions` — `user_id` → `tc_users.id`
- `contact_messages`, `activity_log`, jobs, cache, etc.

## Laravel models

| Model | Physical table |
|-------|----------------|
| `User` | `tc_users` |
| `Device` | `tc_devices` |
| `Geofence` | `tc_geofences` |

App-only fields (role, status, Laravel bcrypt password, preferences) are stored in the Traccar **`attributes`** JSON column (`TraccarAppFields`).

## Deploy steps

Traccar must create `tc_*` tables first (install Traccar / run its schema), then:

```bash
php artisan migrate
php artisan config:clear
```

Migration `2026_05_24_000001_seed_default_tc_admin_user` inserts the default Laravel admin into `tc_users` when missing (see `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env`).

Legacy Laravel tracking tables (`users`, `devices`, `geofences`, etc.) are **not** created by migrations anymore.

## `.env`

```env
TRACCAR_ENABLED=true
TRACCAR_MODE=full
TRACCAR_UNIFIED_IDS=true
```

## Traccar UI password

Laravel login uses bcrypt in `attributes.laravel_password`. Traccar UI still uses PBKDF2 on `hashedPassword` — set via `TRACCAR_DEFAULT_USER_PASSWORD` or `php artisan traccar:set-user-password`.
