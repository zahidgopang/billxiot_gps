<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Device;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\Authorization\TenantScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private AdminAuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorizePermission('clients.view');

        $q = $this->tenantScope()->scopeClients(Client::query(), $request->user());

        if ($search = $request->query('q')) {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $clients = $q->orderByDesc('id')->paginate(15)->withQueryString();

        return view('admin.clients.index', compact('clients'));
    }

    public function create()
    {
        $this->authorizePermission('clients.manage');

        return view('admin.clients.create');
    }

    public function store(Request $request, TenantScopeService $tenantScope)
    {
        $this->authorizePermission('clients.manage');

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'slug' => 'nullable|string|max:150|unique:clients,slug',
            'status' => 'required|in:active,inactive',
            'can_track_maps' => 'sometimes|boolean',
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['created_by'] = $request->user()->id;
        $data['can_track_maps'] = $request->boolean('can_track_maps');

        $client = Client::query()->create($data);

        if ($this->rbac()->isVendorAdmin($request->user()) && ! $this->rbac()->isSuperAdmin($request->user())) {
            $tenantScope->assignAdminToClient($request->user(), (int) $client->id);
        }

        $this->audit->logCreated($client, "client company {$client->name}", [
            'status' => $client->status,
            'can_track_maps' => $client->can_track_maps,
        ]);

        return redirect()->route('admin.clients.index')->with('success', 'Client created.');
    }

    public function edit(Client $client)
    {
        $this->authorizePermission('clients.manage');
        abort_unless(Gate::allows('manage-client', $client), 403);

        $admins = User::query()->orderBy('name')->get();

        return view('admin.clients.edit', compact('client', 'admins'));
    }

    public function update(Request $request, Client $client, TenantScopeService $tenantScope)
    {
        $this->authorizePermission('clients.manage');
        abort_unless(Gate::allows('manage-client', $client), 403);

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'slug' => 'nullable|string|max:150|unique:clients,slug,' . $client->id,
            'status' => 'required|in:active,inactive',
            'can_track_maps' => 'sometimes|boolean',
            'admin_ids' => 'nullable|array',
            'admin_ids.*' => 'integer|exists:tc_users,id',
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $client->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'status' => $data['status'],
            'can_track_maps' => $request->boolean('can_track_maps'),
        ]);

        $this->audit->logUpdated($client, "client company {$client->name}", [
            'status' => $client->status,
            'can_track_maps' => $client->can_track_maps,
        ]);

        if ($this->rbac()->isSuperAdmin($request->user()) && $request->has('admin_ids')) {
            $client->adminScopes()->delete();
            foreach ($request->input('admin_ids', []) as $adminId) {
                $tenantScope->assignAdminToClient(User::query()->findOrFail($adminId), (int) $client->id);
            }
        }

        return redirect()->route('admin.clients.index')->with('success', 'Client updated.');
    }

    public function users(Request $request, Client $client)
    {
        abort_unless(
            Gate::allows('manage-client', $client)
                || $this->rbac()->hasPermission($request->user(), 'devices.manage')
                || $this->rbac()->hasPermission($request->user(), 'users.view'),
            403
        );

        $users = $this->tenantScope()->assignableUsersForClient((int) $client->id, $request->user());

        return response()->json([
            'users' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'text' => $u->name . ' (' . $u->email . ')',
            ])->values(),
        ]);
    }

    public function devices(Request $request, Client $client)
    {
        abort_unless(
            Gate::allows('manage-client', $client)
                || $this->rbac()->hasPermission($request->user(), 'subscriptions.manage')
                || $this->rbac()->hasPermission($request->user(), 'devices.view'),
            403
        );

        $devices = $this->tenantScope()->devicesForClient((int) $client->id, $request->user());

        return response()->json([
            'devices' => $devices->map(fn (Device $d) => [
                'id' => $d->id,
                'text' => ($d->name ?: $d->imei) . ' · IMEI ' . $d->imei
                    . ($d->user ? ' — ' . $d->user->name : ''),
            ])->values(),
        ]);
    }
}
