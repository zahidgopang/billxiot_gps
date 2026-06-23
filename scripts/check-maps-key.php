<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$key = config('services.google.maps_key');
if (! $key) {
    echo "Google Maps key MISSING\n";
    exit(1);
}

$prefix = substr($key, 0, 10).'…';
echo "Google Maps key configured ({$prefix}, ".strlen($key).' chars)'."\n";

$envPath = base_path('.env');
if (is_readable($envPath)) {
    $count = 0;
    foreach (file($envPath, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match('/^GOOGLE_MAPS_API_KEY=/', trim($line))) {
            $count++;
        }
    }
    if ($count > 1) {
        echo "WARNING: .env has {$count} GOOGLE_MAPS_API_KEY lines — only the last one is used.\n";
        exit(1);
    }
}
