<?php

/**
 * Test mobile login with real credentials.
 *
 * Usage:
 *   php scripts/test-login-credentials.php ali@user.com 12345678
 *   API_TEST_BASE=https://gps-traccar.devhost php scripts/test-login-credentials.php ali@user.com 12345678
 */

require __DIR__.'/../vendor/autoload.php';

$email = $argv[1] ?? 'ali@user.com';
$password = $argv[2] ?? '12345678';
$remoteBase = getenv('API_TEST_BASE');

if ($remoteBase) {
    $url = rtrim($remoteBase, '/').'/api/login';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'email' => $email,
            'password' => $password,
            'device_name' => 'cli-test',
            'remember' => true,
        ]),
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Remote: {$url}\n";
    echo "HTTP {$status}\n{$body}\n";
    exit($status >= 200 && $status < 300 ? 0 : 1);
}

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create(
    '/api/login',
    'POST',
    [],
    [],
    [],
    ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
    json_encode([
        'email' => $email,
        'password' => $password,
        'device_name' => 'cli-test',
        'remember' => true,
    ])
);

$response = $kernel->handle($request);
echo 'Local kernel test'."\n";
echo $response->getStatusCode()."\n";
echo $response->getContent()."\n";

exit($response->getStatusCode() >= 200 && $response->getStatusCode() < 300 ? 0 : 1);
