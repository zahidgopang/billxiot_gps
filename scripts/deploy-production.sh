#!/usr/bin/env bash
# Production deploy for FalconEyeGPS — run from project root after git pull.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> git pull (optional — skip if already pulled)"
# git pull --ff-only

echo "==> composer install (production, no dev packages)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Laravel deploy (migrate + safe cache rebuild)"
php artisan app:deploy --skip-composer

echo "==> Done. Reload PHP-FPM if needed:"
echo "    sudo systemctl reload php8.2-fpm"
