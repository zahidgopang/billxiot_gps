@php
    use App\Enums\PlanBillingCycle;
    use App\Enums\BillingInvoiceStatus;

    $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin');
    $selectedClient = $selectedClient ?? old('client_id');
    $selectedDeviceId = old('device_id', $subscription->device_id ?? '');
    $devicesByClient = $devicesByClient ?? [];
    $defaultStart = now()->toDateString();
    $selectedPlanId = old('subscription_plan_id', $subscription->subscription_plan_id ?? '');
    $selectedPlan = ($plans ?? collect())->firstWhere('id', (int) $selectedPlanId)
        ?? ($subscription?->subscriptionPlan ?? null);
    $defaultCycle = $selectedPlan?->billing_cycle ?? PlanBillingCycle::Monthly->value;
    $defaultEnd = $selectedPlan
        ? $selectedPlan->billingCycleEnum()->endDateFrom(now()->startOfDay())->toDateString()
        : PlanBillingCycle::Monthly->endDateFrom(now()->startOfDay())->toDateString();
    $startsAt = old('starts_at', optional(optional($subscription)->starts_at)->toDateString() ?? $defaultStart);
    $endsAt = old('ends_at', optional(optional($subscription)->ends_at)->toDateString() ?? $defaultEnd);

    $initialCurrency = $selectedPlan?->currency ?? 'USD';
    $companyPriceAmount = $subscription
        ? (float) ($subscription->company_price ?? $selectedPlan?->company_price ?? 0)
        : (float) ($selectedPlan?->company_price ?? 0);
    $initialCompanyPrice = $companyPriceAmount > 0 ? number_format($companyPriceAmount, 2) : '';
    $sellingPriceValue = old('selling_price', $subscription?->selling_price ?? '');
    $deviceSellingValue = old('device_selling_price', $subscription?->device_selling_price ?? '');
    $deviceUnitCost = $subscription ? (float) ($subscription->device_unit_cost ?? 0) : 0;
    $deviceCostDisplay = $deviceUnitCost > 0 ? $initialCurrency . ' ' . number_format($deviceUnitCost, 2) : '';
    $clientInvoiceStatus = $subscription?->clientInvoice?->status ?? null;
    $invoicePaid = $clientInvoiceStatus === BillingInvoiceStatus::Paid->value;
    $invoiceCancelled = $clientInvoiceStatus === BillingInvoiceStatus::Cancelled->value;
    $invoiceLocked = $invoicePaid || $invoiceCancelled
        || ((float) ($subscription?->clientInvoice?->amount_paid ?? 0) > 0);
    $invoiceSelectValue = old('client_invoice_status', $invoicePaid ? 'paid' : 'unpaid');
    $subscriptionType = old(
        'subscription_type',
        $subscription?->subscription_type ?? \App\Enums\SubscriptionType::New->value
    );
    $isNewSubscription = $subscriptionType === \App\Enums\SubscriptionType::New->value;
@endphp

<x-admin.form-section
    :title="__('app.forms.assignment')"
    icon="fas fa-satellite-dish"
    :description="__('app.forms.assignment_subscription_hint')"
>
    @if($panel === 'admin' && !empty($clients) && $clients->count())
        <x-admin.form-col>
            <label class="admin-label" for="subscription-client-id">{{ __('app.forms.client_company') }} <span class="text-danger">*</span></label>
            <select name="client_id" id="subscription-client-id" class="form-select form-select-sm" required data-placeholder="{{ __('app.forms.select_client') }}">
                <option value="">{{ __('app.forms.select_client') }}</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}" @selected((string) $selectedClient === (string) $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
            @error('client_id') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
        </x-admin.form-col>
    @endif

    <x-admin.form-col>
        <label class="admin-label" for="subscription-device-id">{{ __('app.forms.device') }} <span class="text-danger">*</span></label>
        <select name="device_id" id="subscription-device-id"
            class="form-select form-select-sm"
            required
            data-placeholder="{{ __('app.forms.select_device') }}">
            <option value="">{{ $panel === 'admin' ? __('app.forms.select_client_first') : __('app.forms.select_device_option') }}</option>
            @if($panel === 'client' && $selectedClient && !empty($devicesByClient[(string) $selectedClient]))
                @foreach($devicesByClient[(string) $selectedClient] as $d)
                    <option value="{{ $d['id'] }}" @selected((string) $selectedDeviceId === (string) $d['id'])>{{ $d['text'] }}</option>
                @endforeach
            @elseif($selectedClient && !empty($devicesByClient[(string) $selectedClient]))
                @foreach($devicesByClient[(string) $selectedClient] as $d)
                    <option value="{{ $d['id'] }}" @selected((string) $selectedDeviceId === (string) $d['id'])>{{ $d['text'] }}</option>
                @endforeach
            @endif
        </select>
        @if($panel === 'admin')
            <p class="admin-hint">{{ __('app.forms.subscription_client_first_hint') }}</p>
        @else
            <p class="admin-hint">{{ __('app.forms.subscription_device_hint') }}</p>
        @endif
        @error('device_id') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col id="subscription-device-cost-wrap" @class(['d-none' => ! $isNewSubscription])>
        <label class="admin-label" for="subscription-device-cost">{{ __('app.billing.device_purchase_cost') }}</label>
        <input type="text"
               id="subscription-device-cost"
               class="form-control form-control-sm bg-light admin-ltr"
               dir="ltr"
               readonly
               value="{{ $deviceCostDisplay }}"
               placeholder="{{ __('app.billing.select_device_to_see_cost') }}"
               data-cost="{{ $subscription ? $deviceUnitCost : '' }}">
        <p class="admin-hint mb-0">{{ __('app.billing.select_device_to_see_cost') }}</p>
    </x-admin.form-col>
