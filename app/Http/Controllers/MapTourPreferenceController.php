<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MapTourPreferenceController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:repeat,dismiss'],
        ]);

        $request->user()->setMapTourPreference($validated['mode']);

        return response()->json([
            'ok' => true,
            'mode' => $validated['mode'],
            'show_on_load' => $request->user()->shouldShowMapTourOnLoad(),
        ]);
    }
}
