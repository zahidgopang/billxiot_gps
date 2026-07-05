<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Authorization\RbacService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, RbacService $rbac): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $home = route($rbac->panelRouteFor($request->user()));

        return redirect()->intended($home);
    }

    /**
     * Destroy the authenticated session on this browser only.
     *
     * Uses logoutCurrentDevice() so other browsers/tabs for the same account
     * stay signed in (no shared remember_token rotation).
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logoutCurrentDevice();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
