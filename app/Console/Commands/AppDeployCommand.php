<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Run after `git pull` on production — safe replacement for optimize / optimize:clear.
 */
class AppDeployCommand extends Command
{
    protected $signature = 'app:deploy
                            {--skip-composer : Skip composer install}
                            {--skip-migrate : Skip database migrations}';

    protected $description = 'Production deploy after git pull (composer, migrate, safe cache rebuild)';

    public function handle(): int
    {
        if (! $this->option('skip-composer')) {
            $this->info('Installing PHP dependencies…');
            $exit = $this->runComposerInstall();

            if ($exit !== 0) {
                $this->error('composer install failed.');

                return self::FAILURE;
            }
        }

        if (! $this->option('skip-migrate')) {
            $this->info('Running migrations…');
            Artisan::call('migrate', ['--force' => true]);
            $this->output->write(Artisan::output());
        }

        $recover = Artisan::call('app:recover', ['--rebuild' => true]);
        $this->output->write(Artisan::output());

        if ($recover !== 0) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Deploy finished.');
        $this->line('Reload PHP-FPM: sudo systemctl reload php8.2-fpm');

        return self::SUCCESS;
    }

    private function runComposerInstall(): int
    {
        $composer = $this->findComposer();

        if ($composer === null) {
            $this->error('composer binary not found in PATH.');

            return 1;
        }

        passthru(
            escapeshellarg($composer).' install --no-dev --optimize-autoloader --no-interaction',
            $exit
        );

        return (int) $exit;
    }

    private function findComposer(): ?string
    {
        $paths = ['composer', 'composer.phar'];

        foreach ($paths as $bin) {
            $cmd = PHP_OS_FAMILY === 'Windows' ? 'where '.$bin : 'command -v '.$bin;
            $result = shell_exec($cmd);

            if (is_string($result) && trim($result) !== '') {
                return trim(explode("\n", trim($result))[0]);
            }
        }

        $local = base_path('composer.phar');

        return is_file($local) ? $local : null;
    }
}
