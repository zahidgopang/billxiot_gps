<?php

namespace App\Observers;

use App\Models\User;
use App\Services\Traccar\TraccarEntityProvisioner;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;

class UserObserver
{
    public function __construct(
        private TraccarEntityProvisioner $provisioner,
    ) {}

    public function created(User $user): void
    {
        $this->syncIfEnabled($user);
    }

    public function updated(User $user): void
    {
        $this->syncIfEnabled($user);
    }

    public function deleted(User $user): void
    {
        if (! TraccarMode::isActive() || ! TraccarSchema::hasUsers()) {
            return;
        }

        try {
            $this->provisioner->unlinkUser($user);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function syncIfEnabled(User $user): void
    {
        if (! TraccarMode::isActive() || ! TraccarSchema::hasUsers()) {
            return;
        }

        try {
            $this->provisioner->provisionUser($user);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
