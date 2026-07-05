<?php

namespace App\Console\Commands;

use App\Enums\AppRole;
use App\Models\User;
use App\Services\Authorization\RbacService;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Console\Command;

class EnsureUserEssentialsCommand extends Command
{
    protected $signature = 'permissions:ensure-user-essentials {--dry-run : Report changes without saving}';

    protected $description = 'Ensure every end user keeps essential fleet permissions (live map, reports, commands)';

    public function handle(RbacService $rbac): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;

        User::query()
            ->whereAppRole(AppRole::EndUser)
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($rbac, $dryRun, &$updated): void {
                foreach ($users as $user) {
                    if ($dryRun) {
                        $overrides = $rbac->permissionOverrides($user);
                        $wouldChange = collect($overrides)
                            ->filter(fn ($enabled, $key) => ($enabled === false && PermissionCatalog::isEssentialFleetPermission((string) $key))
                                || ($key === 'web.map.live_only' && $enabled === true))
                            ->isNotEmpty();

                        if ($wouldChange) {
                            $updated++;
                            $this->line(sprintf('Would update user #%d (%s)', $user->id, $user->email));
                        }

                        continue;
                    }

                    if ($rbac->purgeEssentialDenyOverrides($user)) {
                        $updated++;
                    }
                }
            });

        $this->info($dryRun
            ? "Dry run: {$updated} end user(s) would be updated."
            : "Updated {$updated} end user(s). Run php artisan permissions:sync to refresh role defaults.");

        return self::SUCCESS;
    }
}
