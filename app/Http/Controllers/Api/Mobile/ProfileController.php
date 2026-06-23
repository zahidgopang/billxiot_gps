<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Concerns\PresentsMobileUser;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Services\Mobile\MobileEntitlementService;
use App\Services\UserAvatarService;
use Illuminate\Http\Request;
use App\Services\Auth\UserPasswordVerifier;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    use PresentsMobileUser;
    use RespondsWithMobileJson;

    public function __construct(
        private MobileEntitlementService $entitlement,
        private UserAvatarService $avatars,
    ) {}

    public function show(Request $request)
    {
        $user = $request->user();

        return $this->mobileSuccess([
            'user' => $this->mobileUserPayload($user),
            'permissions' => $this->entitlement->permissionsFor($user),
            'subscription' => $this->entitlement->subscriptionSummaryForUser($user),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|unique:tc_users,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
            'country_code' => 'nullable|string|max:5',
        ]);

        if (array_key_exists('country_code', $validated)) {
            $validated['country_code'] = $this->normalizeDialCode($validated['country_code']);
        }

        $user->fill($validated);
        $user->save();

        return $this->mobileSuccess([
            'user' => $this->mobileUserPayload($user),
            'message' => 'Profile updated successfully.',
        ]);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp,gif|max:5120',
        ]);

        $user = $request->user();
        $user->avatar = $this->avatars->store($user, $request->file('avatar'));
        $user->save();
        $user->refresh();

        return $this->mobileSuccess([
            'user' => $this->mobileUserPayload($user),
            'message' => 'Profile picture updated.',
        ]);
    }

    public function deleteAvatar(Request $request)
    {
        $user = $request->user();
        $this->avatars->delete($user);
        $user->save();
        $user->refresh();

        return $this->mobileSuccess([
            'user' => $this->mobileUserPayload($user),
            'message' => 'Profile picture removed.',
        ]);
    }

    public function changePassword(Request $request, UserPasswordVerifier $passwordVerifier)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        $user = $request->user();

        $checked = $passwordVerifier->verify($user, $request->current_password);

        Log::info('mobile_change_password_check', [
            'user_id' => $user->id,
            'auth_password_len' => strlen((string) $user->getAuthPassword()),
            'verify_ok' => $checked,
        ]);

        if (! $checked) {
            return $this->mobileError('Current password is incorrect', 422, 'invalid_password');
        }

        $user->password = Hash::make($request->new_password);
        $user->setTraccarPlainPasswordForNextSave($request->new_password);
        $user->save();

        return $this->mobileSuccess(['message' => 'Password updated successfully.']);
    }

    private function normalizeDialCode(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $v = trim($value);
        if ($v === '') {
            return null;
        }

        if ($v[0] !== '+') {
            $v = '+'.$v;
        }

        $v = '+'.preg_replace('/\D+/', '', $v);

        if (strlen($v) > 6) {
            $v = substr($v, 0, 6);
        }

        return $v;
    }
}
