<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\Authorization\RbacService;
use App\Services\Authorization\SubAccountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubAccountController extends Controller
{
    public function __construct(
        private SubAccountService $subAccounts,
        private RbacService $rbac,
        private AdminAuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorizeView($request);

        $q = $this->subAccounts->queryForParent($request->user());

        if ($search = trim((string) $request->query('q', ''))) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $subAccounts = $q->orderByDesc('id')->paginate(15)->withQueryString();

        return view('sub-accounts.index', [
            'subAccounts' => $subAccounts,
            'panel' => $this->panelPrefix(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeCreate($request);
        $parent = $request->user();

        return view('sub-accounts.create', [
            'subAccount' => null,
            'panel' => $this->panelPrefix(),
            'devices' => $this->subAccounts->assignableDevices($parent),
            'groupedPermissions' => $this->subAccounts->groupedPermissionsForForm($parent),
            'grantedKeys' => array_flip($this->subAccounts->defaultPermissionKeysForForm($parent)),
            'essentialKeys' => array_flip(\App\Support\Authorization\PermissionCatalog::essentialFleetPermissionKeys()),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeCreate($request);
        $parent = $request->user();
        $data = $this->validatePayload($request);

        $user = $this->subAccounts->create($parent, $data);

        $this->audit->logCreated($user, "sub-account {$user->email}", [
            'parent_user_id' => $parent->id,
            'device_count' => count($data['device_ids']),
        ]);

        return redirect()
            ->route($this->panelPrefix().'.sub-accounts.index')
            ->with('success', __('app.sub_accounts.created'));
    }

    public function show(Request $request, User $subAccount)
    {
        $this->authorizeShow($request, $subAccount);

        $parent = $request->user();
        $subAccount->refresh();
        $subAccount->load('devices');

        $selectedKeys = $this->subAccounts->selectedPermissionKeysForForm($subAccount, $parent);

        return view('sub-accounts.show', [
            'subAccount' => $subAccount,
            'panel' => $this->panelPrefix(),
            'devices' => $subAccount->devices->sortBy(fn ($d) => $d->vehicle_name ?: $d->name)->values(),
            'groupedPermissions' => $this->subAccounts->groupedPermissionsForForm($parent, $subAccount),
            'grantedKeys' => array_flip($selectedKeys),
            'selectedPermissionCount' => count($selectedKeys),
        ]);
    }

    public function edit(Request $request, User $subAccount)
    {
        $this->authorizeManage($request, $subAccount);

        $parent = $request->user();
        $subAccount->refresh();
        $subAccount->load('devices');

        $granted = array_flip($this->subAccounts->selectedPermissionKeysForForm($subAccount, $parent));

        return view('sub-accounts.edit', [
            'subAccount' => $subAccount,
            'panel' => $this->panelPrefix(),
            'devices' => $this->subAccounts->assignableDevices($parent),
            'groupedPermissions' => $this->subAccounts->groupedPermissionsForForm($parent, $subAccount),
            'grantedKeys' => $granted,
            'essentialKeys' => array_flip(\App\Support\Authorization\PermissionCatalog::essentialFleetPermissionKeys()),
            'selectedDeviceIds' => $subAccount->devices->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function update(Request $request, User $subAccount)
    {
        $this->authorizeManage($request, $subAccount);

        $parent = $request->user();
        $data = $this->validatePayload($request, $subAccount);

        $user = $this->subAccounts->update($parent, $subAccount, $data);

        $this->audit->logUpdated($user, "sub-account {$user->email}", [
            'parent_user_id' => $parent->id,
            'device_count' => count($data['device_ids']),
        ]);

        return redirect()
            ->route($this->panelPrefix().'.sub-accounts.index')
            ->with('success', __('app.sub_accounts.updated'));
    }

    public function destroy(Request $request, User $subAccount)
    {
        $this->authorizeManage($request, $subAccount);

        $email = $subAccount->email;
        $this->subAccounts->delete($request->user(), $subAccount);

        $this->audit->logDeleted($subAccount, "sub-account {$email}");

        return redirect()
            ->route($this->panelPrefix().'.sub-accounts.index')
            ->with('success', __('app.sub_accounts.deleted'));
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($this->subAccounts->canViewSubAccounts($request->user()), 403);
    }

    private function authorizeCreate(Request $request): void
    {
        abort_unless($this->subAccounts->canCreateSubAccounts($request->user()), 403);
    }

    private function authorizeShow(Request $request, User $subAccount): void
    {
        abort_unless(
            $this->subAccounts->canViewSubAccount($request->user(), $subAccount),
            404
        );
    }

    private function authorizeManage(Request $request, User $subAccount): void
    {
        abort_unless(
            $this->subAccounts->canManageSubAccount($request->user(), $subAccount),
            404
        );
    }

    private function panelPrefix(): string
    {
        if (request()->routeIs('user.*')) {
            return 'user';
        }

        if (request()->routeIs('client.*')) {
            return 'client';
        }

        return 'admin';
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?User $subAccount = null): array
    {
        $usersTable = config('traccar.tables.users', 'tc_users');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique($usersTable, 'email')->ignore($subAccount?->id)],
            'password' => [$subAccount ? 'nullable' : 'required', 'string', 'min:6'],
            'status' => ['required', 'in:active,inactive'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'phone' => ['nullable', 'string', 'max:20'],
            'device_ids' => ['required', 'array', 'min:1'],
            'device_ids.*' => ['integer'],
            'permission_keys' => ['nullable', 'array'],
            'permission_keys.*' => ['string'],
        ]);

        $validated['device_ids'] = $this->subAccounts->validateDeviceIds(
            $request->user(),
            $validated['device_ids']
        );

        $validated['permission_keys'] = array_values(array_unique($validated['permission_keys'] ?? []));

        return $validated;
    }
}
