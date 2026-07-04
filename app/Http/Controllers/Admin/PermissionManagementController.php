<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppRole;
use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\Authorization\PermissionCatalogService;
use App\Services\Authorization\RbacService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermissionManagementController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private PermissionCatalogService $catalog,
        private RbacService $rbac,
        private AdminAuditService $audit,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        abort_unless($this->rbac->isSuperAdmin($request->user()), 403);

        $mode = $request->query('mode', 'role');
        if (! in_array($mode, ['role', 'user'], true)) {
            $mode = 'role';
        }

        $search = $request->query('q');
        $grouped = $this->catalog->groupedForUi($search, managementOnly: true);
        $totalCount = count($this->catalog->managementPermissionKeys());

        if ($mode === 'user') {
            return $this->userIndex($request, $grouped, $search, $totalCount);
        }

        return $this->roleIndex($request, $grouped, $search, $totalCount);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->rbac->isSuperAdmin($request->user()), 403);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', array_column(AppRole::cases(), 'value'))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $role = $validated['role'];
        if ($role === AppRole::SuperAdmin->value) {
            return back()->with('error', __('app.permissions.super_admin_locked'));
        }

        $keys = array_values(array_unique($validated['permissions'] ?? []));
        $visibleKeys = $this->catalog->managementPermissionKeys();
        $keys = array_values(array_intersect($keys, $visibleKeys));

        $existing = $this->catalog->rolePermissionKeys($role);
        if ($existing === ['*']) {
            $existing = $this->catalog->allActiveKeys();
        }
        $hiddenPreserved = array_values(array_diff($existing, $visibleKeys));
        $keys = array_values(array_unique(array_merge($keys, $hiddenPreserved)));

        $this->catalog->syncRolePermissions($role, $keys);

        $this->audit->log('permissions.role_updated', "Updated permissions for role {$role}", null, [
            'role' => $role,
            'count' => count($keys),
        ]);

        return redirect()
            ->route($this->panelPrefix().'.permissions.index', [
                'mode' => 'role',
                'role' => $role,
            ])
            ->with('success', __('app.permissions.saved'));
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->rbac->isSuperAdmin($request->user()), 403);

        if ($this->rbac->isSuperAdmin($user)) {
            return back()->with('error', __('app.permissions.user_super_admin_locked'));
        }

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $checked = array_flip(array_values(array_unique($validated['permissions'] ?? [])));
        $role = $this->rbac->roleOf($user)->value;
        $overrides = [];

        foreach ($this->catalog->managementPermissionKeys() as $key) {
            $roleGrants = $this->rbac->roleGrantsPermission($role, $key);
            $wants = isset($checked[$key]);
            if ($wants !== $roleGrants) {
                $overrides[$key] = $wants ? '1' : '0';
            }
        }

        $this->rbac->syncPermissionOverrides($user, $overrides);

        $this->audit->log('permissions.user_updated', "Updated permissions for user {$user->email}", $user, [
            'user_id' => $user->id,
            'override_count' => count($overrides),
        ]);

        return redirect()
            ->route($this->panelPrefix().'.permissions.index', [
                'mode' => 'user',
                'role' => $this->rbac->roleOf($user)->value,
                'user_id' => $user->id,
            ])
            ->with('success', __('app.permissions.user_saved'));
    }

    public function users(Request $request): JsonResponse
    {
        abort_unless($this->rbac->isSuperAdmin($request->user()), 403);

        $roleValue = (string) $request->query('role', AppRole::EndUser->value);
        if (! in_array($roleValue, $this->assignableRoleValues(), true)) {
            $roleValue = AppRole::EndUser->value;
        }

        $users = $this->usersForPermissionRole(
            AppRole::from($roleValue),
            $request->query('q')
        );

        return response()->json([
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'label' => trim($user->name.' — '.$user->email),
            ])->values(),
        ]);
    }

    public function templateKeys(Request $request, string $slug): JsonResponse
    {
        abort_unless($this->rbac->isSuperAdmin($request->user()), 403);

        $template = $this->catalog->templates()->firstWhere('slug', $slug);
        if (! $template) {
            return response()->json(['keys' => []], 404);
        }

        $valid = $this->catalog->allActiveKeys();

        return response()->json([
            'keys' => array_values(array_intersect($template->permission_keys ?? [], $valid)),
        ]);
    }

    private function roleIndex(Request $request, array $grouped, ?string $search, int $totalCount): View
    {
        $role = $request->query('role', AppRole::Admin->value);
        if (! in_array($role, $this->assignableRoleValues(), true)) {
            $role = AppRole::Admin->value;
        }

        $granted = $this->catalog->rolePermissionKeys($role);
        if ($granted === ['*']) {
            $granted = $this->catalog->allActiveKeys();
        }
        $managementKeys = $this->catalog->managementPermissionKeys();
        $grantedVisible = array_values(array_intersect($granted, $managementKeys));

        return view('admin.permissions.index', [
            'panel' => $this->panelPrefix(),
            'mode' => 'role',
            'selectedRole' => $role,
            'roles' => $this->assignableRolesForPermissions(),
            'grouped' => $grouped,
            'grantedKeys' => array_flip($grantedVisible),
            'templates' => $this->catalog->templates(),
            'search' => $search,
            'totalCount' => $totalCount,
            'grantedCount' => count($grantedVisible),
            'selectedUser' => null,
            'usersForSelect' => collect(),
            'roleUserCounts' => [],
            'userOverrideStates' => [],
            'roleGrantedKeys' => [],
            'effectiveKeys' => [],
            'overrideCount' => 0,
        ]);
    }

    private function userIndex(Request $request, array $grouped, ?string $search, int $totalCount): View|RedirectResponse
    {
        $roleFilter = (string) $request->query('role', '');
        if ($roleFilter === '' || ! in_array($roleFilter, $this->assignableRoleValues(), true)) {
            $roleFilter = $this->defaultUserPermissionRole();
        }
        $roleEnum = AppRole::from($roleFilter);

        $usersForSelect = $this->usersForPermissionRole($roleEnum, null);
        $roleUserCounts = $this->roleUserCounts();

        $selectedUser = null;
        $userId = (int) $request->query('user_id', 0);
        if ($userId > 0) {
            $candidate = User::query()->find($userId);
            if ($candidate
                && ! $this->rbac->isSuperAdmin($candidate)
                && $this->rbac->roleOf($candidate) === $roleEnum) {
                $selectedUser = $candidate;
            }
        }
        if (! $selectedUser && $usersForSelect->isNotEmpty()) {
            $selectedUser = $usersForSelect->first();
        }

        // If role changed without user_id, redirect so URL matches selection.
        if ($request->query('user_id') === null && $selectedUser && $usersForSelect->isNotEmpty()) {
            return redirect()->route($this->panelPrefix().'.permissions.index', [
                'mode' => 'user',
                'role' => $roleFilter,
                'user_id' => $selectedUser->id,
            ]);
        }

        $effectiveKeys = [];
        $grantedCount = 0;

        if ($selectedUser) {
            foreach ($this->catalog->managementPermissionKeys() as $key) {
                $effectiveKeys[$key] = $this->rbac->hasPermission($selectedUser, $key);
                if ($effectiveKeys[$key]) {
                    $grantedCount++;
                }
            }
        }

        return view('admin.permissions.index', [
            'panel' => $this->panelPrefix(),
            'mode' => 'user',
            'selectedRole' => $roleFilter,
            'roles' => $this->assignableRolesForPermissions(),
            'roleUserCounts' => $roleUserCounts,
            'grouped' => $grouped,
            'grantedKeys' => array_flip(array_keys(array_filter($effectiveKeys))),
            'templates' => collect(),
            'search' => $search,
            'totalCount' => $totalCount,
            'grantedCount' => $grantedCount,
            'selectedUser' => $selectedUser,
            'usersForSelect' => $usersForSelect,
            'userOverrideStates' => [],
            'roleGrantedKeys' => [],
            'effectiveKeys' => $effectiveKeys,
            'overrideCount' => 0,
        ]);
    }

    /**
     * Roles that can be configured in permission management (super admin excluded).
     *
     * @return \Illuminate\Support\Collection<int, array{value: string, label: string}>
     */
    private function assignableRolesForPermissions()
    {
        return collect(AppRole::cases())
            ->reject(fn (AppRole $role) => $role === AppRole::SuperAdmin)
            ->map(fn (AppRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ])
            ->values();
    }

    /**
     * @return list<string>
     */
    private function assignableRoleValues(): array
    {
        return $this->assignableRolesForPermissions()
            ->pluck('value')
            ->all();
    }

    private function defaultUserPermissionRole(): string
    {
        foreach ($this->roleUserCounts() as $role => $count) {
            if ($count > 0) {
                return $role;
            }
        }

        return AppRole::EndUser->value;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function usersForPermissionRole(AppRole $role, ?string $search)
    {
        return User::query()
            ->excludeSuperAdmins()
            ->when($search, function ($q, $term) {
                $like = '%'.addcslashes(trim($term), '%_').'%';
                $q->where(function ($w) use ($like) {
                    $w->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->filter(fn (User $user) => $this->rbac->roleOf($user) === $role)
            ->take(200)
            ->values();
    }

    /**
     * @return array<string, int>
     */
    private function roleUserCounts(): array
    {
        $counts = array_fill_keys($this->assignableRoleValues(), 0);

        User::query()
            ->excludeSuperAdmins()
            ->orderBy('name')
            ->limit(1000)
            ->get()
            ->each(function (User $user) use (&$counts) {
                $role = $this->rbac->roleOf($user)->value;
                if (array_key_exists($role, $counts)) {
                    $counts[$role]++;
                }
            });

        return $counts;
    }
}
