#!/usr/bin/env bash
# Production deploy for BillX GPS — run from project root on the server.
set -euo pipefail

cd "$(dirname "$0")/.."

if [[ ! -f .env ]]; then
  echo "ERROR: .env missing. Copy .env.production.example and configure before deploy."
  exit 1
fi

if grep -qE '^APP_DEBUG=true' .env 2>/dev/null; then
  echo "WARNING: APP_DEBUG=true in .env — set APP_DEBUG=false before going live."
fi

echo "==> composer install (production, no dev packages)"
composer install --no-dev --optimize-autoloader --no-interaction

if [[ -f package.json ]]; then
  echo "==> npm ci && npm run build"
  npm ci
  npm run build
fi

echo "==> storage link (public disk)"
php artisan storage:link --force 2>/dev/null || true

echo "==> Laravel deploy (migrate + safe cache rebuild)"
php artisan app:deploy --skip-composer

echo ""
echo "==> Deploy complete."
echo "    Ensure these are running on the server:"
echo "    - Cron: * * * * * cd $(pwd) && php artisan schedule:run"
echo "    - Queue: php artisan queue:work --sleep=3 --tries=3"
echo "    - Reload PHP-FPM: sudo systemctl reload php8.2-fpm"
echo "    - Health check: curl -fsS \"\${APP_URL}/up\""
