<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\Auth\UserPasswordVerifier;
use App\Services\Authorization\RbacService;
use App\Services\DeviceSubscriptionService;
use App\Services\UserAvatarService;
use App\Services\UserDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function dashboard(Request $request, UserDashboardService $dashboard, RbacService $rbac)
    {
        $user = auth()->user();

        // Panel roles (client/admin) must not render the end-user dashboard — send them
        // to their own panel so /user/dashboard never 500s for fleet managers.
        if ($rbac->canAccessPanel($user)) {
            return redirect()->route($rbac->panelRouteFor($user));
        }

        $stats = $dashboard->getDashboardShell($user);

        return view('user.dashboard', array_merge($stats, [
            'emailVerified' => session()->has('email_verified'),
            'dashboardService' => $dashboard,
            'subscriptionService' => app(DeviceSubscriptionService::class),
            'deviceAccessMap' => app(\App\Services\DeviceAccessService::class)
                ->evaluateMany($user, $stats['devices']),
        ]));
    }

    public function dashboardMetricsJson(UserDashboardService $dashboard, RbacService $rbac)
    {
        $user = auth()->user();

        if ($rbac->canAccessPanel($user)) {
            return response()->json(['success' => false], 403);
        }

        return response()
            ->json($dashboard->getDashboardMetrics($user))
            ->header('Cache-Control', 'private, max-age=30');
    }

    public function devices()
    {
        $devices = auth()->user()->trackerDevicesQuery()->get();

        return view('user.devices', compact('devices'));
    }

    public function profile(UserDashboardService $dashboard)
    {
        $user = auth()->user();

        try {
            $payload = array_merge(
                $dashboard->getProfileStats($user),
                ['user' => $user],
            );
        } catch (\Throwable $e) {
            report($e);

            $payload = array_merge(
                $dashboard->getDevicePageStats(collect()),
                [
                    'user' => $user,
                    'totalDistanceKm' => 0,
                    'activeAlerts' => 0,
                    'trackingDaysActive' => 0,
                    'geofenceCount' => 0,
                    'memberDays' => max(1, $user->created_at?->diffInDays(now()) ?? 1),
                    'activities' => collect(),
                ],
            );
        }

        return view('user.profile', $payload);
    }

    public function updateProfile(Request $req, UserAvatarService $avatars)
    {
        $req->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|unique:tc_users,email,' . auth()->id(),
            'phone' => 'nullable|string|max:20',
            'country_code' => 'nullable|string|max:5',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:2048',
        ]);

        $user = auth()->user();
        $user->name = $req->name;
        $user->email = $req->email;
        $user->phone = $req->phone;
        $user->country_code = $req->country_code;

        if ($req->boolean('remove_avatar')) {
            $avatars->delete($user);
        } elseif ($req->hasFile('avatar')) {
            $user->avatar = $avatars->store($user, $req->file('avatar'));
        }

        $user->save();

        return back()->with('success', 'Profile updated successfully!');
    }

    /* ========== Password Change ========== */
    public function changePassword()
    {
        return view('user.change_password');
    }

    public function updatePassword(Request $req, UserPasswordVerifier $passwordVerifier)
    {
        $req->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = auth()->user();

        if (! $passwordVerifier->verify($user, $req->current_password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect']);
        }

        $user->password = Hash::make($req->new_password);
        $user->setTraccarPlainPasswordForNextSave($req->new_password);
        $user->save();

        return back()->with('success', 'Password updated successfully!');
    }
}
