<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionType;
use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Device;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\AdminAuditService;
use App\Services\Billing\BillingInvoiceService;
use App\Services\Billing\DeviceCostResolver;
use App\Services\Billing\SubscriptionBillingService;
use App\Services\SubscriptionRenewalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class SubscriptionController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private AdminAuditService $audit,
        private SubscriptionRenewalService $renewals,
        private SubscriptionBillingService $subscriptionBilling,
        private BillingInvoiceService $billingInvoices,
        private DeviceCostResolver $deviceCosts,
    ) {}

    public function index(Request $request)
    {
        $this->authorizePermission('subscriptions.view');

        $q = Subscription::with([
            'user',
            'device',
            'platformInvoice:id,invoice_no,status,total,currency,subscription_id',
            'clientInvoice:id,invoice_no,status,total,currency,subscription_id',
        ])->withCount('histories');

        $visibleDeviceIds = $this->tenantScope()->visibleDeviceIdsForPanel($request->user());
        if ($visibleDeviceIds !== null) {
            $q->whereIn('device_id', $visibleDeviceIds !== [] ? $visibleDeviceIds : [0]);
        }

        if ($search = $request->query('q')) {
            $q->where(function ($query) use ($search) {
                $query->where('plan', 'like', "%{$search}%")
                    ->orWhereHas('device', fn ($d) => $d->where('name', 'like', "%{$search}%")->whereImeiLike("%{$search}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $subs = $q->orderByDesc('created_at')->paginate(15)->withQueryString();

        $subs->getCollection()->transform(function (Subscription $subscription) {
            return $this->renewals->syncExpiredStatus($subscription);
        });

        return view('admin.subscriptions.index', [
            'subs' => $subs,
            'panel' => $this->panelPrefix(),
        ]);
    }

    public function create()
    {
        $this->authorizePermission('subscriptions.manage');

        [$clients, $devicesByClient, $selectedClient] = $this->subscriptionFormClientData(auth()->user());

        return view('admin.subscriptions.create', [
            'subscription' => null,
            'clients' => $clients,
            'devicesByClient' => $devicesByClient,
            'selectedClient' => $selectedClient,
            'plans' => $this->activePlans(),
            'panel' => $this->panelPrefix(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('subscriptions.manage');

        [$data, $billing] = $this->validated($request);

        $subscription = Subscription::create($data);
        $subscription->load('device');

        $this->subscriptionBilling->provisionForSubscription($subscription, $billing, $request->user());
        $subscription->load('clientInvoice');

        $this->applyClientInvoicePaymentFromRequest($request, $subscription);

        $this->audit->logCreated($subscription, "subscription for device #{$subscription->device_id}", [
            'plan' => $subscription->plan,
            'status' => $subscription->status,
            'starts_at' => $subscription->starts_at?->toDateString(),
            'ends_at' => $subscription->ends_at?->toDateString(),
        ]);

        return redirect()
            ->to($this->panelRoute('subscriptions.index'))
            ->with('success', __('app.billing.subscription_created'));
    }

    public function edit(Subscription $subscription)
    {
        $this->authorizePermission('subscriptions.manage');
        $this->authorizeSubscription($subscription);

        $subscription->load([
            'user',
            'device',
            'subscriptionPlan',
            'clientInvoice',
        ]);

        [$clients, $devicesByClient, $selectedClient] = $this->subscriptionFormClientData(
            auth()->user(),
            $subscription
        );

        return view('admin.subscriptions.edit', [
            'subscription' => $subscription,
            'clients' => $clients,
            'devicesByClient' => $devicesByClient,
            'selectedClient' => $selectedClient,
            'plans' => $this->activePlans(),
            'panel' => $this->panelPrefix(),
        ]);
    }

    public function update(Request $request, Subscription $subscription)
    {
        $this->authorizePermission('subscriptions.manage');
        $this->authorizeSubscription($subscription);

        [$data] = $this->validated($request, $subscription);
        $previousStatus = $subscription->status;

        $subscription->update($data);
        $this->subscriptionBilling->syncClientInvoice($subscription->fresh(['device', 'subscriptionPlan', 'clientInvoice']));
        $this->subscriptionBilling->handleStatusChange($subscription->fresh(), $previousStatus, $request->user());
        $subscription->load('clientInvoice');

        $this->applyClientInvoicePaymentFromRequest($request, $subscription);

        $this->audit->logUpdated($subscription, "subscription for device #{$subscription->device_id}", [
            'plan' => $subscription->plan,
            'status' => $subscription->status,
            'starts_at' => $subscription->starts_at?->toDateString(),
            'ends_at' => $subscription->ends_at?->toDateString(),
        ]);

        return redirect()->to($this->panelRoute('subscriptions.index'))->with('success', 'Device subscription updated.');
    }

    public function markClientInvoicePaid(Subscription $subscription, Request $request)
    {
        $this->authorizePermission('subscriptions.manage');
        // Admin panel requires billing.manage; client panel managers can mark their own end-user invoices paid.
        if (! $this->isClientPanel($request)) {
            $this->authorizePermission('billing.manage');
        }
        $this->authorizeSubscription($subscription);

        $invoice = $subscription->clientInvoice;
        if (! $invoice) {
            return response()->json(['success' => false, 'message' => 'No end-user invoice found.'], 404);
        }

        if ($invoice->isCancelled() || $invoice->status === \App\Enums\BillingInvoiceStatus::Paid->value) {
            return response()->json(['success' => true]);
        }

        $amount = max(0.0, (float) $invoice->balance_due);
        if ($amount <= 0) {
            return response()->json(['success' => true]);
        }

        $payment = $request->validate([
            'payment_method' => 'nullable|string|max:50',
            'payment_reference' => 'nullable|string|max:100',
            'receipt_no' => 'nullable|string|max:100',
            'payment_notes' => 'nullable|string|max:500',
        ]);

        [$method, $reference, $notes] = $this->paymentDetailsFromInput($payment, 'Subscription listing');

        $this->billingInvoices->recordPayment(
            $invoice,
            $amount,
            $request->user(),
            method: $method,
            reference: $reference,
            notes: $notes,
        );

        return response()->json(['success' => true]);
    }

    public function cancelClientInvoice(Subscription $subscription, Request $request)
    {
        $this->authorizePermission('subscriptions.manage');
        // Admin panel requires billing.manage; client panel managers can cancel their own end-user invoices.
        if (! $this->isClientPanel($request)) {
            $this->authorizePermission('billing.manage');
        }
        $this->authorizeSubscription($subscription);

        $invoice = $subscription->clientInvoice;
        if (! $invoice) {
            return response()->json(['success' => false, 'message' => 'No end-user invoice found.'], 404);
        }

        if ($invoice->status === \App\Enums\BillingInvoiceStatus::Paid->value) {
            return response()->json(['success' => false, 'message' => 'Paid invoice cannot be cancelled.'], 422);
        }

        $this->billingInvoices->cancelInvoice($invoice, $request->user());

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, Subscription $subscription)
    {
        $this->authorizePermission('subscriptions.manage');
        $this->authorizeSubscription($subscription);

        $deviceId = $subscription->device_id;
        $subscription->loadMissing('device');

        $previousStatus = $subscription->status;
        $this->subscriptionBilling->cancelBilling($subscription, $request->user());
        if ($previousStatus !== 'cancelled') {
            $subscription->status = 'cancelled';
            $this->subscriptionBilling->handleStatusChange($subscription, $previousStatus, $request->user());
        }

        $this->audit->log('deleted', "Deleted subscription for device #{$deviceId}", $subscription, [
            'device_id' => $deviceId,
        ]);

        $subscription->delete();

        return redirect()->to($this->panelRoute('subscriptions.index'))->with('success', 'Deleted.');
    }

    public function renew(Request $request, Subscription $subscription)
    {
        $this->authorizePermission('subscriptions.manage');
        $this->authorizeSubscription($subscription);

        $data = $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
        ]);

        try {
            $renewed = $this->renewals->renew(
                $subscription,
                Carbon::parse($data['starts_at']),
                Carbon::parse($data['ends_at']),
                $request->user()
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $this->audit->log('renewed', "Renewed subscription for device #{$renewed->device_id}", $renewed, [
            'plan' => $renewed->plan,
            'starts_at' => $renewed->starts_at?->toDateString(),
            'ends_at' => $renewed->ends_at?->toDateString(),
            'status' => $renewed->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription renewed successfully.',
            'subscription' => [
                'id' => $renewed->id,
                'status' => $renewed->status,
                'starts_at' => $renewed->starts_at?->format('d M Y'),
                'ends_at' => $renewed->ends_at?->format('d M Y'),
            ],
        ]);
    }

    public function histories(Subscription $subscription)
    {
        $this->authorizePermission('subscriptions.view');
        $this->authorizeSubscription($subscription);

        $subscription->load(['device', 'user']);
        $histories = $subscription->histories()
            ->with('archivedByUser:id,name,email')
            ->get();

        return response()->json([
            'subscription' => [
                'id' => $subscription->id,
                'plan' => $subscription->plan,
                'device' => $subscription->device ? [
                    'id' => $subscription->device->id,
                    'name' => $subscription->device->name,
                    'imei' => $subscription->device->imei,
                ] : null,
                'user' => $subscription->user?->only(['id', 'name', 'email']),
                'current' => [
                    'starts_at' => $subscription->starts_at?->format('d M Y'),
                    'ends_at' => $subscription->ends_at?->format('d M Y'),
                    'status' => $subscription->status,
                ],
            ],
            'histories' => $histories->map(fn ($h) => [
                'id' => $h->id,
                'plan' => $h->plan,
                'starts_at' => $h->starts_at?->format('d M Y') ?? '—',
                'ends_at' => $h->ends_at?->format('d M Y') ?? '—',
                'status' => $h->status,
                'archived_at' => $h->archived_at?->format('d M Y H:i'),
                'archived_by' => $h->archivedByUser?->name ?? 'System',
            ]),
        ]);
    }

    public function planPricing(SubscriptionPlan $subscriptionPlan)
    {
        $this->authorizePermission('subscriptions.manage');

        return response()->json([
            'id' => $subscriptionPlan->id,
            'name' => $subscriptionPlan->name,
            'company_price' => (float) $subscriptionPlan->company_price,
            'currency' => $subscriptionPlan->currency,
            'billing_cycle' => $subscriptionPlan->billing_cycle,
            'billing_cycle_label' => $subscriptionPlan->billingCycleLabel(),
            'duration_months' => $subscriptionPlan->duration_months,
        ]);
    }

    public function devicePricing(Request $request)
    {
        $this->authorizePermission('subscriptions.manage');

        $data = $request->validate([
            'device_id' => 'required|exists:tc_devices,id',
            'client_id' => 'required|integer|exists:clients,id',
        ]);

        $clientId = $this->isClientPanel($request)
            ? $this->tenantScope()->ensureClientForManager($request->user())
            : (int) $data['client_id'];

        $this->authorizeVisibleClient($request, $clientId);

        $device = Device::query()->findOrFail($data['device_id']);

        if (! $this->tenantScope()->deviceBelongsToClient($device, $clientId)) {
            abort(403);
        }

        $cost = $this->deviceCosts->resolveForClientDevice($device, $clientId);

        return response()->json([
            'device_id' => $device->id,
            'unit_cost' => $cost,
            'currency' => 'USD',
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1?: array{subscription_plan_id: int, selling_price: float, device_selling_price: float, client_id: int, subscription_type: string}}
     */
    private function validated(Request $request, ?Subscription $subscription = null): array
    {
        $rules = [
            'device_id' => [
                'required',
                'exists:tc_devices,id',
            ],
            'starts_at' => 'required|date',
            'status' => 'required|in:active,cancelled',
            'subscription_type' => ['required', Rule::in(array_map(fn (SubscriptionType $t) => $t->value, SubscriptionType::cases()))],
        ];

        $rules['subscription_plan_id'] = 'required|exists:subscription_plans,id';
        $rules['selling_price'] = 'required|numeric|min:0';

        $subscriptionType = SubscriptionType::from(
            (string) $request->input(
                'subscription_type',
                $subscription?->subscription_type ?? SubscriptionType::New->value
            )
        );

        if ($subscriptionType === SubscriptionType::New) {
            $rules['device_selling_price'] = 'required|numeric|min:0';
        } else {
            $rules['device_selling_price'] = 'nullable|numeric|min:0';
        }

        if (! $this->isClientPanel()) {
            $rules['client_id'] = 'required|integer|exists:clients,id';
        }

        $data = $request->validate($rules);

        $clientId = $this->resolveClientIdForRequest($request);

        if ($subscription?->clientInvoice) {
            $invoice = $subscription->clientInvoice;
            $invoiceLocked = $invoice->isCancelled()
                || $invoice->status === \App\Enums\BillingInvoiceStatus::Paid->value
                || (float) $invoice->amount_paid > 0;

            if ($invoiceLocked) {
                $subscriptionType = $subscription->subscriptionTypeEnum();
            }
        }

        $device = Device::query()->findOrFail($data['device_id']);

        // Prevent duplicate active subscriptions per device.
        // Allow creating a new subscription if previous is expired (ends_at < today) or cancelled.
        $hasActive = Subscription::query()
            ->where('device_id', (int) $device->id)
            ->when($subscription, fn ($q) => $q->where('id', '!=', (int) $subscription->id))
            ->where('status', 'active')
            ->whereDate('ends_at', '>=', now()->toDateString())
            ->exists();

        if ($hasActive) {
            throw ValidationException::withMessages([
                'device_id' => 'This device already has an active subscription.',
            ]);
        }

        if (! $this->tenantScope()->deviceBelongsToClient($device, $clientId)) {
            throw ValidationException::withMessages([
                'device_id' => 'The selected device does not belong to this client.',
            ]);
        }

        $startsAt = Carbon::parse($data['starts_at'])->startOfDay();
        $plan = null;

        if (! empty($data['subscription_plan_id'])) {
            $plan = SubscriptionPlan::query()->findOrFail($data['subscription_plan_id']);
            $data['plan'] = $plan->name;
        }

        $endsAt = $plan
            ? $plan->billingCycleEnum()->endDateFrom($startsAt)
            : $startsAt->copy()->addMonth();

        $billing = null;
        if (! $subscription) {
            $billing = [
                'subscription_plan_id' => (int) $data['subscription_plan_id'],
                'selling_price' => (float) $data['selling_price'],
                'device_selling_price' => $subscriptionType === SubscriptionType::New
                    ? (float) ($data['device_selling_price'] ?? 0)
                    : 0.0,
                'client_id' => $clientId,
                'subscription_type' => $subscriptionType->value,
            ];
        }

        if ($plan) {
            $data['subscription_plan_id'] = $plan->id;
            $data['company_price'] = (float) $plan->company_price;
            $data['selling_price'] = (float) $data['selling_price'];
            $data['device_unit_cost'] = $this->deviceCosts->resolveForClientDevice($device, $clientId);
            $data['device_selling_price'] = $subscriptionType === SubscriptionType::New
                ? (float) ($data['device_selling_price'] ?? 0)
                : 0.0;
        }

        $data['subscription_type'] = $subscriptionType->value;
        $data['client_id'] = $clientId;

        if ($billing !== null) {
            unset($data['selling_price'], $data['device_selling_price']);
        }

        $data['starts_at'] = $startsAt->toDateString();
        $data['ends_at'] = $endsAt->toDateString();
        $data['user_id'] = $device->user_id;

        if (
            ($data['status'] ?? '') === 'active'
            && $endsAt->endOfDay()->isPast()
        ) {
            $data['status'] = 'expired';
        }

        return $billing !== null ? [$data, $billing] : [$data];
    }

    private function authorizeSubscription(Subscription $subscription): void
    {
        $visible = $this->tenantScope()->visibleDeviceIdsForPanel(auth()->user());

        if ($visible !== null && ! in_array((int) $subscription->device_id, $visible, true)) {
            abort(403);
        }
    }

    private function applyClientInvoicePaymentFromRequest(Request $request, Subscription $subscription): void
    {
        if ($request->input('client_invoice_status') !== 'paid') {
            return;
        }

        $subscription->loadMissing('clientInvoice');
        $invoice = $subscription->clientInvoice;
        if (! $invoice || $invoice->status === \App\Enums\BillingInvoiceStatus::Paid->value) {
            return;
        }

        $amount = max(0.0, (float) $invoice->balance_due);
        if ($amount <= 0) {
            return;
        }

        $payment = [
            'payment_method' => $request->input('client_invoice_payment_method'),
            'payment_reference' => $request->input('client_invoice_payment_reference'),
            'receipt_no' => $request->input('client_invoice_receipt_no'),
            'payment_notes' => $request->input('client_invoice_payment_notes'),
        ];

        [$method, $reference, $notes] = $this->paymentDetailsFromInput($payment, 'Subscription form');

        $this->billingInvoices->recordPayment(
            $invoice,
            $amount,
            $request->user(),
            method: $method,
            reference: $reference,
            notes: $notes,
        );
    }

    /**
     * @param  array{payment_method?:mixed,payment_reference?:mixed,receipt_no?:mixed,payment_notes?:mixed}  $payment
     * @return array{0:string,1:string,2:?string}
     */
    private function paymentDetailsFromInput(array $payment, string $defaultReference): array
    {
        $method = trim((string) ($payment['payment_method'] ?? ''));
        $reference = trim((string) ($payment['payment_reference'] ?? ''));
        $receiptNo = trim((string) ($payment['receipt_no'] ?? ''));
        $notes = trim((string) ($payment['payment_notes'] ?? ''));

        if ($receiptNo !== '') {
            $notes = trim($notes . ($notes !== '' ? ' | ' : '') . 'Receipt: ' . $receiptNo);
        }

        return [
            $method !== '' ? $method : 'Manual',
            $reference !== '' ? $reference : $defaultReference,
            $notes !== '' ? $notes : null,
        ];
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: array<int, list<array{id: int, text: string}>>, 2: int|null}
     */
    private function subscriptionFormClientData(\App\Models\User $actor, ?Subscription $subscription = null): array
    {
        $selectedClient = old(
            'client_id',
            $subscription
                ? $this->tenantScope()->clientIdForDevice($subscription->device)
                : null
        );

        if ($this->isClientPanel()) {
            $clientId = $this->tenantScope()->ensureClientForManager($actor);
            $devices = $this->tenantScope()->devicesForClient($clientId, $actor);

            return [
                collect(),
                [(string) $clientId => $devices->map(fn (Device $d) => $this->deviceOption($d))->values()->all()],
                $clientId,
            ];
        }

        $clients = $this->tenantScope()->scopeClients(Client::query(), $actor)->orderBy('name')->get();
        $devicesByClient = [];

        foreach ($clients as $client) {
            $devicesByClient[(string) $client->id] = $this->tenantScope()
                ->devicesForClient((int) $client->id, $actor)
                ->map(fn (Device $d) => $this->deviceOption($d))
                ->values()
                ->all();
        }

        return [$clients, $devicesByClient, $selectedClient ? (int) $selectedClient : null];
    }

    /**
     * @return array{id: int, text: string}
     */
    private function deviceOption(Device $d): array
    {
        return [
            'id' => $d->id,
            'text' => ($d->name ?: $d->imei) . ' · IMEI ' . $d->imei
                . ($d->user ? ' — ' . $d->user->name : ''),
        ];
    }

    private function activePlans()
    {
        return SubscriptionPlan::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('billing_cycle')
            ->get();
    }
}
