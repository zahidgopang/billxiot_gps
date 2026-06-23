<?php

namespace App\Services\Mobile;

use App\Auth\TcAwareUserProvider;
use App\Models\User;
use App\Services\Tracking\DevicePositionLoader;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Throwable;

class MobileLoginService
{
    public function __construct(
        private MobileEntitlementService $entitlement,
        private MobileDevicePresenter $presenter,
        private DevicePositionLoader $positionLoader,
        private TcAwareUserProvider $userProvider,
        private Hasher $hasher,
    ) {}

    public function authenticate(string $email, string $password): ?User
    {
        $user = User::findForLoginByEmail($email);

        if (! $user || ! $this->passwordValid($user, $password)) {
            return null;
        }

        return $user;
    }

    /**
     * @return array{
     *     token: string,
     *     token_type: string,
     *     remember: bool,
     *     expires_at: string|null,
     *     permissions: list<string>,
     *     accessible_devices: Collection<int, array<string, mixed>>,
     *     subscription: array<string, mixed>|null
     * }
     */
    public function issueTokenAndPayload(
        User $user,
        string $deviceName,
        bool $remember,
    ): array {
        $expiresAt = $remember
            ? null
            : now()->addHours((int) config('mobile_auth.session_hours', 12));

        $tokenResult = $user->createToken($deviceName, ['*'], $expiresAt);
        $devices = $this->loadAccessibleDevices($user);

        return [
            'token' => $tokenResult->plainTextToken,
            'token_type' => 'Bearer',
            'remember' => $remember,
            'expires_at' => $tokenResult->accessToken->expires_at?->toIso8601String(),
            'permissions' => $this->entitlement->permissionsFor($user),
            'accessible_devices' => $devices->map(
                fn ($d) => $this->presenter->listItem($d)
            )->values(),
            'subscription' => $this->safeSubscriptionSummary($user),
        ];
    }

    private function passwordValid(User $user, string $plain): bool
    {
        if ($plain === '') {
            return false;
        }

        $laravelHash = (string) $user->getAuthPassword();

        if ($laravelHash !== '' && $this->hasher->check($plain, $laravelHash)) {
            return true;
        }

        try {
            return User::withoutEvents(function () use ($user, $plain) {
                return $this->userProvider->validateCredentials($user, [
                    'password' => $plain,
                ]);
            });
        } catch (Throwable) {
            return $laravelHash !== '' && Hash::check($plain, $laravelHash);
        }
    }

    /**
     * @return Collection<int, \App\Models\Device>
     */
    private function loadAccessibleDevices(User $user): Collection
    {
        try {
            $devices = $this->entitlement->accessibleDevices($user);
            $this->positionLoader->attachLatestToMany($devices);

            return $devices;
        } catch (Throwable $e) {
            report($e);

            return collect();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeSubscriptionSummary(User $user): ?array
    {
        try {
            return $this->entitlement->subscriptionSummaryForUser($user);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