</x-admin.form-section>

<x-admin.form-section
    :title="__('app.forms.subscription_details')"
    icon="fas fa-calendar-check"
    :description="__('app.forms.subscription_details_hint')"
>
    <x-admin.form-col>
        <label class="admin-label" for="subscription-type">{{ __('app.billing.subscription_type') }} <span class="text-danger">*</span></label>
        <select name="subscription_type" id="subscription-type" class="form-select form-select-sm" required data-search="false" @disabled($invoiceLocked)>
            <option value="new" @selected($subscriptionType === 'new')>{{ __('app.billing.subscription_type_new') }}</option>
            <option value="renew" @selected($subscriptionType === 'renew')>{{ __('app.billing.subscription_type_renew') }}</option>
        </select>
        @if($invoiceLocked)
            <input type="hidden" name="subscription_type" value="{{ $subscriptionType }}">
        @endif
        @if($invoiceLocked)
            <p class="admin-hint mb-0">{{ __('app.billing.subscription_type_locked_hint') }}</p>
        @else
            <p class="admin-hint mb-0">{{ __('app.billing.subscription_type_hint') }}</p>
        @endif
        @error('subscription_type') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>
    <x-admin.form-col>
        <label class="admin-label" for="subscription-plan-id">{{ __('app.billing.subscription_plan') }} <span class="text-danger">*</span></label>
        <select name="subscription_plan_id" id="subscription-plan-id" class="form-select form-select-sm" required data-placeholder="{{ __('app.forms.select_plan') }}">
            <option value="">{{ __('app.forms.select_plan') }}</option>
            @foreach(($plans ?? []) as $p)
                <option value="{{ $p->id }}"
                        data-company-price="{{ $p->company_price }}"
                        data-currency="{{ $p->currency }}"
                        data-billing-cycle="{{ $p->billing_cycle }}"
                        data-billing-cycle-label="{{ $p->billingCycleLabel() }}"
                        @selected((string) $selectedPlanId === (string) $p->id)>{{ $p->displayLabel() }}</option>
            @endforeach
        </select>
        @error('subscription_plan_id') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="subscription-company-price">{{ __('app.billing.company_price') }}</label>
        <input type="text"
               id="subscription-company-price"
               class="form-control form-control-sm bg-light admin-ltr"
               dir="ltr"
               readonly
               value="{{ $initialCompanyPrice !== '' ? $initialCurrency . ' ' . $initialCompanyPrice : '' }}"
               data-company-price="{{ $companyPriceAmount > 0 ? $companyPriceAmount : ($selectedPlan?->company_price ?? '') }}"
               placeholder="{{ __('app.billing.select_plan_to_see_price') }}">
        <p class="admin-hint">{{ __('app.billing.company_price_hint') }}</p>
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="subscription-billing-cycle">{{ __('app.billing.billing_cycle') }}</label>
        <input type="text"
               id="subscription-billing-cycle"
               class="form-control form-control-sm bg-light"
               readonly
               value="{{ $selectedPlan ? $selectedPlan->billingCycleLabel() : ($subscription?->subscriptionPlan?->billingCycleLabel() ?? '') }}"
               placeholder="{{ __('app.forms.select_plan_first') }}">
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="subscription-selling-price">{{ __('app.billing.end_user_selling_price') }} <span class="text-danger">*</span></label>
        <input type="number" name="selling_price" id="subscription-selling-price" step="0.01" min="0"
               class="form-control form-control-sm admin-ltr" dir="ltr" required
               value="{{ $sellingPriceValue }}">
        @error('selling_price') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="subscription-profit-margin">{{ __('app.billing.subscription_profit') }}</label>
        <input type="text" id="subscription-profit-margin" class="form-control form-control-sm bg-light admin-ltr" dir="ltr" readonly value="—">
    </x-admin.form-col>

    <x-admin.form-col id="subscription-device-selling-wrap" @class(['d-none' => ! $isNewSubscription])>
        <label class="admin-label" for="subscription-device-selling-price">{{ __('app.billing.device_selling_price') }} <span class="text-danger subscription-type-new-required">*</span></label>
        <input type="number" name="device_selling_price" id="subscription-device-selling-price" step="0.01" min="0"
               class="form-control form-control-sm admin-ltr" dir="ltr"
               value="{{ $deviceSellingValue }}">
        @error('device_selling_price') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col id="subscription-device-profit-wrap" @class(['d-none' => ! $isNewSubscription])>
        <label class="admin-label" for="subscription-device-profit">{{ __('app.billing.device_profit') }}</label>
        <input type="text" id="subscription-device-profit" class="form-control form-control-sm bg-light admin-ltr" dir="ltr" readonly value="—">
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="subscription-client-invoice-status">Invoice status (end user)</label>
        <select name="client_invoice_status" id="subscription-client-invoice-status"
                class="form-select form-select-sm" @disabled($invoicePaid || $invoiceCancelled) data-search="false">
            <option value="unpaid" @selected($invoiceSelectValue === 'unpaid')>Unpaid</option>
            <option value="paid" @selected($invoiceSelectValue === 'paid')>Paid</option>
        </select>
        <input type="hidden" name="client_invoice_payment_method" id="subscription-client-invoice-payment-method" value="{{ old('client_invoice_payment_method', '') }}">
        <input type="hidden" name="client_invoice_payment_reference" id="subscription-client-invoice-payment-reference" value="{{ old('client_invoice_payment_reference', '') }}">
        <input type="hidden" name="client_invoice_receipt_no" id="subscription-client-invoice-receipt-no" value="{{ old('client_invoice_receipt_no', '') }}">
        <input type="hidden" name="client_invoice_payment_notes" id="subscription-client-invoice-payment-notes" value="{{ old('client_invoice_payment_notes', '') }}">
        @if($invoicePaid)
            <p class="admin-hint mb-0 text-success">Already paid. Cannot cancel.</p>
        @elseif($invoiceCancelled)
            <p class="admin-hint mb-0 text-muted">Invoice cancelled.</p>
        @else
            <p class="admin-hint mb-0">If you choose Paid, you can optionally record payment details (cash/bank, reference, receipt).</p>
        @endif
    </x-admin.form-col>

    @include('admin.subscriptions._payment-modal')

    <x-admin.form-col :full="true">
        <div class="border rounded-3 p-3 bg-light subscription-pricing-summary">
            <h6 class="fw-semibold mb-3"><i class="fas fa-calculator me-2 text-primary"></i>{{ __('app.billing.pricing_summary') }}</h6>
            <div class="row g-3">
                <div class="col-sm-4">
                    <div class="small text-muted">{{ __('app.billing.total_company_cost') }}</div>
                    <div class="fw-bold fs-5 admin-ltr" dir="ltr" id="subscription-total-company">—</div>
                </div>
                <div class="col-sm-4">
                    <div class="small text-muted">{{ __('app.billing.total_end_user_price') }}</div>
                    <div class="fw-bold fs-5 text-primary admin-ltr" dir="ltr" id="subscription-total-end-user">—</div>
                </div>
                <div class="col-sm-4">
                    <div class="small text-muted">{{ __('app.billing.total_profit') }}</div>
                    <div class="fw-bold fs-5 text-success admin-ltr" dir="ltr" id="subscription-total-profit">—</div>
                </div>
            </div>
        </div>
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="subscription-starts-at">{{ __('app.forms.starts_at') }} <span class="text-danger">*</span></label>
        <x-admin.date-input
            name="starts_at"
            id="subscription-starts-at"
            :value="$startsAt"
            :required="true"
            :allow-future="true"
        />
        @error('starts_at') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="subscription-ends-at">{{ __('app.forms.ends_at') }}</label>
        <div class="admin-date-wrap admin-date-wrap--static">
            <div class="admin-date-wrap__field">
                <input type="text"
                       name="ends_at"
                       id="subscription-ends-at"
                       value="{{ $endsAt }}"
                       class="form-control form-control-sm bg-light admin-ltr no-flatpickr"
                       dir="ltr"
                       readonly
                       tabindex="-1"
                       autocomplete="off"
                       data-subscription-end-date="1">
                <span class="admin-date-wrap__trigger" aria-hidden="true">
                    <i class="fas fa-calendar-alt"></i>
                </span>
            </div>
        </div>
        <p class="admin-hint">{{ __('app.forms.ends_at_auto_hint') }}</p>
        @error('ends_at') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="subscription-status">{{ __('app.common.status') }}</label>
        @php $status = old('status', $subscription->status ?? 'active'); @endphp
        <select name="status" id="subscription-status" class="form-select form-select-sm" required data-search="false">
            <option value="active" @selected($status === 'active')>{{ __('app.common.active') }}</option>
            <option value="cancelled" @selected($status === 'cancelled')>{{ __('app.forms.cancelled') }}</option>
        </select>
        @error('status') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>
</x-admin.form-section>
