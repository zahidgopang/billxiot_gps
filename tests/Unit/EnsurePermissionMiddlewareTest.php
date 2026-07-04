<?php

namespace Tests\Unit;

use App\Enums\AppRole;
use App\Http\Middleware\EnsurePermission;
use App\Models\User;
use App\Services\Authorization\PermissionCatalogService;
use App\Support\Traccar\TraccarAppFields;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnsurePermissionMiddlewareTest extends TestCase
{
    public function test_middleware_allows_any_matching_permission(): void
    {
        $this->bindRolePermissions([
            AppRole::Admin->value => ['web.map.live_only'],
        ]);

        $request = Request::create('/admin/tracking');
        $request->setUserResolver(fn () => $this->userWithRole(AppRole::Admin));

        $response = app(EnsurePermission::class)->handle(
            $request,
            fn () => response('ok'),
            'web.map.open',
            'web.map.live_only',
        );

        $this->assertSame('ok', $response->getContent());
    }

    public function test_middleware_denies_when_no_permission_matches(): void
    {
        $this->bindRolePermissions([
            AppRole::Admin->value => ['maps.view'],
        ]);

        $request = Request::create('/admin/tracking');
        $request->setUserResolver(fn () => $this->userWithRole(AppRole::Admin));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('You do not have permission to perform this action.');

        app(EnsurePermission::class)->handle(
            $request,
            fn () => response('ok'),
            'web.map.open',
            'web.map.live_only',
        );
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

    private function userWithRole(AppRole $role): User
    {
        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'administrator' => 0,
            'attributes' => '{}',
        ]);
        $user->patchTraccarAppAttributes([TraccarAppFields::KEY_ROLE => $role->value]);

        return $user;
    }
}
