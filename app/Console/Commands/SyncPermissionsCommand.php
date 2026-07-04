<?php

namespace App\Console\Commands;

use App\Services\Authorization\PermissionCatalogService;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Sync permission catalog and default role grants from the developer registry';

    public function handle(PermissionCatalogService $catalog): int
    {
        $stats = $catalog->syncCatalog();

        $this->info(sprintf(
            'Synced %d permissions, %d templates, %d role defaults.',
            $stats['permissions'],
            $stats['templates'],
            $stats['roles'],
        ));

        return self::SUCCESS;
    }
}
