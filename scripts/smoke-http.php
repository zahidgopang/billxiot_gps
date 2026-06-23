<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

foreach (['/', '/up', '/login'] as $path) {
    $request = Illuminate\Http\Request::create($path, 'GET');
    $response = $app->handleRequest($request);
    $code = $response->getStatusCode();
    $note = $path === '/up' && in_array($code, [200, 503], true) ? ' (ok)' : '';
    echo "{$path} => {$code}{$note}".PHP_EOL;
}
