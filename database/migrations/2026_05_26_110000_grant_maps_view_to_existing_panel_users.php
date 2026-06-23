<?php

use App\Enums\AppRole;
use App\Models\User;
use App\Services\Authorization\RbacService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $rbac = app(RbacService::class);

        User::query()->orderBy('id')->chunkById(100, function ($users) use ($rbac) {
            foreach ($users as $user) {
                $role = $rbac->roleOf($user);

                if (! in_array($role, [AppRole::Admin, AppRole::Client], true)) {
                    continue;
                }

                $overrides = $rbac->permissionOverrides($user);

                if (array_key_exists('maps.view', $overrides)) {
                    continue;
                }

                $rbac->syncMapsViewPermission($user, true);
            }
        });
    }

    public function down(): void
    {
        // Permission overrides are left as-is on rollback.
    }
};
