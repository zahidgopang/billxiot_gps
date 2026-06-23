<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemap = rtrim(config('app.url'), '/').'/sitemap.xml';

        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /client/',
            'Disallow: /user/',
            'Disallow: /dashboard',
            'Disallow: /profile',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /demo/',
            'Disallow: /locale/',
            'Disallow: /device/',
            '',
            'Sitemap: '.$sitemap,
        ];

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
