<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$devices = App\Models\Device::query()->orderByDesc('id')->limit(30)->get();
foreach ($devices as $d) {
    if ($d->map_icon_source !== 'custom' && empty($d->map_custom_icon)) {
        continue;
    }
    $payload = $d->mapAppearancePayload();
    echo "id={$d->id} source={$d->map_icon_source} path={$d->map_custom_icon}\n";
    echo 'url='.($payload['map_custom_icon_url'] ?? 'null')."\n";
    echo 'uses='.($d->usesCustomMapIcon() ? '1' : '0')."\n\n";
}
