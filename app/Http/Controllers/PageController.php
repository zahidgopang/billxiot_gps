<?php
// app/Http/Controllers/PageController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    public function company()
    {
        return view('frontend.pages.company');
    }

    public function about()
    {
        return view('frontend.pages.about');
    }

    public function careers()
    {
        return view('frontend.pages.careers');
    }

    public function press()
    {
        return view('frontend.pages.press');
    }

    public function blog()
    {
        return view('frontend.pages.blog');
    }

    public function help()
    {
        return view('frontend.pages.help');
    }

    public function docs()
    {
        return view('frontend.pages.docs');
    }

    public function api()
    {
        return view('frontend.pages.api');
    }

    public function status()
    {
        return view('frontend.pages.status');
    }

    public function terms()
    {
        return view('frontend.pages.terms');
    }

    public function privacy()
    {
        return view('frontend.pages.privacy');
    }

    public function security()
    {
        return view('frontend.pages.security');
    }

    public function cookies()
    {
        return view('frontend.pages.cookies');
    }
}
