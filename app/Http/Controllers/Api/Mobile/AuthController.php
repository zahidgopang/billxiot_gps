<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Concerns\PresentsMobileUser;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Services\Mobile\MobileEntitlementService;
use App\Services\Mobile\MobileLoginService;
use Illuminate\Http\Request;
use Throwable;

class AuthController extends Controller
{
    use PresentsMobileUser;
    use RespondsWithMobileJson;

    public function __construct(
        private MobileLoginService $login,
        private MobileEntitlementService $entitlement,
    ) {}

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
            'remember' => 'sometimes|boolean',
        ]);

        try {
            return $this->attemptLogin($request);
        } catch (Throwable $e) {
            try {
                report($e);
            } catch (Throwable) {
                // Never fail the response because logging is broken on production.
            }

            return $this->mobileError(
                config('app.debug') ? $e->getMessage() : 'Something went wrong. Please try again.',
                500,
                'server_error'
            );
        }
    }

    private function attemptLogin(Request $request)
    {
        $remember = $request->boolean('remember', true);
        $email = strtolower(trim((string) $request->email));
        $deviceName = (string) $request->input('device_name', 'mobile-app');

        $user = $this->login->authenticate($email, (string) $request->password);

        if (! $user) {
            return $this->mobileError('Invalid credentials', 401, 'invalid_credentials');
        }

        if (! $this->entitlement->canAccessMobileApp($user)) {
            return $this->mobileError('This account is not allowed to use the mobile app.', 403, 'invalid_role');
        }

        if ($this->entitlement->isEndUser($user)) {
            $access = $this->entitlement->evaluateAccountAccess($user);
        } else {
            $access = $this->entitlement->evaluateStaff($user);
        }

        if (! $access['allowed']) {
            $status = match ($access['code']) {
                MobileEntitlementService::CODE_ACCOUNT_INACTIVE => 403,
                default => 402,
            };

            return $this->mobileError($access['message'], $status, $access['code']);
        }

        $payload = $this->login->issueTokenAndPayload($user, $deviceName, $remember);

        return $this->mobileSuccess([
            ...$payload,
            'user' => $this->mobileUserPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->mobileSuccess(['message' => 'Logged out']);
    }
}
