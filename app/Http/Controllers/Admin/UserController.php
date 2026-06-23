<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppRole;
use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private AdminAuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorizePermission('users.view');

        $q = $this->tenantScope()->scopeUsers(User::query(), $request->user());

        if ($this->isClientPanel()) {
            $q->where($q->qualifyColumn('id'), '!=', $request->user()->id);
            $this->scopeEndUsersOnly($q);
        }

        if ($search = $request->query('q')) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $q
            ->withCount('clientMemberships')
            ->withCount('devices as tracker_devices_count')
            ->with(['clients:id,name'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'panel' => $this->panelPrefix(),
        ]);
    }

    public function create()
    {
        $this->authorizePermission('users.manage');

        $assignableRoles = $this->rbac()->assignableRoles(auth()->user());
        $clients = $this->isClientPanel()
            ? collect()
            : $this->tenantScope()->scopeClients(\App\Models\Client::query(), auth()->user())->orderBy('name')->get();

        return view('admin.users.create', [
            'assignableRoles' => $assignableRoles,
            'clients' => $clients,
            'panel' => $this->panelPrefix(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('users.manage');

        $assignable = $this->rbac()->assignableRoles($request->user());

        $rules = [
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:tc_users,email',
            'password' => 'required|min:6',
            'role' => ['required', Rule::in($assignable)],
            'status' => 'required|in:active,inactive',
            'country_code' => 'nullable|string|max:8',
            'phone' => 'nullable|string|max:20',
        ];

        $data = $request->validate($rules);
        $data = $this->applyClientPanelUserDefaults($request, $data);

        if (! $this->isClientPanel() && $this->userFormRequiresClientPicker($data['role'])) {
            $request->validate(['client_id' => 'required|integer|exists:clients,id']);
        }

        $clientId = $this->resolveClientIdForUser($request, new User($data), $data['role']);

        $plainPassword = $request->input('password');
        unset($data['password'], $data['client_id']);
        $data['country_code'] = $data['country_code'] ?? '';
        $data['phone'] = $data['phone'] ?? '';

        $user = new User($data);
        $user->password = $plainPassword;
        $user->setTraccarPlainPasswordForNextSave($plainPassword);
        $user->save();

        $this->syncUserTenantLinks($request, $user, $clientId);
        $this->syncUserMapTrackingPermission($request, $user, $user->role);
        $this->syncClientCompanyForClientRoleUser($request, $user, $user->role, $clientId);

        $this->audit->logCreated($user, "user {$user->email}", [
            'role' => $user->role,
            'status' => $user->status,
            'client_id' => $clientId,
        ]);

        return redirect()->to($this->panelRoute('users.index'))->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $this->authorizePermission('users.manage');
        $this->authorizeManageUser($user);

        $assignableRoles = $this->rbac()->assignableRoles(auth()->user());
        $clients = $this->isClientPanel()
            ? collect()
            : $this->tenantScope()->scopeClients(\App\Models\Client::query(), auth()->user())->orderBy('name')->get();

        return view('admin.users.edit', [
            'user' => $user,
            'assignableRoles' => $assignableRoles,
            'clients' => $clients,
            'panel' => $this->panelPrefix(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizePermission('users.manage');
        $this->authorizeManageUser($user);

        $assignable = $this->rbac()->assignableRoles($request->user());

        $rules = [
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:tc_users,email,' . $user->id,
            'password' => 'nullable|min:6',
            'role' => ['required', Rule::in($assignable)],
            'status' => 'required|in:active,inactive',
            'country_code' => 'nullable|string|max:8',
            'phone' => 'nullable|string|max:20',
        ];

        $data = $request->validate($rules);
        $data = $this->applyClientPanelUserDefaults($request, $data, $user);

        if (! $this->isClientPanel() && $this->userFormRequiresClientPicker($data['role'])) {
            $request->validate(['client_id' => 'required|integer|exists:clients,id']);
        }

        $clientId = $this->resolveClientIdForUser($request, $user, $data['role']);

        $data['country_code'] = $data['country_code'] ?? '';
        $data['phone'] = $data['phone'] ?? '';

        if ($request->filled('password')) {
            $user->setTraccarPlainPasswordForNextSave($request->input('password'));
            $user->password = $request->input('password');
        }

        unset($data['client_id']);
        $user->fill($data);
        $user->save();

        $this->syncUserTenantLinks($request, $user, $clientId);
        $this->syncUserMapTrackingPermission($request, $user, $user->role);
        $this->syncClientCompanyForClientRoleUser($request, $user, $user->role, $clientId);

        $this->audit->logUpdated($user, "user {$user->email}", [
            'role' => $user->role,
            'status' => $user->status,
            'client_id' => $clientId,
        ]);

        return redirect()->to($this->panelRoute('users.index'))->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $this->authorizePermission('users.manage');
        $this->authorizeManageUser($user);

        if ($user->email === 'admin@demo.test') {
            return back()->with('error', 'Default admin cannot be deleted.');
        }

        $email = $user->email;
        $clientId = $this->tenantScope()->primaryClientIdForUser($user);

        $user->delete();

        $this->audit->log('deleted', "Deleted user {$email}", null, array_filter([
            'email' => $email,
            'client_id' => $clientId,
        ]));

        return redirect()->to($this->panelRoute('users.index'))->with('success', 'User deleted successfully.');
    }

    public function toggleStatus(Request $request, User $user)
    {
        $this->authorizePermission('users.manage');
        $this->authorizeManageUser($user);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot change your own account status here.',
            ], 422);
        }

        if ($user->email === 'admin@demo.test') {
            return response()->json([
                'success' => false,
                'message' => 'The default admin account cannot be deactivated.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $user->update(['status' => $validated['status']]);

        $label = $validated['status'] === 'active' ? 'Active' : 'Inactive';

        $this->audit->logUpdated($user, "user {$user->email} status", [
            'status' => $user->status,
            'client_id' => $this->tenantScope()->primaryClientIdForUser($user),
        ]);

        return response()->json([
            'success' => true,
            'status' => $user->status,
            'label' => $label,
            'message' => "User account is now {$label}.",
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyClientPanelUserDefaults(Request $request, array $data, ?User $existing = null): array
    {
        if (! $this->isClientPanel()) {
            return $data;
        }

        $data['role'] = AppRole::EndUser->value;

        if ($existing && $existing->id === $request->user()->id) {
            abort(403, 'You cannot edit your own account from this screen.');
        }

        return $data;
    }

    private function syncUserMapTrackingPermission(Request $request, User $user, string $role): void
    {
        if (! $this->rbac()->roleSupportsMapTrackingToggle($role)) {
            $this->rbac()->setPermissionOverride($user, 'maps.view', null);

            return;
        }

        $this->rbac()->syncMapsViewPermission($user, $request->boolean('can_track_maps'));
    }

    private function syncClientCompanyForClientRoleUser(Request $request, User $user, string $role, int $clientId): void
    {
        if ($role !== AppRole::Client->value || $this->isClientPanel()) {
            return;
        }

        $client = \App\Models\Client::query()->find($clientId);

        if (! $client) {
            return;
        }

        $client->update([
            'name' => $request->input('name'),
            'can_track_maps' => $request->boolean('can_track_maps'),
        ]);
    }

}
