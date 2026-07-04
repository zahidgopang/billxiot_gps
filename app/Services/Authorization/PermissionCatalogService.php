<?php

namespace App\Services\Authorization;

use App\Models\Permission;
use App\Models\PermissionTemplate;
use App\Models\RolePermission;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionCatalogService
{
    private const CACHE_ROLE_KEYS = 'rbac.role_permission_keys.';

    private const CACHE_ALL_KEYS = 'rbac.all_permission_keys';

    /**
     * Sync permission definitions and templates from the developer catalog.
     */
    public function syncCatalog(): array
    {
        $stats = ['permissions' => 0, 'templates' => 0, 'roles' => 0];

        DB::transaction(function () use (&$stats) {
            foreach (PermissionCatalog::definitions() as $row) {
                Permission::query()->updateOrCreate(
                    ['key' => $row['key']],
                    $row + ['is_active' => true],
                );
                $stats['permissions']++;
            }

            Permission::query()
                ->whereNotIn('key', array_column(PermissionCatalog::definitions(), 'key'))
                ->update(['is_active' => false]);

            foreach (PermissionCatalog::templates() as $tpl) {
                PermissionTemplate::query()->updateOrCreate(
                    ['slug' => $tpl['slug']],
                    [
                        'name' => $tpl['name'],
                        'description' => $tpl['description'],
                        'permission_keys' => $tpl['permission_keys'],
                        'sort_order' => $tpl['sort_order'],
                    ],
                );
                $stats['templates']++;
            }

            foreach (PermissionCatalog::defaultRoleGrants() as $role => $keys) {
                if ($keys === ['*']) {
                    continue;
                }
                $this->syncRolePermissions($role, $keys, skipCacheClear: true);
                $stats['roles']++;
            }
        });

        $this->clearCaches();

        return $stats;
    }

    /**
     * @return Collection<int, Permission>
     */
    public function activePermissions(): Collection
    {
        return Permission::query()
            ->where('is_active', true)
            ->orderBy('module')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function managementPermissionKeys(): array
    {
        return $this->activePermissions()
            ->reject(fn (Permission $p) => $p->module === 'mobile'
                || str_starts_with($p->key, 'pref.'))
            ->pluck('key')
            ->values()
            ->all();
    }

    /**
     * Grouped structure for the permission management UI.
     *
     * @return array<string, array<string, array<string, list<Permission>>>>
     */
    public function groupedForUi(?string $search = null, bool $managementOnly = false): array
    {
        $permissions = $this->activePermissions();

        if ($managementOnly) {
            $permissions = $permissions->reject(fn (Permission $p) => $p->module === 'mobile'
                || str_starts_with($p->key, 'pref.'));
        }

        if ($search !== null && trim($search) !== '') {
            $q = mb_strtolower(trim($search));
            $permissions = $permissions->filter(function (Permission $p) use ($q) {
                return str_contains(mb_strtolower($p->key), $q)
                    || str_contains(mb_strtolower($p->display_name), $q)
                    || str_contains(mb_strtolower((string) $p->description), $q)
                    || str_contains(mb_strtolower($p->category), $q)
                    || str_contains(mb_strtolower((string) $p->group_label), $q);
            });
        }

        $tabs = [
            'general' => __('app.permissions.tab_general'),
            'web' => __('app.permissions.tab_web'),
            'mobile' => __('app.permissions.tab_mobile'),
            'admin' => __('app.permissions.tab_admin'),
        ];

        if ($managementOnly) {
            unset($tabs['mobile']);
        }

        $grouped = [];
        foreach ($tabs as $module => $label) {
            $modulePerms = $permissions->where('module', $module);
            if ($modulePerms->isEmpty()) {
                continue;
            }

            $categories = [];
            foreach ($modulePerms->groupBy('category') as $category => $items) {
                $groups = [];
                foreach ($items->groupBy(fn (Permission $p) => $p->group_key ?: '_default') as $groupKey => $groupItems) {
                    $groupLabel = $groupItems->first()->group_label ?: $category;
                    $groups[$groupKey] = [
                        'label' => $groupLabel,
                        'permissions' => $groupItems->values()->all(),
                    ];
                }
                $categories[$category] = $groups;
            }

            $grouped[$module] = [
                'label' => $label,
                'categories' => $categories,
                'count' => $modulePerms->count(),
            ];
        }

        return $grouped;
    }

    /**
     * @return list<string>
     */
    public function rolePermissionKeys(string $role): array
    {
        return Cache::remember(self::CACHE_ROLE_KEYS.$role, 3600, function () use ($role) {
            $defaults = PermissionCatalog::defaultRoleGrants()[$role] ?? [];
            if ($defaults === ['*']) {
                return ['*'];
            }

            $fromDb = RolePermission::query()
                ->where('role', $role)
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('permissions.is_active', true)
                ->pluck('permissions.key')
                ->all();

            if ($fromDb !== []) {
                return array_values(array_unique($fromDb));
            }

            return array_values(array_unique($defaults));
        });
    }

    /**
     * @param  list<string>  $keys
     */
    public function syncRolePermissions(string $role, array $keys, bool $skipCacheClear = false): void
    {
        if ($keys === ['*']) {
            return;
        }

        $permissionIds = Permission::query()
            ->whereIn('key', $keys)
            ->where('is_active', true)
            ->pluck('id', 'key');

        RolePermission::query()->where('role', $role)->delete();

        foreach ($permissionIds as $id) {
            RolePermission::query()->create([
                'role' => $role,
                'permission_id' => $id,
            ]);
        }

        if (! $skipCacheClear) {
            Cache::forget(self::CACHE_ROLE_KEYS.$role);
        }
    }

    /**
     * @return Collection<int, PermissionTemplate>
     */
    public function templates(): Collection
    {
        return PermissionTemplate::query()->orderBy('sort_order')->get();
    }

    /**
     * @return list<string>
     */
    public function allActiveKeys(): array
    {
        return Cache::remember(self::CACHE_ALL_KEYS, 3600, fn () => Permission::query()
            ->where('is_active', true)
            ->orderBy('key')
            ->pluck('key')
            ->all());
    }

    public function clearCaches(): void
    {
        Cache::forget(self::CACHE_ALL_KEYS);
        foreach (array_keys(PermissionCatalog::defaultRoleGrants()) as $role) {
            Cache::forget(self::CACHE_ROLE_KEYS.$role);
        }
    }

    /**
     * Resolve effective permissions for a user (for mobile API).
     *
     * @return array<string, bool>
     */
    public function effectivePermissionMap(\App\Models\User $user, RbacService $rbac): array
    {
        $map = [];
        foreach ($this->allActiveKeys() as $key) {
            $map[$key] = $rbac->hasPermission($user, $key);
        }

        return $map;
    }
}
