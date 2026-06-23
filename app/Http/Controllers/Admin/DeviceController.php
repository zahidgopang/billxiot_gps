<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Device;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\Inventory\InventoryService;
use App\Services\Stock\ClientStockBalanceService;
use App\Support\Traccar\TraccarSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private AdminAuditService $audit,
        private ClientStockBalanceService $clientStock,
        private InventoryService $inventory,
    ) {}

    public function index(Request $request)
    {
        $this->authorizePermission('devices.view');

        $q = $this->tenantScope()->scopeDevices(Device::query(), $request->user())
            ->with('user')
            ->orderByDesc('id');

        if ($search = $request->query('q')) {
            $q->where(function ($w) use ($search) {
                $w->whereImeiLike("%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereRaw(
                        'JSON_UNQUOTE(JSON_EXTRACT(' . $w->qualifyColumn('attributes') . ', ?)) LIKE ?',
                        ['$.' . \App\Support\Traccar\TraccarAppFields::KEY_VEHICLE_NAME, "%{$search}%"]
                    )
                    ->orWhereRaw(
                        'JSON_UNQUOTE(JSON_EXTRACT(' . $w->qualifyColumn('attributes') . ', ?)) LIKE ?',
                        ['$.' . \App\Support\Traccar\TraccarAppFields::KEY_VEHICLE_NUMBER, "%{$search}%"]
                    )
                    ->orWhereRaw(
                        'JSON_UNQUOTE(JSON_EXTRACT(' . $w->qualifyColumn('attributes') . ', ?)) LIKE ?',
                        ['$.' . \App\Support\Traccar\TraccarAppFields::KEY_VEHICLE_MODEL, "%{$search}%"]
                    );
            });
        }

        $devices = $q->paginate(15)->withQueryString();
        app(\App\Services\Tracking\DevicePositionLoader::class)->attachLatestToMany($devices->getCollection());

        return view('admin.devices.index', [
            'devices' => $devices,
            'panel' => $this->panelPrefix(),
        ]);
    }

    public function create()
    {
        $this->authorizePermission('devices.manage');

        [$users, $clients, $usersByClient] = $this->deviceFormUserData(auth()->user());

        $formClientId = $this->resolveFormClientId(null);
        $allowedDeviceTypes = $this->allowedDeviceTypesForForm($formClientId, null);

        return view('admin.devices.create', [
            'users' => $users,
            'clients' => $clients,
            'usersByClient' => $usersByClient,
            'panel' => $this->panelPrefix(),
            'formClientId' => $formClientId,
            'allowedDeviceTypes' => $allowedDeviceTypes,
            'clientStockBalance' => $formClientId ? $this->clientStock->balanceForClient($formClientId) : null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('devices.manage');

        $this->normalizeDeviceInput($request);
        $data = $request->validate($this->deviceValidationRules());
        $data['device_type'] = Device::canonicalDeviceType($data['device_type']) ?? $data['device_type'];

        $clientId = $this->resolveClientIdForRequest($request);

        $owner = User::query()->findOrFail($data['user_id']);
        $this->assertUserBelongsToClient($owner, $clientId);

        $device = DB::transaction(function () use ($data, $clientId) {
            $this->clientStock->assertCanInstall($clientId, $data['device_type']);

            $device = Device::create($this->devicePayloadFromValidated($data));

            $this->tenantScope()->assignDeviceToClient($device, $clientId);

            return $device;
        });

        // Central inventory: consume 1 unit from client inventory for this install.
        // (Sell invoice transfers to client, install consumes client stock.)
        $this->inventory->consumeForInstall($clientId, $data['device_type'], (int) $device->id, (int) $request->user()->id);

        $this->audit->logCreated($device, "device {$device->imei}", [
            'name' => $device->name,
            'device_type' => $device->device_type,
            'user_id' => $device->user_id,
            'client_id' => $clientId,
            'status' => $device->status,
        ]);

        return redirect()->to($this->panelRoute('devices.index'))->with('success', 'Device created successfully.');
    }

    public function edit(Device $device)
    {
        $this->authorizePermission('devices.manage');
        $this->authorizeManageDevice($device);

        [$users, $clients, $usersByClient] = $this->deviceFormUserData(auth()->user());

        $formClientId = $this->resolveFormClientId($device);
        $allowedDeviceTypes = $this->allowedDeviceTypesForForm($formClientId, $device->device_type);

        return view('admin.devices.edit', [
            'device' => $device,
            'users' => $users,
            'clients' => $clients,
            'usersByClient' => $usersByClient,
            'panel' => $this->panelPrefix(),
            'formClientId' => $formClientId,
            'allowedDeviceTypes' => $allowedDeviceTypes,
            'clientStockBalance' => $formClientId ? $this->clientStock->balanceForClient($formClientId) : null,
        ]);
    }

    public function update(Request $request, Device $device)
    {
        $this->authorizePermission('devices.manage');
        $this->authorizeManageDevice($device);

        $this->normalizeDeviceInput($request);
        $data = $request->validate($this->deviceValidationRules($device));
        $data['device_type'] = Device::canonicalDeviceType($data['device_type']) ?? $data['device_type'];

        $clientId = $this->resolveClientIdForRequest($request);

        $owner = User::query()->findOrFail($data['user_id']);
        $this->assertUserBelongsToClient($owner, $clientId);

        $previousClientId = $this->tenantScope()->clientIdForDevice($device);
        $exceptDeviceId = ($previousClientId && (int) $previousClientId === $clientId)
            ? (int) $device->id
            : null;

        DB::transaction(function () use ($device, $data, $clientId, $exceptDeviceId) {
            $this->clientStock->assertCanInstall($clientId, $data['device_type'], $exceptDeviceId);

            $device->update($this->devicePayloadFromValidated($data));

            $this->tenantScope()->assignDeviceToClient($device, $clientId);
        });

        $this->audit->logUpdated($device, "device {$device->imei}", array_merge($data, ['client_id' => $clientId]));

        return redirect()->to($this->panelRoute('devices.index'))->with('success', 'Device updated successfully.');
    }

    public function destroy(Device $device)
    {
        $this->authorizePermission('devices.manage');
        $this->authorizeManageDevice($device);

        $imei = $device->imei;
        $clientId = $this->tenantScope()->clientIdForDevice($device);

        $device->delete();

        $this->audit->log('deleted', "Deleted device {$imei}", null, array_filter([
            'imei' => $imei,
            'client_id' => $clientId,
        ]));

        return redirect()->to($this->panelRoute('devices.index'))->with('success', 'Device deleted successfully.');
    }

    public function toggleStatus(Request $request, Device $device)
    {
        $this->authorizePermission('devices.manage');
        $this->authorizeManageDevice($device);

        if ($device->status === 'blocked') {
            return response()->json([
                'success' => false,
                'message' => 'This device is blocked. Edit the device to change status.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $device->update(['status' => $validated['status']]);

        $label = $validated['status'] === 'active' ? 'Active' : 'Inactive';

        $this->audit->logUpdated($device, "device {$device->imei} status", [
            'status' => $device->status,
            'client_id' => $this->tenantScope()->clientIdForDevice($device),
        ]);

        return response()->json([
            'success' => true,
            'status' => $device->status,
            'label' => $label,
            'message' => "Device is now {$label}.",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function deviceValidationRules(?Device $device = null): array
    {
        $devicesTable = (new Device)->getTable();
        $imeiColumn = TraccarSchema::resolveColumn($devicesTable, 'uniqueid') ?? 'uniqueid';
        $exceptId = $device?->id;

        $rules = [
            'imei' => [
                'required',
                'string',
                'max:32',
                Rule::unique($devicesTable, $imeiColumn)->ignore($exceptId),
            ],
            'name' => 'nullable|string|max:255',
            'user_id' => 'required|exists:tc_users,id',
            'device_type' => ['required', Rule::in(array_keys(Device::DEVICE_TYPES))],
            'status' => 'required|in:active,inactive,blocked',
            'vehicle_name' => 'nullable|string|max:120',
            'vehicle_number' => [
                'nullable',
                'string',
                'max:40',
                function (string $attribute, mixed $value, \Closure $fail) use ($exceptId): void {
                    if (! is_string($value) || Device::normalizeVehicleNumber($value) === null) {
                        return;
                    }

                    if (Device::isVehicleNumberTaken($value, $exceptId)) {
                        $fail(__('validation.unique', ['attribute' => __('app.forms.vehicle_number')]));
                    }
                },
            ],
            'vehicle_model' => 'nullable|string|max:80',
            'vehicle_type' => ['nullable', Rule::in(array_keys(Device::VEHICLE_TYPES))],
            'sim_type' => ['nullable', Rule::in(array_keys(Device::SIM_TYPES))],
            'sim_number' => 'nullable|string|max:40',
            'plate_type' => ['nullable', Rule::in(array_keys(Device::PLATE_TYPES))],
        ];

        if (! $this->isClientPanel()) {
            $rules['client_id'] = 'required|integer|exists:clients,id';
        }

        return $rules;
    }

    private function normalizeDeviceInput(Request $request): void
    {
        $request->merge([
            'imei' => Device::normalizeImei($request->input('imei')),
            'vehicle_number' => Device::normalizeVehicleNumber($request->input('vehicle_number')),
            'sim_number' => $request->filled('sim_number')
                ? trim((string) $request->input('sim_number'))
                : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function devicePayloadFromValidated(array $data): array
    {
        return [
            'uniqueid' => $data['imei'],
            'name' => $data['name'] ?: ($data['vehicle_name'] ?? $data['imei']),
            'user_id' => $data['user_id'],
            'device_type' => $data['device_type'],
            'status' => $data['status'],
            'vehicle_name' => $data['vehicle_name'] ?? null,
            'vehicle_number' => $data['vehicle_number'] ?? null,
            'vehicle_model' => $data['vehicle_model'] ?? null,
            'vehicle_type' => $data['vehicle_type'] ?? null,
            'sim_type' => $data['sim_type'] ?? null,
            'sim_number' => $data['sim_number'] ?? null,
            'plate_type' => $data['plate_type'] ?? null,
        ];
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: array<int, list<array{id: int, text: string}>>}
     */
    private function deviceFormUserData(User $actor): array
    {
        if ($this->isClientPanel()) {
            $usersQuery = $this->tenantScope()->scopeUsers(User::query(), $actor)->orderBy('name');
            $this->scopeEndUsersOnly($usersQuery);

            return [$usersQuery->get(), collect(), []];
        }

        $clients = $this->tenantScope()->scopeClients(Client::query(), $actor)->orderBy('name')->get();
        $usersByClient = [];

        foreach ($clients as $client) {
            $usersByClient[(string) $client->id] = $this->tenantScope()
                ->assignableUsersForClient((int) $client->id, $actor)
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'text' => $u->name . ' (' . $u->email . ')',
                ])
                ->values()
                ->all();
        }

        return [collect(), $clients, $usersByClient];
    }

    private function resolveFormClientId(?Device $device): ?int
    {
        if ($device) {
            return $this->tenantScope()->clientIdForDevice($device);
        }

        if ($this->isClientPanel()) {
            return $this->tenantScope()->primaryClientIdForUser(auth()->user());
        }

        $clientId = old('client_id') ?: request()->input('client_id');

        return $clientId ? (int) $clientId : null;
    }

    /**
     * @return list<string>
     */
    private function allowedDeviceTypesForForm(?int $clientId, ?string $includeType): array
    {
        if (! $clientId) {
            return array_keys(Device::DEVICE_TYPES);
        }

        return $this->clientStock->installableTypesForClient($clientId, $includeType);
    }
}
