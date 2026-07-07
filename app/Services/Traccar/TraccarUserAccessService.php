<?php

namespace App\Services\Traccar;

use App\Models\TraccarEntityMap;
use App\Models\User;
use App\Support\Traccar\TraccarMode;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tracker account access: tc_users + traccar_entity_map (not Laravel users alone).
 */
class TraccarUserAccessService
{
    /** @var array<int, bool> */
    private array $trackerAccountCache = [];

    /** @var array<int, ?object> */
    private array $trackerUserRowCache = [];

    public function __construct(
        private TraccarSyncService $sync,
        private TraccarIdMap $idMap,
    ) {}

    public function usesTraccarUserGate(): bool
    {
        return TraccarMode::readsTraccar() && TraccarSchema::hasUsers();
    }

    /**
     * User has a live row in tc_users (removed from Traccar UI = no tracker access).
     */
    public function hasTrackerAccount(User $user): bool
    {
        $userId = (int) $user->id;

        if (array_key_exists($userId, $this->trackerAccountCache)) {
            return $this->trackerAccountCache[$userId];
        }

        if (! $this->usesTraccarUserGate()) {
            return $this->trackerAccountCache[$userId] = true;
        }

        return $this->trackerAccountCache[$userId] = DB::table(config('traccar.tables.users', 'tc_users'))
            ->where('id', $userId)
            ->exists();
    }

    public function resolveTraccarUserId(User $user, bool $ensure = false): ?int
    {
        if (! $this->usesTraccarUserGate()) {
            return null;
        }

        if ($this->hasTrackerAccount($user)) {
            return (int) $user->id;
        }

        return $ensure ? $this->sync->resolveTraccarUserIdForLink($user, true) : null;
    }

    /**
     * Display name for tracker UI (tc_users.name when linked).
     */
    public function displayName(User $user): string
    {
        $row = $this->trackerUserRow($user);

        if ($row && ! empty($row->name)) {
            return (string) $row->name;
        }

        return $user->name;
    }

    public function trackerUserRow(User $user): ?object
    {
        $userId = (int) $user->id;

        if (array_key_exists($userId, $this->trackerUserRowCache)) {
            return $this->trackerUserRowCache[$userId];
        }

        if (! $this->hasTrackerAccount($user)) {
            return $this->trackerUserRowCache[$userId] = null;
        }

        $table = config('traccar.tables.users', 'tc_users');

        return $this->trackerUserRowCache[$userId] = DB::table($table)->where('id', $userId)->first();
    }

    public function pruneStaleMapForUser(User $user): void
    {
        // No-op after unified schema (user id is tc_users.id).
    }
}
