<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::query()
    ->where('id', (int) (getenv('API_TEST_USER') ?: 13))
    ->first()
    ?? App\Models\User::query()->where('email', 'like', '%@%')->first();

if (! $user) {
    fwrite(STDERR, "No user found\n");
    exit(1);
}

$token = $user->createToken('api-test')->plainTextToken;
$base = getenv('API_TEST_BASE') ?: 'http://127.0.0.1:8765/api';

$paths = ['/dashboard', '/devices', '/geofences', '/profile'];

echo "User: {$user->id} {$user->email}\n";
echo "Base: {$base}\n\n";

foreach ($paths as $path) {
    $url = $base . $path;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $preview = is_string($body) ? substr($body, 0, 200) : '';
    echo "{$path} => HTTP {$status}\n{$preview}\n\n";
}
