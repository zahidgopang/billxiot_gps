<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\AppRole;
use App\Models\Client;
use App\Models\Device;
use App\Models\User;
use App\Services\Authorization\RbacService;
use App\Services\Authorization\TenantScopeService;
use App\Support\Traccar\TraccarAppFields;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

trait InteractsWithTenantAuthorization
{
    use InteractsWithPanelRoutes;

    protected function rbac(): RbacService
    {
        return app(RbacService::class);
    }

    protected function tenantScope(): TenantScopeService
    {
        return app(TenantScopeService::class);
    }

    protected function authorizePermission(string $permission): void
    {
        abort_unless(Gate::allows('permission', $permission), 403);
    }

    protected function authorizeManageUser(User $target): void
    {
        abort_unless(Gate::allows('manage-user', $target), 403);
    }

    protected function authorizeManageDevice(Device $device): void
    {
        abort_unless(Gate::allows('manage-device', $device), 403);
    }

    protected function authorizeVisibleClient(Request $request, int $clientId): void
    {
        if (! in_array($clientId, $this->tenantScope()->visibleClientIds($request->user()), true)
            && ! $this->rbac()->isSuperAdmin($request->user())) {
            abort(403);
        }
    }

    /**
     * Client ID for the current form action (selected on admin panel, implicit on client panel).
     */
    protected function resolveClientIdForRequest(Request $request): int
    {
        $clientId = $this->resolveClientIdForUser(
            $request,
            $request->user(),
            (string) $request->input('role', $request->user()->role),
        );

        if ($clientId === null) {
            throw ValidationException::withMessages([
                'client_id' => 'Please select a client company.',
            ]);
        }

        return $clientId;
    }

    /**
     * Resolve tenant client for user create/update (admin panel).
     */
    protected function resolveClientIdForUser(Request $request, User $user, string $role): ?int
    {
        if ($this->isClientPanel($request)) {
            return $this->tenantScope()->ensureClientForManager($request->user());
        }

        if ($role === AppRole::Client->value) {
            return $this->resolveClientIdForClientRoleUser($request, $user);
        }

        $clientId = $request->integer('client_id');

        if ($role === AppRole::EndUser->value) {
            if (! $clientId) {
                throw ValidationException::withMessages([
                    'client_id' => 'Please select a client company.',
                ]);
            }

            $this->authorizeVisibleClient($request, $clientId);

            return $clientId;
        }

        if ($clientId) {
            $this->authorizeVisibleClient($request, $clientId);

            return $clientId;
        }

        return null;
    }

    protected function resolveClientIdForClientRoleUser(Request $request, User $user): int
    {
        $existing = $user->exists
            ? $this->tenantScope()->primaryClientIdForUser($user)
            : null;

        if ($existing) {
            $this->authorizeVisibleClient($request, $existing);

            return $existing;
        }

        $client = Client::query()->create([
            'name' => $request->input('name'),
            'status' => 'active',
            'created_by' => $request->user()->id,
            'can_track_maps' => $request->boolean('can_track_maps'),
        ]);

        return (int) $client->id;
    }

    protected function userFormRequiresClientPicker(string $role): bool
    {
        return $role === AppRole::EndUser->value;
    }

    protected function syncUserTenantLinks(Request $request, User $user, ?int $clientId): void
    {
        if ($clientId === null) {
            return;
        }

        $this->authorizeVisibleClient($request, $clientId);

        if ($user->role === AppRole::Admin->value) {
            $this->tenantScope()->assignAdminToClient($user, $clientId);
        }

        $membership = $user->role === AppRole::Client->value ? 'owner' : 'member';
        $this->tenantScope()->assignUserToClient($user, $clientId, $membership);
    }

    protected function assertUserBelongsToClient(User $user, int $clientId): void
    {
        if (! $this->tenantScope()->userBelongsToClient($user, $clientId)) {
            throw ValidationException::withMessages([
                'user_id' => 'The selected user does not belong to this client.',
            ]);
        }
    }

    protected function scopeEndUsersOnly(Builder $query): void
    {
        $table = $query->getModel()->getTable();

        if (! TraccarSchema::hasColumn($table, 'attributes')) {
            return;
        }

        $path = '$.' . TraccarAppFields::KEY_ROLE;
        $endUser = AppRole::EndUser->value;

        $query->where(function ($q) use ($path, $endUser) {
            $q->whereRaw(
                'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(' . $q->qualifyColumn('attributes') . ', ?)), ?) = ?',
                [$path, $endUser, $endUser]
            )->where(function ($inner) {
                $inner->where($inner->qualifyColumn('administrator'), '!=', 1)
                    ->orWhereNull($inner->qualifyColumn('administrator'));
            });
        });
    }
}
