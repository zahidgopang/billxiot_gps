<?php

namespace App\Http\Concerns;

use App\Models\User;
use App\Services\UserAvatarService;

trait PresentsMobileUser
{
    /**
     * @return array<string, mixed>
     */
    protected function mobileUserPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country_code' => $user->country_code,
            'avatar_url' => app(UserAvatarService::class)->url($user),
            'status' => $user->status ?? 'active',
        ];
    }
}
