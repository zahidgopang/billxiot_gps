<?php

namespace Tests\Unit;

use App\Enums\AppRole;
use App\Models\User;
use App\Services\Authorization\PermissionCatalogService;
use App\Services\Authorization\RbacService;
use App\Support\Traccar\TraccarAppFields;
use Tests\TestCase;

class RbacServiceTest extends TestCase
{
    public function test_user_deny_override_removes_role_permission(): void
    {
        $this->bindRolePermissions([
            AppRole::Admin->value => ['maps.view', 'web.map.workspace'],
        ]);

        $user = $this->userWithRole(AppRole::Admin);
        $user->patchTraccarAppAttributes([
            TraccarAppFields::KEY_PERMISSIONS => ['web.map.workspace' => false],
        ]);

        $rbac = app(RbacService::class);

        $this->assertTrue($rbac->hasPermission($user, 'maps.view'));
        $this->assertFalse($rbac->hasPermission($user, 'web.map.workspace'));
    }

    public function test_user_grant_override_adds_permission_not_in_role(): void
    {
        $this->bindRolePermissions([
            AppRole::Client->value => ['maps.view'],
        ]);

        $user = $this->userWithRole(AppRole::Client);
        $user->patchTraccarAppAttributes([
            TraccarAppFields::KEY_PERMISSIONS => ['web.map.live_only' => true],
        ]);

        $rbac = app(RbacService::class);

        $this->assertTrue($rbac->hasPermission($user, 'maps.view'));
        $this->assertTrue($rbac->hasPermission($user, 'web.map.live_only'));
    }

    public function test_granted_permissions_apply_user_denies_and_grants(): void
    {
        $this->bindRolePermissions([
            AppRole::Admin->value => ['maps.view', 'web.map.workspace'],
        ]);

        $user = $this->userWithRole(AppRole::Admin);
        $user->patchTraccarAppAttributes([
            TraccarAppFields::KEY_PERMISSIONS => [
                'web.map.workspace' => false,
                'web.map.live_only' => true,
            ],
        ]);

        $granted = app(RbacService::class)->grantedPermissionsFor($user);

        $this->assertContains('maps.view', $granted);
        $this->assertContains('web.map.live_only', $granted);
        $this->assertNotContains('web.map.workspace', $granted);
    }

    public function test_super_admin_cannot_be_limited_by_user_overrides(): void
    {
        $this->bindRolePermissions([]);

        $user = $this->userWithRole(AppRole::SuperAdmin, administrator: true);
        $user->patchTraccarAppAttributes([
            TraccarAppFields::KEY_PERMISSIONS => ['permissions.manage' => false],
        ]);

        $this->assertTrue(app(RbacService::class)->hasPermission($user, 'permissions.manage'));
    }

    /**
     * @param  array<string, list<string>>  $permissionsByRole
     */
    private function bindRolePermissions(array $permissionsByRole): void
    {
        $this->app->instance(PermissionCatalogService::class, new class($permissionsByRole) extends PermissionCatalogService
        {
            public function __construct(private array $permissionsByRole) {}

            public function rolePermissionKeys(string $role): array
            {
                return $this->permissionsByRole[$role] ?? [];
            }
        });
    }

    private function userWithRole(AppRole $role, bool $administrator = false): User
    {
        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'administrator' => $administrator ? 1 : 0,
            'attributes' => '{}',
        ]);
        $user->role = $role->value;

        return $user;
    }
}
