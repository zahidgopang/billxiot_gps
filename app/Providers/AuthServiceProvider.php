<?php

namespace App\Providers;

use App\Enums\AppRole;
use App\Models\Client;
use App\Models\Device;
use App\Models\User;
use App\Services\Authorization\RbacService;
use App\Services\Authorization\TenantScopeService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        $rbac = fn () => app(RbacService::class);
        $tenant = fn () => app(TenantScopeService::class);

        // Legacy: vendor admin + super admin (admin/* routes)
        Gate::define('admin', fn (User $user) => $rbac()->isSuperAdmin($user) || $rbac()->isVendorAdmin($user));

        Gate::define('super-admin', fn (User $user) => $rbac()->isSuperAdmin($user));

        Gate::define('panel.access', fn (User $user) => $rbac()->canAccessPanel($user));

        Gate::define('client-panel', fn (User $user) => $rbac()->roleOf($user) === AppRole::Client);

        Gate::define('permission', fn (User $user, string $permission) => $rbac()->hasPermission($user, $permission));

        Gate::define('manage-user', fn (User $actor, User $target) => $tenant()->canManageUser($actor, $target));

        Gate::define('manage-device', fn (User $actor, Device $target) => $tenant()->canManageDevice($actor, $target));

        Gate::define('manage-client', fn (User $actor, Client $client) => $rbac()->isSuperAdmin($actor)
            || ($rbac()->hasPermission($actor, 'clients.manage')
                && in_array((int) $client->id, $tenant()->visibleClientIds($actor), true)));
    }
}
