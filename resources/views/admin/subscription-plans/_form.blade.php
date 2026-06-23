@php
    use App\Enums\PlanBillingCycle;

    $featuresText = old('features_text', isset($plan) && is_array($plan->features) ? implode("\n", $plan->features) : '');
    $selectedCycle = old('billing_cycle', $plan->billing_cycle ?? PlanBillingCycle::Monthly->value);
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
