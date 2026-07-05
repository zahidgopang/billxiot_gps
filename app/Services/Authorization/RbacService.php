<?php

namespace App\Services\Authorization;

use App\Enums\AppRole;
use App\Models\User;
use App\Support\Authorization\PermissionCatalog;
use App\Support\Traccar\TraccarAppFields;

class RbacService
{
    public function roleOf(User $user): AppRole
    {
        $raw = $user->role;

        return AppRole::tryFrom($raw) ?? AppRole::EndUser;
    }

    public function isSuperAdmin(User $user): bool
    {
        return $this->roleOf($user) === AppRole::SuperAdmin;
    }

    /**
     * Super admin (platform owner) — no subscription/device tracking restrictions.
     */
    public function bypassesSubscriptionRestrictions(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Legacy admin panel gate (super admin + vendor admin).
     */
    public function isVendorAdmin(User $user): bool
    {
        return in_array($this->roleOf($user), [AppRole::SuperAdmin, AppRole::Admin], true);
    }

    public function isClientManager(User $user): bool
    {
        return $this->roleOf($user) === AppRole::Client;
    }

    public function isEndUser(User $user): bool
    {
        return $this->roleOf($user) === AppRole::EndUser;
    }

    public function canAccessPanel(User $user): bool
    {
        return in_array($this->roleOf($user)->value, AppRole::panelRoles(), true);
    }

    public function panelRouteFor(User $user): string
    {
        return match ($this->roleOf($user)) {
            AppRole::SuperAdmin, AppRole::Admin => 'admin.dashboard',
            AppRole::Client => 'client.dashboard',
            AppRole::EndUser => 'user.dashboard',
        };
    }

    public function hasPermission(User $user, string $permission): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        // End users always get essential fleet permissions (live map, reports, commands).
        // Device scope is enforced separately — overrides cannot revoke these.
        if ($this->isEndUser($user) && PermissionCatalog::isEssentialFleetPermission($permission)) {
            return true;
        }

        $role = $this->roleOf($user)->value;
        $rolePermissions = $this->rolePermissionKeys($role);
        $overrides = $this->permissionOverrides($user);

        if (array_key_exists($permission, $overrides)) {
            return (bool) $overrides[$permission];
        }

        if (in_array('*', $rolePermissions, true)) {
            return true;
        }

        if (in_array($permission, $rolePermissions, true)) {
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function rolePermissionKeys(string $role): array
    {
        try {
            return app(PermissionCatalogService::class)->rolePermissionKeys($role);
        } catch (\Throwable) {
            return config("rbac.roles.{$role}.permissions", []);
        }
    }

    /**
     * @return list<string>
     */
    public function grantedPermissionsFor(User $user): array
    {
        if ($this->isSuperAdmin($user)) {
            return ['*'];
        }

        $role = $this->roleOf($user)->value;
        $base = $this->rolePermissionKeys($role);
        $overrides = $this->permissionOverrides($user);

        $granted = array_filter($base, fn ($p) => $p !== '*');

        if ($this->isEndUser($user)) {
            $granted = array_merge($granted, PermissionCatalog::essentialFleetPermissionKeys());
        }

        foreach ($overrides as $key => $enabled) {
            if ($enabled) {
                $granted[] = $key;
            } elseif (! ($this->isEndUser($user) && PermissionCatalog::isEssentialFleetPermission($key))) {
                $granted = array_values(array_filter($granted, fn ($k) => $k !== $key));
            }
        }

        return array_values(array_unique($granted));
    }

    /**
     * @return array<string, bool>
     */
    public function permissionOverrides(User $user): array
    {
        $raw = TraccarAppFields::get(
            $user->getTraccarAttributesJson(),
            TraccarAppFields::KEY_PERMISSIONS,
            []
        );

        return is_array($raw) ? $raw : [];
    }

    public function canCreateRole(User $actor, string $targetRole): bool
    {
        $actorRole = $this->roleOf($actor);

        return in_array($targetRole, AppRole::creatableBy($actorRole), true);
    }

    /**
     * @return list<string>
     */
    public function assignableRoles(User $actor): array
    {
        return AppRole::creatableBy($this->roleOf($actor));
    }

    public function roleSupportsMapTrackingToggle(AppRole|string|null $role): bool
    {
        $role = $role instanceof AppRole ? $role : AppRole::tryFrom((string) $role);

        return in_array($role, [AppRole::Admin, AppRole::Client], true);
    }

    public function syncMapsViewPermission(User $user, bool $enabled): void
    {
        $this->setPermissionOverride($user, 'maps.view', $enabled);
    }

    public function roleGrantsPermission(string $role, string $permission): bool
    {
        $keys = $this->rolePermissionKeys($role);

        if (in_array('*', $keys, true)) {
            return true;
        }

        return in_array($permission, $keys, true);
    }

    /**
     * @param  array<string, mixed>  $submitted  key => ''|null (inherit), '1'|true (grant), '0'|false (deny)
     */
    public function syncPermissionOverrides(User $user, array $submitted, ?array $validKeys = null): void
    {
        $validKeys ??= app(PermissionCatalogService::class)->allActiveKeys();
        $validSet = array_flip($validKeys);
        $overrides = $this->permissionOverrides($user);

        foreach ($submitted as $key => $value) {
            if (! is_string($key) || ! isset($validSet[$key])) {
                continue;
            }

            if ($this->isEndUser($user)
                && PermissionCatalog::isEssentialFleetPermission($key)
                && ($value === '0' || $value === 0 || $value === false)) {
                continue;
            }

            if ($value === '' || $value === null) {
                unset($overrides[$key]);
            } elseif ($value === '1' || $value === 1 || $value === true) {
                $overrides[$key] = true;
            } elseif ($value === '0' || $value === 0 || $value === false) {
                $overrides[$key] = false;
            }
        }

        $user->patchTraccarAppAttributes([
            TraccarAppFields::KEY_PERMISSIONS => $overrides !== [] ? $overrides : null,
        ]);

        if ($user->exists) {
            $user->save();
        }
    }

    public function setPermissionOverride(User $user, string $permission, ?bool $value): void
    {
        if ($this->isEndUser($user)
            && $value === false
            && PermissionCatalog::isEssentialFleetPermission($permission)) {
            return;
        }

        $overrides = $this->permissionOverrides($user);

        if ($value === null) {
            unset($overrides[$permission]);
        } else {
            $overrides[$permission] = $value;
        }

        $user->patchTraccarAppAttributes([
            TraccarAppFields::KEY_PERMISSIONS => $overrides !== [] ? $overrides : null,
        ]);

        if ($user->exists) {
            $user->save();
        }
    }

    /**
     * Remove deny overrides (and live-map-only grants) that block essential fleet access.
     */
    public function purgeEssentialDenyOverrides(User $user): bool
    {
        if (! $this->isEndUser($user)) {
            return false;
        }

        $overrides = $this->permissionOverrides($user);
        $changed = false;

        foreach (PermissionCatalog::essentialFleetPermissionKeys() as $key) {
            if (($overrides[$key] ?? null) === false) {
                unset($overrides[$key]);
                $changed = true;
            }
        }

        if (($overrides['web.map.live_only'] ?? null) === true) {
            unset($overrides['web.map.live_only']);
            $changed = true;
        }

        if (! $changed) {
            return false;
        }

        $user->patchTraccarAppAttributes([
            TraccarAppFields::KEY_PERMISSIONS => $overrides !== [] ? $overrides : null,
        ]);

        if ($user->exists) {
            $user->save();
        }

        return true;
    }
}
