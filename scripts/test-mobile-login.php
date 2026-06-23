<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create(
    '/api/login',
    'POST',
    [],
    [],
    [],
    ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
    json_encode(['email' => 'test@test.com', 'password' => 'x'])
);

$response = $kernel->handle($request);
echo $response->getStatusCode().PHP_EOL;
echo $response->getContent().PHP_EOL;
