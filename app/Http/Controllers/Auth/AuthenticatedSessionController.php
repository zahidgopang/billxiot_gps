<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Authorization\RbacService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
     * Destroy an authenticated session.
     *
     * Multi-device aware: logging out on this device must NOT sign the same
     * account out on other laptops/phones. Laravel's guard logout rotates the
     * shared remember_token, which would invalidate "remember me" everywhere, so
     * we capture and restore it after invalidating only the current session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $guard = Auth::guard('web');
        $user = $guard->user();
        $rememberToken = $user?->getRememberToken();

        $guard->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($user && ! empty($rememberToken)) {
            try {
                $user->setRememberToken($rememberToken);
                $user->save();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect('/');
    }
}
