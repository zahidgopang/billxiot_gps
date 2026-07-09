<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'env='.app()->environment().PHP_EOL;
echo 'protection='.(config('assets.protection_enabled') ? '1' : '0').PHP_EOL;
echo 'ASSET_PROTECTION='.var_export(env('ASSET_PROTECTION'), true).PHP_EOL;
