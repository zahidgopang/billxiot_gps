<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AndroidAppController extends Controller
{
    public function show(): View
    {
        $apkRelative = config('contact.android_apk', 'android_apk/app-release.apk');
        $apkPath = public_path($apkRelative);

        return view('frontend.android-app', [
            'apkUrl' => asset($apkRelative),
            'apkAvailable' => file_exists($apkPath),
            'apkSizeMb' => file_exists($apkPath)
                ? round(filesize($apkPath) / 1024 / 1024, 1)
                : null,
            'officialSite' => rtrim((string) config('app.url', 'https://falconeyegps.com'), '/'),
        ]);
    }
}
