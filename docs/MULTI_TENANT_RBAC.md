# Multi-tenant RBAC architecture

GPS tracking remains on **Traccar `tc_*` tables** (`tc_users`, `tc_devices`, `tc_user_device`, `tc_positions`). Laravel adds a **tenant and permission layer** on top without duplicating device or position data.

## Role hierarchy

| Role | Panel | Description |
|------|--------|-------------|
| `super_admin` | `/admin` | Full system access; manages admins, clients, permissions |
| `admin` | `/admin` | Vendor staff; scoped to assigned **clients** via `admin_client_scopes` |
| `client` | `/client` | Fleet/company manager; scoped to own **client** via `client_members` |
| `user` | `/user` | End user; tracks devices linked in `tc_user_device` (unchanged behaviour) |

Roles are stored in `tc_users.attributes.laravel_role`. Legacy `administrator = 1` users are migrated to `super_admin`.

## Laravel tables (tenant layer)

- **`clients`** — company / fleet (tenant)
- **`client_members`** — links `tc_users` to a client (`user_id`, `client_id`)
- **`client_devices`** — links `tc_devices` to a client (`device_id` unique per fleet)
- **`admin_client_scopes`** — which clients an **admin** user may manage

## Permission model

Defined in `config/rbac.php`. Checked via:

- `Gate::allows('permission', 'devices.manage')`
- Middleware `permission:devices.view`
- `RbacService::hasPermission()`

Super admin has `*`. Optional per-user grants in `attributes.laravel_permissions` (JSON).

## Access choke points

1. **`TenantScopeService`** — visible client/user/device IDs per actor
2. **`TraccarDeviceAccessService`** — device lists and `userCanAccessDevice()` (end users + map access for panel roles)
3. **`AuthServiceProvider` gates** — `admin`, `panel.access`, `manage-user`, `manage-device`, `manage-client`
4. **Admin / Client controllers** — `InteractsWithTenantAuthorization` + scoped queries

## End user behaviour

Unchanged: login → `/user/*` → devices from `tc_user_device`, subscriptions, live map. If the user is linked to a client, device access is intersected with that client’s fleet (`client_devices`).

## Setup

```bash
php artisan migrate
```

Assign a device to a client when creating/editing in admin (or client panel). Assign users to a client with role **Client** or **user** (end user).

## Map access

- End users: existing map flow + subscription gates
- Panel roles: require `maps.view`; optional `maps.view_all` grant in `laravel_permissions`

## Next steps (optional)

- Subscription scoping per client in `Admin\SubscriptionController`
- Client-branded UI / separate layout
- API Sanctum policies using the same gates
- Spatie Permission package if you need dynamic DB-driven permissions
