<?php

namespace App\Services\Authorization;

use App\Enums\AppRole;
use App\Models\User;
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

        $role = $this->roleOf($user)->value;
        $rolePermissions = config("rbac.roles.{$role}.permissions", []);

        if (in_array('*', $rolePermissions, true)) {
            return true;
        }

        if (in_array($permission, $rolePermissions, true)) {
            return true;
        }

        $overrides = $this->permissionOverrides($user);

        if (array_key_exists($permission, $overrides)) {
            return (bool) $overrides[$permission];
        }

        return false;
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

    public function setPermissionOverride(User $user, string $permission, ?bool $value): void
    {
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
}
