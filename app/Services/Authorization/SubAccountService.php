<?php

namespace App\Services\Authorization;

use App\Enums\AppRole;
use App\Models\ClientMember;
use App\Models\Device;
use App\Models\SubAccount;
use App\Models\User;
use App\Services\Traccar\TraccarUserDeviceLinker;
use App\Support\Authorization\PermissionCatalog;
use App\Support\Traccar\TraccarAppFields;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SubAccountService
{
    /**
     * Permission keys that cannot be delegated to sub accounts.
     *
     * @var list<string>
     */
    private const EXCLUDED_DELEGATION_KEYS = [
        'panel.access',
        'permissions.manage',
        'users.view',
        'users.manage',
        'clients.view',
        'clients.manage',
        'stock.view',
        'stock.manage',
        'subscriptions.view',
        'subscriptions.manage',
        'billing.manage',
        'activity.view',
        'maps.view_all',
        'sub_accounts.view',
        'sub_accounts.manage',
    ];

    public function __construct(
        private RbacService $rbac,
        private TenantScopeService $tenantScope,
        private PermissionCatalogService $catalog,
        private TraccarUserDeviceLinker $deviceLinker,
    ) {}

    public function canCreateSubAccounts(User $parent): bool
    {
        return $parent->canCreateSubAccounts();
    }

    public function canViewSubAccounts(User $parent): bool
    {
        return $parent->canViewSubAccounts();
    }

    public function canViewSubAccount(User $parent, User $child): bool
    {
        if (! $parent->canViewSubAccounts()) {
            return false;
        }

        return $this->ownsSubAccount($parent, $child);
    }

    public function canManageSubAccount(User $parent, User $child): bool
    {
        if (! $parent->canManageSubAccounts()) {
            return false;
        }

        return $this->ownsSubAccount($parent, $child);
    }

    public function ownsSubAccount(User $parent, User $child): bool
    {
        if (! $child->isSubAccount()) {
            return false;
        }

        return SubAccount::query()
            ->where('parent_user_id', $parent->id)
            ->where('user_id', $child->id)
            ->exists();
    }

    public function queryForParent(User $parent): Builder
    {
        if (! $this->canViewSubAccounts($parent)) {
            return User::query()->whereRaw('1 = 0');
        }

        $ids = SubAccount::query()
            ->where('parent_user_id', $parent->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return User::query()
            ->whereIn('id', $ids !== [] ? $ids : [0])
            ->withCount('devices as tracker_devices_count');
    }

    /**
     * @return Collection<int, Device>
     */
    public function assignableDevices(User $parent): Collection
    {
        if ($this->rbac->isEndUser($parent) || $parent->isSubAccount()) {
            return $parent->trackableDevicesQuery()->orderBy('name')->get();
        }

        if ($this->rbac->canAccessPanel($parent)) {
            return $this->tenantScope
                ->scopeDevices(Device::query()->orderBy('name'), $parent)
                ->get();
        }

        return $parent->trackableDevicesQuery()->orderBy('name')->get();
    }

    /**
     * @param  list<int>  $deviceIds
     * @return list<int>
     */
    public function validateDeviceIds(User $parent, array $deviceIds): array
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));

        if ($deviceIds === []) {
            throw ValidationException::withMessages([
                'device_ids' => __('app.sub_accounts.validation.devices_required'),
            ]);
        }

        $allowed = $this->assignableDevices($parent)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allowedSet = array_flip($allowed);
        $invalid = array_values(array_filter($deviceIds, fn ($id) => ! isset($allowedSet[$id])));

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'device_ids' => __('app.sub_accounts.validation.devices_invalid'),
            ]);
        }

        return $deviceIds;
    }

    /**
     * Fleet permissions parents may always offer on the sub-account form
     * (even when not currently granted to the parent), so admins/clients/users
     * can assign geofences and related tracking access to sub-accounts.
     *
     * @var list<string>
     */
    private const ALWAYS_DELEGATABLE_KEYS = [
        'web.geofence.view',
        'web.geofence.manage',
        'web.map.toolbar.geofence',
        'web.map.sidebar.places',
        'web.places.view',
        'web.places.manage',
        'web.history.view',
        'web.events.view',
    ];

    /**
     * @return list<string>
     */
    public function assignablePermissionKeys(User $parent): array
    {
        $granted = $this->rbac->grantedPermissionsFor($parent);

        if (in_array('*', $granted, true)) {
            $granted = $this->catalog->allActiveKeys();
        }

        $active = array_flip($this->catalog->allActiveKeys());
        foreach (self::ALWAYS_DELEGATABLE_KEYS as $key) {
            if (isset($active[$key])) {
                $granted[] = $key;
            }
        }

        return array_values(array_unique(array_filter($granted, function (string $key): bool {
            if (str_starts_with($key, 'pref.')) {
                return false;
            }

            return ! in_array($key, self::EXCLUDED_DELEGATION_KEYS, true);
        })));
    }

    /**
     * Grouped permissions for the sub-account form (parent can only delegate what they have).
     *
     * @return array<string, array<string, array<string, array<string, mixed>>>>
     */
    public function groupedPermissionsForForm(User $parent, ?User $child = null): array
    {
        $assignable = array_flip($this->assignablePermissionKeys($parent));
        $grouped = $this->catalog->groupedForUi(managementOnly: false);
        $filtered = [];

        foreach ($grouped as $module => $tab) {
            $categories = [];

            foreach ($tab['categories'] as $category => $groups) {
                $filteredGroups = [];

                foreach ($groups as $groupKey => $group) {
                    $permissions = array_values(array_filter(
                        $group['permissions'],
                        fn ($perm) => isset($assignable[$perm->key])
                    ));

                    if ($permissions !== []) {
                        $filteredGroups[$groupKey] = [
                            'label' => $group['label'],
                            'permissions' => $permissions,
                        ];
                    }
                }

                if ($filteredGroups !== []) {
                    $categories[$category] = $filteredGroups;
                }
            }

            if ($categories !== []) {
                $count = 0;
                foreach ($categories as $groups) {
                    foreach ($groups as $group) {
                        $count += count($group['permissions']);
                    }
                }

                $filtered[$module] = [
                    'label' => $tab['label'],
                    'count' => $count,
                    'categories' => $categories,
                ];
            }
        }

        return $filtered;
    }

    /**
     * Default checked permissions on the create form (role baseline ∩ parent assignable).
     *
     * @return list<string>
     */
    public function defaultPermissionKeysForForm(User $parent): array
    {
        return array_values(array_intersect(
            $this->rbac->rolePermissionKeys(AppRole::EndUser->value),
            $this->assignablePermissionKeys($parent)
        ));
    }

    /**
     * Permissions that should appear checked when editing a sub account form.
     *
     * @return list<string>
     */
    public function selectedPermissionKeysForForm(User $subAccount, User $parent): array
    {
        $assignable = $this->assignablePermissionKeys($parent);
        $overrides = $this->rbac->permissionOverrides($subAccount);
        $role = AppRole::EndUser->value;
        $selected = [];

        foreach ($assignable as $key) {
            $roleGrants = $this->rbac->roleGrantsPermission($role, $key)
                || PermissionCatalog::isEssentialFleetPermission($key);

            if (array_key_exists($key, $overrides)) {
                if ($this->permissionOverrideEnables($overrides[$key])) {
                    $selected[] = $key;
                }

                continue;
            }

            if ($roleGrants) {
                $selected[] = $key;
            }
        }

        return $selected;
    }

    private function permissionOverrideEnables(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if ($value === false || $value === 0 || $value === '0' || $value === null || $value === '') {
            return false;
        }

        return (bool) $value;
    }

    /**
     * @return array<string, bool>
     */
    public function grantedPermissionMap(User $user): array
    {
        $granted = array_flip($this->rbac->grantedPermissionsFor($user));

        return array_map(fn ($key) => isset($granted[$key]), array_flip($this->catalog->allActiveKeys()));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $parent, array $data): User
    {
        if (! $this->canCreateSubAccounts($parent)) {
            abort(403);
        }

        $deviceIds = $this->validateDeviceIds($parent, (array) ($data['device_ids'] ?? []));
        $permissionKeys = (array) ($data['permission_keys'] ?? []);

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'] ?? 'active',
            'role' => AppRole::EndUser->value,
            'country_code' => $data['country_code'] ?? '',
            'phone' => $data['phone'] ?? '',
        ]);

        $user->password = $data['password'];
        $user->setTraccarPlainPasswordForNextSave($data['password']);

        if (TraccarSchema::hasColumn($user->getTable(), 'login')) {
            $user->setAttribute('login', strtolower((string) $data['email']));
        }

        $user->patchTraccarAppAttributes([
            TraccarAppFields::KEY_IS_SUB_ACCOUNT => true,
            TraccarAppFields::KEY_PARENT_USER_ID => (int) $parent->id,
            TraccarAppFields::KEY_CREATED_BY => (int) $parent->id,
        ]);

        $user->save();

        SubAccount::query()->create([
            'parent_user_id' => $parent->id,
            'user_id' => $user->id,
        ]);

        $this->inheritClientMembership($parent, $user);
        $this->syncDevices($user, $deviceIds);
        $this->syncPermissions($user, $permissionKeys, $parent);

        if (! $user->isSubAccount()) {
            $this->rbac->purgeEssentialDenyOverrides($user);
        }

        return $user->fresh(['devices']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $parent, User $child, array $data): User
    {
        if (! $this->canManageSubAccount($parent, $child)) {
            abort(403);
        }

        $deviceIds = $this->validateDeviceIds($parent, (array) ($data['device_ids'] ?? []));
        $permissionKeys = (array) ($data['permission_keys'] ?? []);

        $child->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'] ?? $child->status,
            'country_code' => $data['country_code'] ?? '',
            'phone' => $data['phone'] ?? '',
        ]);

        if (! empty($data['password'])) {
            $child->password = $data['password'];
            $child->setTraccarPlainPasswordForNextSave($data['password']);
        }

        if (TraccarSchema::hasColumn($child->getTable(), 'login')) {
            $child->setAttribute('login', strtolower((string) $data['email']));
        }

        $child->save();

        $this->syncDevices($child, $deviceIds);
        $this->syncPermissions($child, $permissionKeys, $parent);

        if (! $child->isSubAccount()) {
            $this->rbac->purgeEssentialDenyOverrides($child);
        }

        return $child->fresh(['devices']);
    }

    public function delete(User $parent, User $child): void
    {
        if (! $this->canManageSubAccount($parent, $child)) {
            abort(403);
        }

        SubAccount::query()->where('user_id', $child->id)->delete();
        $child->delete();
    }

    /**
     * @param  list<int>  $deviceIds
     */
    public function syncDevices(User $child, array $deviceIds): void
    {
        $this->deviceLinker->removeForUser((int) $child->id);

        foreach ($deviceIds as $deviceId) {
            $this->deviceLinker->upsert((int) $child->id, (int) $deviceId);
        }
    }

    /**
     * @param  list<string>  $checkedKeys
     */
    public function syncPermissions(User $child, array $checkedKeys, User $parent): void
    {
        $assignable = $this->assignablePermissionKeys($parent);
        $assignableSet = array_flip($assignable);
        $checked = array_flip(array_values(array_unique(array_filter(
            $checkedKeys,
            fn ($key) => is_string($key) && isset($assignableSet[$key])
        ))));

        $role = AppRole::EndUser->value;
        $existing = $this->rbac->permissionOverrides($child);
        $submitted = [];

        foreach ($assignable as $key) {
            $roleGrants = $this->rbac->roleGrantsPermission($role, $key)
                || PermissionCatalog::isEssentialFleetPermission($key);
            $wants = isset($checked[$key]);

            if ($wants !== $roleGrants) {
                $submitted[$key] = $wants ? '1' : '0';
            } elseif (array_key_exists($key, $existing)) {
                $submitted[$key] = '';
            }
        }

        $this->rbac->syncPermissionOverrides($child, $submitted, $assignable);
    }

    private function inheritClientMembership(User $parent, User $child): void
    {
        $memberships = ClientMember::query()
            ->where('user_id', $parent->id)
            ->get();

        foreach ($memberships as $membership) {
            $this->tenantScope->assignUserToClient(
                $child,
                (int) $membership->client_id,
                'member'
            );
        }
    }
}
