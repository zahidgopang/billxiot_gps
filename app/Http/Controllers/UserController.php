<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\Auth\UserPasswordVerifier;
use App\Services\DeviceSubscriptionService;
use App\Services\UserAvatarService;
use App\Services\UserDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function dashboard(Request $request, UserDashboardService $dashboard)
    {
        $user = auth()->user();
        $stats = $dashboard->getStats($user);

        return view('user.dashboard', array_merge($stats, [
            'emailVerified' => session()->has('email_verified'),
            'trackerAccountActive' => app(\App\Services\Traccar\TraccarUserAccessService::class)->hasTrackerAccount($user),
            'dashboardService' => $dashboard,
            'subscriptionService' => app(DeviceSubscriptionService::class),
            'alertDeviceIds' => $dashboard->alertDeviceIds($stats['devices']),
        ]));
    }

    public function devices()
    {
        $devices = auth()->user()->trackerDevicesQuery()->get();

        return view('user.devices', compact('devices'));
    }

    public function profile(UserDashboardService $dashboard)
    {
        $user = auth()->user();

        return view('user.profile', array_merge(
            $dashboard->getProfileStats($user),
            ['user' => $user]
        ));
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
