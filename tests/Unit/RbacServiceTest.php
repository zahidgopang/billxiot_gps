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

    public function test_end_user_cannot_be_denied_essential_fleet_permissions(): void
    {
        $this->bindRolePermissions([
            AppRole::EndUser->value => ['maps.view'],
        ]);

        $user = $this->userWithRole(AppRole::EndUser);
        $user->patchTraccarAppAttributes([
            TraccarAppFields::KEY_PERMISSIONS => [
                'web.reports.view' => false,
                'web.vehicles.send_commands' => false,
                'web.map.sidebar.vehicle_list' => false,
            ],
        ]);

        $rbac = app(RbacService::class);

        $this->assertTrue($rbac->hasPermission($user, 'web.reports.view'));
        $this->assertTrue($rbac->hasPermission($user, 'web.vehicles.send_commands'));
        $this->assertTrue($rbac->hasPermission($user, 'web.map.sidebar.vehicle_list'));
        $this->assertContains('web.reports.view', $rbac->grantedPermissionsFor($user));
    }

    public function test_end_user_deny_override_is_ignored_when_syncing_permissions(): void
    {
        $this->bindRolePermissions([
            AppRole::EndUser->value => ['maps.view'],
        ]);

        $user = $this->userWithRole(AppRole::EndUser);

        app(RbacService::class)->syncPermissionOverrides($user, [
            'web.reports.view' => '0',
            'web.vehicles.immobilizer' => false,
        ], validKeys: [
            'web.reports.view',
            'web.vehicles.immobilizer',
            'web.geofence.view',
        ]);

        $this->assertTrue(app(RbacService::class)->hasPermission($user, 'web.reports.view'));
        $this->assertTrue(app(RbacService::class)->hasPermission($user, 'web.vehicles.immobilizer'));
        $this->assertArrayNotHasKey('web.reports.view', app(RbacService::class)->permissionOverrides($user));
    }

    public function test_purge_essential_deny_overrides_removes_stored_denies(): void
    {
        $this->bindRolePermissions([
            AppRole::EndUser->value => ['maps.view'],
        ]);

        $user = $this->userWithRole(AppRole::EndUser);
        $user->patchTraccarAppAttributes([
            TraccarAppFields::KEY_PERMISSIONS => [
                'web.reports.view' => false,
                'web.map.live_only' => true,
                'web.geofence.view' => false,
            ],
        ]);

        $rbac = app(RbacService::class);

        $this->assertTrue($rbac->purgeEssentialDenyOverrides($user));
        $overrides = $rbac->permissionOverrides($user);
        $this->assertArrayNotHasKey('web.reports.view', $overrides);
        $this->assertArrayNotHasKey('web.map.live_only', $overrides);
        $this->assertFalse($overrides['web.geofence.view']);
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
