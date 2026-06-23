<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Emergency recovery after a bad deploy or optimize:clear on live traffic.
 *
 * Fixes HTTP 500 caused by stale bootstrap/cache files referencing removed packages.
 */
class AppRecoverCommand extends Command
{
    protected $signature = 'app:recover
                            {--rebuild : Rebuild config/route/view caches after recovery}';

    protected $description = 'Fix HTTP 500: purge stale bootstrap caches and rediscover packages (keeps sessions)';

    public function handle(): int
    {
        $this->warn('Recovering application bootstrap (sessions and app cache are preserved)…');

        $this->purgeBootstrapCacheFiles();

        Artisan::call('package:discover');
        $this->output->write(Artisan::output());

        Artisan::call('optimize:clear', ['--except' => 'cache']);
        $this->output->write(Artisan::output());

        if ($this->option('rebuild')) {
            Artisan::call('optimize');
            $this->output->write(Artisan::output());
        }

        $missing = $this->missingPackageProviders();

        if ($missing !== []) {
            $this->error('Some discovered providers are missing from vendor/ (run composer install):');
            foreach ($missing as $provider) {
                $this->line('  - '.$provider);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Recovery complete.');
        $this->line('If the site is still down, run: composer install --no-dev --optimize-autoloader');
        $this->line('Then: php artisan app:recover --rebuild');

        return self::SUCCESS;
    }

    private function purgeBootstrapCacheFiles(): void
    {
        $dir = base_path('bootstrap/cache');
        $removed = 0;

        foreach (glob($dir.'/*.php') ?: [] as $file) {
            if (@unlink($file)) {
                $removed++;
            }
        }

        $this->line("Removed {$removed} stale bootstrap cache file(s).");
    }

    /**
     * @return list<string>
     */
    private function missingPackageProviders(): array
    {
        $packagesFile = base_path('bootstrap/cache/packages.php');

        if (! is_file($packagesFile)) {
            return [];
        }

        $packages = require $packagesFile;
        $missing = [];

        foreach ($packages as $package) {
            foreach ($package['providers'] ?? [] as $provider) {
                if (! is_string($provider) || $provider === '') {
                    continue;
                }

                if (! class_exists($provider)) {
                    $missing[] = $provider;
                }
            }
        }

        return array_values(array_unique($missing));
    }
}
