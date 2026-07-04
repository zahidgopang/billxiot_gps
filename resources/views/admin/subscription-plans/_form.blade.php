@php
    use App\Enums\PlanBillingCycle;

    $featuresText = old('features_text', isset($plan) && is_array($plan->features) ? implode("\n", $plan->features) : '');
    $selectedCycle = old('billing_cycle', $plan->billing_cycle ?? PlanBillingCycle::Monthly->value);
    $notificationTypeOptions = $notificationTypeOptions ?? [];
    $routeOptions = $routeOptions ?? [];
    $selectedEmailTypes = collect($selectedEmailTypes ?? [])->map(fn ($v) => (string) $v)->all();
    $selectedWhatsappTypes = collect($selectedWhatsappTypes ?? [])->map(fn ($v) => (string) $v)->all();
    $selectedRouteIds = collect($selectedRouteIds ?? [])->map(fn ($v) => (int) $v)->all();
@endphp

<x-admin.form-section :title="__('app.billing.plan_details')" icon="fas fa-layer-group">
    <x-admin.form-col>
        <label class="admin-label" for="plan-name">{{ __('app.common.name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" id="plan-name" class="form-control form-control-sm" required
               value="{{ old('name', $plan->name ?? '') }}">
        @error('name') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col :full="true">
        <fieldset class="admin-radio-group" id="plan-billing-cycle-group">
            <legend class="admin-label mb-2">{{ __('app.billing.billing_cycle') }} <span class="text-danger">*</span></legend>
            <div class="admin-radio-group__options" role="radiogroup">
                @foreach(PlanBillingCycle::cases() as $cycle)
                    @php $cycleId = 'plan-billing-cycle-' . $cycle->value; @endphp
                    <label class="admin-radio-option" for="{{ $cycleId }}">
                        <input type="radio"
                               name="billing_cycle"
                               id="{{ $cycleId }}"
                               value="{{ $cycle->value }}"
                               class="admin-radio-option__input"
                               @checked($selectedCycle === $cycle->value)
                               @required($loop->first)>
                        <span class="admin-radio-option__label">{{ $cycle->label() }}</span>
                    </label>
                @endforeach
            </div>
            <p class="admin-hint mt-2">{{ __('app.billing.billing_cycle_hint') }}</p>
            @error('billing_cycle') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
        </fieldset>
    </x-admin.form-col>

    @if($plan)
        <x-admin.form-col>
            <label class="admin-label">{{ __('app.billing.slug') }}</label>
            <input type="text" class="form-control form-control-sm bg-light" readonly value="{{ $plan->slug }}">
            <p class="admin-hint">{{ __('app.billing.slug_auto_hint') }}</p>
        </x-admin.form-col>
    @else
        <x-admin.form-col :full="true">
            <p class="admin-hint mb-0">{{ __('app.billing.slug_auto_hint') }}</p>
        </x-admin.form-col>
    @endif

    <x-admin.form-col>
        <label class="admin-label" for="plan-company-price">{{ __('app.billing.company_price') }} <span class="text-danger">*</span></label>
        <input type="number" name="company_price" id="plan-company-price" step="0.01" min="0" class="form-control form-control-sm" required
               value="{{ old('company_price', $plan->company_price ?? '') }}">
        <p class="admin-hint">{{ __('app.billing.company_price_per_cycle') }}</p>
        @error('company_price') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="plan-currency">{{ __('app.billing.currency') }}</label>
        <input type="text" name="currency" id="plan-currency" maxlength="3" class="form-control form-control-sm"
               value="{{ old('currency', $plan->currency ?? 'USD') }}">
        @error('currency') <p class="admin-field__error text-danger">{{ $message }}</p> @enderror
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="plan-status">{{ __('app.common.status') }}</label>
        @php $status = old('status', $plan->status ?? 'active'); @endphp
        <select name="status" id="plan-status" class="form-select form-select-sm">
            <option value="active" @selected($status === 'active')>{{ __('app.common.active') }}</option>
            <option value="inactive" @selected($status === 'inactive')>{{ __('app.common.inactive') }}</option>
        </select>
    </x-admin.form-col>

    <x-admin.form-col>
        <label class="admin-label" for="plan-sort">{{ __('app.billing.sort_order') }}</label>
        <input type="number" name="sort_order" id="plan-sort" min="0" class="form-control form-control-sm"
               value="{{ old('sort_order', $plan->sort_order ?? 0) }}">
    </x-admin.form-col>

    <x-admin.form-col>
        <div class="form-check mt-4">
            <input type="checkbox" name="is_public" value="1" class="form-check-input" id="plan-public"
                   @checked(old('is_public', $plan->is_public ?? true))>
            <label class="form-check-label" for="plan-public">{{ __('app.billing.show_on_public_pricing') }}</label>
        </div>
    </x-admin.form-col>

    <x-admin.form-col :full="true">
        <label class="admin-label" for="plan-description">{{ __('app.billing.description') }}</label>
        <textarea name="description" id="plan-description" rows="2" class="form-control form-control-sm">{{ old('description', $plan->description ?? '') }}</textarea>
    </x-admin.form-col>

    <x-admin.form-col :full="true">
        <label class="admin-label" for="plan-features">{{ __('app.billing.features') }}</label>
        <textarea name="features_text" id="plan-features" rows="5" class="form-control form-control-sm" placeholder="{{ __('app.billing.features_placeholder') }}">{{ $featuresText }}</textarea>
        <p class="admin-hint">{{ __('app.billing.features_hint') }}</p>
    </x-admin.form-col>
</x-admin.form-section>

<x-admin.form-section
    :title="__('app.billing.plan_email_notifications')"
    icon="fas fa-envelope"
    :description="__('app.billing.plan_email_notifications_hint')"
>
    <x-admin.form-col :full="true">
        <div class="d-flex flex-wrap gap-2 mb-2">
            <button type="button" class="btn btn-outline-secondary btn-sm plan-notif-select-all" data-target="email">
                {{ __('app.billing.plan_notif_select_all') }}
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm plan-notif-clear-all" data-target="email">
                {{ __('app.billing.plan_notif_clear_all') }}
            </button>
            <span class="small text-muted align-self-center plan-notif-count" data-target="email"></span>
        </div>
        <div class="row g-2 plan-notification-grid" data-channel="email">
            @foreach($notificationTypeOptions as $option)
                <div class="col-md-6 col-lg-4">
                    <div class="form-check">
                        <input type="checkbox"
                               class="form-check-input plan-notif-check"
                               data-channel="email"
                               name="notification_email_types[]"
                               id="plan-email-notif-{{ $option['key'] }}"
                               value="{{ $option['key'] }}"
                               @checked(in_array($option['key'], $selectedEmailTypes, true))>
                        <label class="form-check-label" for="plan-email-notif-{{ $option['key'] }}">
                            {{ $option['label'] }}
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    </x-admin.form-col>
</x-admin.form-section>

<x-admin.form-section
    :title="__('app.billing.plan_whatsapp_notifications')"
    icon="fab fa-whatsapp"
    :description="__('app.billing.plan_whatsapp_notifications_hint')"
>
    <x-admin.form-col :full="true">
        <div class="d-flex flex-wrap gap-2 mb-2">
            <button type="button" class="btn btn-outline-secondary btn-sm plan-notif-select-all" data-target="whatsapp">
                {{ __('app.billing.plan_notif_select_all') }}
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm plan-notif-clear-all" data-target="whatsapp">
                {{ __('app.billing.plan_notif_clear_all') }}
            </button>
            <span class="small text-muted align-self-center plan-notif-count" data-target="whatsapp"></span>
        </div>
        <div class="row g-2 plan-notification-grid" data-channel="whatsapp">
            @foreach($notificationTypeOptions as $option)
                <div class="col-md-6 col-lg-4">
                    <div class="form-check">
                        <input type="checkbox"
                               class="form-check-input plan-notif-check"
                               data-channel="whatsapp"
                               name="notification_whatsapp_types[]"
                               id="plan-whatsapp-notif-{{ $option['key'] }}"
                               value="{{ $option['key'] }}"
                               @checked(in_array($option['key'], $selectedWhatsappTypes, true))>
                        <label class="form-check-label" for="plan-whatsapp-notif-{{ $option['key'] }}">
                            {{ $option['label'] }}
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    </x-admin.form-col>
</x-admin.form-section>

@if(! empty($routeOptions))
    <x-admin.form-section
        :title="__('app.billing.plan_route_notifications')"
        icon="fas fa-route"
        :description="__('app.billing.plan_route_notifications_hint')"
    >
        <x-admin.form-col :full="true">
            <div class="d-flex flex-wrap gap-2 mb-2">
                <button type="button" class="btn btn-outline-secondary btn-sm plan-notif-select-all" data-target="route">
                    {{ __('app.billing.plan_notif_select_all') }}
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm plan-notif-clear-all" data-target="route">
                    {{ __('app.billing.plan_notif_clear_all') }}
                </button>
                <span class="small text-muted align-self-center plan-notif-count" data-target="route"></span>
            </div>
            <div class="row g-2 plan-notification-grid" data-channel="route">
                @foreach($routeOptions as $route)
                    <div class="col-md-6 col-lg-4">
                        <div class="form-check">
                            <input type="checkbox"
                                   class="form-check-input plan-notif-check"
                                   data-channel="route"
                                   name="notification_route_ids[]"
                                   id="plan-route-notif-{{ $route['id'] }}"
                                   value="{{ $route['id'] }}"
                                   @checked(in_array((int) $route['id'], $selectedRouteIds, true))>
                            <label class="form-check-label" for="plan-route-notif-{{ $route['id'] }}">
                                {{ $route['label'] }}
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-admin.form-col>
    </x-admin.form-section>
@endif

@push('scripts')
<script>
(function () {
    'use strict';

    function channelSelector(channel) {
        if (channel === 'route') {
            return '.plan-notif-check[data-channel="route"]';
        }
        return `.plan-notif-check[data-channel="${channel}"]`;
    }

    function updateCount(channel) {
        const checks = document.querySelectorAll(channelSelector(channel));
        const checked = Array.from(checks).filter((cb) => cb.checked).length;
        const el = document.querySelector(`.plan-notif-count[data-target="${channel}"]`);
        if (el) {
            el.textContent = `${checked} / ${checks.length} {{ __('app.billing.plan_notif_selected') }}`;
        }
    }

    function setAll(channel, checked) {
        document.querySelectorAll(channelSelector(channel)).forEach((cb) => {
            cb.checked = checked;
        });
        updateCount(channel);
    }

    document.querySelectorAll('.plan-notif-select-all').forEach((btn) => {
        btn.addEventListener('click', () => setAll(btn.dataset.target, true));
    });

    document.querySelectorAll('.plan-notif-clear-all').forEach((btn) => {
        btn.addEventListener('click', () => setAll(btn.dataset.target, false));
    });

    document.querySelectorAll('.plan-notif-check').forEach((cb) => {
        cb.addEventListener('change', () => updateCount(cb.dataset.channel));
    });

    ['email', 'whatsapp', 'route'].forEach(updateCount);
})();
</script>
@endpush
