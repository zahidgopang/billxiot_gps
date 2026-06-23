<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Production-safe cache rebuild — does NOT flush application cache (sessions, map grants, live cursors).
 *
 * Never run `php artisan optimize:clear` on a live site without --except=cache:
 * the default command runs cache:clear which can log everyone out (Redis) and break maps.
 */
class SafeDeployCacheCommand extends Command
{
    protected $signature = 'app:deploy-cache
                            {--maintenance : Put the app in maintenance mode while rebuilding caches}
                            {--secret= : Bypass secret when using --maintenance}';

    protected $description = 'Safely rebuild Laravel caches for production (preserves sessions and app cache)';

    public function handle(): int
    {
        $this->warn('Do NOT use `php artisan optimize:clear` on live traffic.');
        $this->line('That command runs cache:clear and can log users out or break the live map.');

        if (! $this->validateSessionCacheIsolation()) {
            return self::FAILURE;
        }

        $usedMaintenance = false;

        if ($this->option('maintenance')) {
            $secret = $this->option('secret') ?: 'deploy-'.bin2hex(random_bytes(4));
            Artisan::call('down', [
                '--retry' => 60,
                '--secret' => $secret,
            ]);
            $usedMaintenance = true;
            $this->info('Maintenance mode enabled.');
            $this->line('Bypass URL secret: '.$secret);
        }

        try {
            $this->purgeBootstrapCacheFiles();

            Artisan::call('package:discover');
            $this->output->write(Artisan::output());

            $this->info('Clearing config, routes, views, and compiled bootstrap (keeping application cache)…');
            Artisan::call('optimize:clear', ['--except' => 'cache']);
            $this->output->write(Artisan::output());

            $this->info('Rebuilding production caches…');
            Artisan::call('optimize');
            $this->output->write(Artisan::output());

            $this->newLine();
            $this->info('Deploy cache rebuild complete.');
            $this->line('Reload PHP-FPM when possible: sudo systemctl reload php8.2-fpm');
        } finally {
            if ($usedMaintenance) {
                Artisan::call('up');
                $this->info('Maintenance mode disabled.');
            }
        }

        return self::SUCCESS;
    }

    private function validateSessionCacheIsolation(): bool
    {
        $sessionDriver = (string) config('session.driver');
        $cacheStore = (string) config('cache.default');

        if ($sessionDriver === 'cache') {
            $this->error('SESSION_DRIVER=cache stores logins inside the cache store.');
            $this->error('cache:clear (inside optimize:clear) will log everyone out.');
            $this->line('Use SESSION_DRIVER=database or file on production.');

            return false;
        }

        if ($sessionDriver === 'redis' && $cacheStore === 'redis') {
            $sessionConn = config('session.connection') ?: 'default';
            $cacheConn = config('cache.stores.redis.connection', 'cache');

            $sessionDb = (int) config("database.redis.{$sessionConn}.database", 0);
            $cacheDb = (int) config("database.redis.{$cacheConn}.database", 1);

            if ($sessionDb === $cacheDb) {
                $this->error('Redis sessions and cache use the same database index ('.$sessionDb.').');
                $this->error('cache:clear flushes that entire Redis DB and destroys all sessions.');
                $this->line('Set REDIS_CACHE_DB=1 (or another index) separate from REDIS_DB in .env.');

                return false;
            }
        }

        return true;
    }

    private function purgeBootstrapCacheFiles(): void
    {
        foreach (glob(base_path('bootstrap/cache/*.php')) ?: [] as $file) {
            @unlink($file);
        }
    }
}
