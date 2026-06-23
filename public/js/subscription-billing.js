(function () {
    'use strict';

    function boot() {
        const form = document.getElementById('subscription-form');
        if (!form || form.dataset.subscriptionBilling !== '1') {
            return;
        }

        const planSelect = document.getElementById('subscription-plan-id');
        const clientSelect = document.getElementById('subscription-client-id');
        const deviceSelect = document.getElementById('subscription-device-id');
        const companyPriceEl = document.getElementById('subscription-company-price');
        const billingCycleEl = document.getElementById('subscription-billing-cycle');
        const sellingPriceEl = document.getElementById('subscription-selling-price');
        const subscriptionProfitEl = document.getElementById('subscription-profit-margin');
        const deviceCostEl = document.getElementById('subscription-device-cost');
        const deviceSellingEl = document.getElementById('subscription-device-selling-price');
        const deviceProfitEl = document.getElementById('subscription-device-profit');
        const subscriptionTypeEl = document.getElementById('subscription-type');
        const deviceCostWrap = document.getElementById('subscription-device-cost-wrap');
        const deviceSellingWrap = document.getElementById('subscription-device-selling-wrap');
        const deviceProfitWrap = document.getElementById('subscription-device-profit-wrap');
        const totalCompanyEl = document.getElementById('subscription-total-company');
        const totalEndUserEl = document.getElementById('subscription-total-end-user');
        const totalProfitEl = document.getElementById('subscription-total-profit');
        const routes = window.subscriptionBillingRoutes || {};
        const $ = window.jQuery;
        let deviceCostValue = 0;
        let planCurrency = 'USD';

        function selectValue(el) {
            if (!el) {
                return '';
            }
            if ($ && $.fn.select2 && $(el).hasClass('select2-hidden-accessible')) {
                const val = $(el).val();
                return val == null ? '' : String(val);
            }
            return el.value || '';
        }

        function fmt(n) {
            const v = parseFloat(n);
            return Number.isFinite(v) ? v.toFixed(2) : '0.00';
        }

        function selectedPlanOption() {
            const planId = selectValue(planSelect);
            if (!planId || !planSelect) {
                return null;
            }
            return Array.from(planSelect.options).find(function (opt) {
                return opt.value === planId;
            }) || null;
        }

        function currency() {
            return selectedPlanOption()?.dataset.currency || planCurrency || 'USD';
        }

        function fmtMoney(n) {
            return currency() + ' ' + fmt(n);
        }

        function companyPriceValue() {
            const opt = selectedPlanOption();
            if (!opt) {
                return parseFloat(companyPriceEl?.dataset.companyPrice || 0) || 0;
            }
            return parseFloat(opt.dataset.companyPrice || 0) || 0;
        }

        function clearPlanFields() {
            planCurrency = 'USD';
            if (companyPriceEl) {
                companyPriceEl.value = '';
                companyPriceEl.dataset.companyPrice = '';
            }
            if (billingCycleEl) {
                billingCycleEl.value = '';
            }
            refreshTotals();
        }

        function applyPlanPricing(data) {
            const company = parseFloat(data.company_price || 0) || 0;
            planCurrency = data.currency || 'USD';

            if (companyPriceEl) {
                companyPriceEl.value = planCurrency + ' ' + fmt(company);
                companyPriceEl.dataset.companyPrice = String(company);
            }
            if (billingCycleEl) {
                billingCycleEl.value = data.billing_cycle_label || data.billing_cycle || '';
            }

            if (sellingPriceEl && sellingPriceEl.value === '') {
                sellingPriceEl.value = fmt(company);
            }

            refreshTotals();
        }

        function applyPlanFromOption(opt) {
            if (!opt || !opt.value) {
                clearPlanFields();
                return false;
            }

            applyPlanPricing({
                company_price: opt.dataset.companyPrice,
                currency: opt.dataset.currency,
                billing_cycle: opt.dataset.billingCycle,
                billing_cycle_label: opt.dataset.billingCycleLabel || opt.dataset.billingCycle,
            });

            return true;
        }

        function planPricingRequestUrl(planId) {
            if (!routes.planPricingUrl) {
                return '';
            }
            return routes.planPricingUrl.replace('__PLAN__', encodeURIComponent(planId));
        }

        async function fetchPlanPricing() {
            const planId = selectValue(planSelect);

            if (!planId) {
                clearPlanFields();
                return;
            }

            const opt = selectedPlanOption();
            if (opt && applyPlanFromOption(opt)) {
                if (window.SubscriptionBillingDates?.apply) {
                    window.SubscriptionBillingDates.apply(form, true);
                }
            }

            if (!routes.planPricingUrl) {
                return;
            }

            if (companyPriceEl && !opt) {
                companyPriceEl.value = '…';
            }

            try {
                const res = await fetch(planPricingRequestUrl(planId), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    if (!opt) {
                        clearPlanFields();
                    }
                    return;
                }
                const data = await res.json();
                applyPlanPricing(data);
                if (window.SubscriptionBillingDates?.apply) {
                    window.SubscriptionBillingDates.apply(form, true);
                }
            } catch (e) {
                if (!opt) {
                    clearPlanFields();
                }
            }
        }

        function updateSubscriptionProfit() {
            const company = companyPriceValue();
            const selling = parseFloat(sellingPriceEl?.value || 0) || 0;
            const profit = selling - company;
            if (subscriptionProfitEl) {
                subscriptionProfitEl.value = optOrDash(profit, company > 0 || selling > 0);
            }
            return profit;
        }

        function optOrDash(amount, hasData) {
            if (!hasData) {
                return '—';
            }
            return fmtMoney(amount);
        }

        function updateDeviceProfit() {
            const cost = deviceCostValue;
            const selling = parseFloat(deviceSellingEl?.value || 0) || 0;
            const profit = selling - cost;
            if (deviceProfitEl) {
                deviceProfitEl.value = optOrDash(profit, cost > 0 || selling > 0);
            }
            return profit;
        }

        function isNewSubscriptionType() {
            return (subscriptionTypeEl?.value || 'new') === 'new';
        }

        function applySubscriptionTypeUi() {
            const isNew = isNewSubscriptionType();

            [deviceCostWrap, deviceSellingWrap, deviceProfitWrap].forEach(function (el) {
                if (el) {
                    el.classList.toggle('d-none', !isNew);
                }
            });

            document.querySelectorAll('.subscription-type-new-required').forEach(function (el) {
                el.classList.toggle('d-none', !isNew);
            });

            if (deviceSellingEl) {
                deviceSellingEl.required = isNew;
                if (!isNew) {
                    deviceSellingEl.value = '0';
                }
            }

            refreshTotals();
        }

        function refreshTotals() {
            const company = companyPriceValue();
            const subSelling = parseFloat(sellingPriceEl?.value || 0) || 0;
            const devSelling = isNewSubscriptionType()
                ? (parseFloat(deviceSellingEl?.value || 0) || 0)
                : 0;

            const subProfit = updateSubscriptionProfit();
            const devProfit = isNewSubscriptionType() ? updateDeviceProfit() : 0;

            const totalCompany = company;
            const totalEndUser = subSelling + devSelling;
            const totalProfit = subProfit + devProfit;

            const hasPlan = !!selectValue(planSelect);

            if (totalCompanyEl) {
                totalCompanyEl.textContent = hasPlan ? fmtMoney(totalCompany) : '—';
            }
            if (totalEndUserEl) {
                totalEndUserEl.textContent = (subSelling > 0 || devSelling > 0) ? fmtMoney(totalEndUser) : '—';
            }
            if (totalProfitEl) {
                const hasAnyPricing = hasPlan || subSelling !== 0 || devSelling !== 0 || (isNewSubscriptionType() && deviceCostValue !== 0);
                totalProfitEl.textContent = hasAnyPricing ? fmtMoney(totalProfit) : '—';

                // Visual cue: green for profit, red for loss.
                totalProfitEl.classList.toggle('text-success', totalProfit >= 0);
                totalProfitEl.classList.toggle('text-danger', totalProfit < 0);
            }
        }

        function clearDeviceCost() {
            deviceCostValue = 0;
            if (deviceCostEl) {
                deviceCostEl.value = '';
                deviceCostEl.dataset.cost = '';
                deviceCostEl.placeholder = deviceCostEl.getAttribute('data-placeholder-text') || '';
            }
            refreshTotals();
        }

        function setDeviceCost(unitCost, cur) {
            deviceCostValue = parseFloat(unitCost || 0) || 0;
            const displayCurrency = cur || currency();

            if (deviceCostEl) {
                deviceCostEl.value = displayCurrency + ' ' + fmt(deviceCostValue);
                deviceCostEl.dataset.cost = String(deviceCostValue);
            }

            if (deviceSellingEl && (deviceSellingEl.value === '' || deviceSellingEl.value === '0')) {
                deviceSellingEl.value = fmt(deviceCostValue);
            }

            refreshTotals();
        }

        async function fetchDeviceCost() {
            const deviceId = selectValue(deviceSelect);
            const clientId = selectValue(clientSelect) || (routes.clientId != null ? String(routes.clientId) : '');

            if (!deviceId || !clientId || !routes.devicePricing) {
                clearDeviceCost();
                return;
            }

            if (deviceCostEl) {
                deviceCostEl.value = '…';
            }

            const url = new URL(routes.devicePricing, window.location.origin);
            url.searchParams.set('device_id', deviceId);
            url.searchParams.set('client_id', clientId);

            try {
                const res = await fetch(url.toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    clearDeviceCost();
                    return;
                }
                const data = await res.json();
                setDeviceCost(data.unit_cost, data.currency);
            } catch (e) {
                clearDeviceCost();
            }
        }

        function onPlanChange() {
            fetchPlanPricing();
        }

        if (deviceCostEl) {
            deviceCostEl.setAttribute('data-placeholder-text', deviceCostEl.placeholder || '');
        }

        planSelect?.addEventListener('change', onPlanChange);
        if ($ && planSelect) {
            $(planSelect).on('change select2:select select2:clear', onPlanChange);
        }
        subscriptionTypeEl?.addEventListener('change', applySubscriptionTypeUi);
        sellingPriceEl?.addEventListener('input', refreshTotals);
        deviceSellingEl?.addEventListener('input', refreshTotals);

        deviceSelect?.addEventListener('change', fetchDeviceCost);
        if ($ && deviceSelect) {
            $(deviceSelect).on('change select2:select select2:clear', fetchDeviceCost);
        }

        clientSelect?.addEventListener('change', function () {
            if (deviceSelect && $ && $.fn.select2 && $(deviceSelect).hasClass('select2-hidden-accessible')) {
                $(deviceSelect).val('').trigger('change');
            } else if (deviceSelect) {
                deviceSelect.value = '';
            }
            clearDeviceCost();
        });

        document.addEventListener('subscription:devices-ready', fetchDeviceCost);

        if (window.SubscriptionPaymentModal?.initForm) {
            window.SubscriptionPaymentModal.initForm();
        }

        applySubscriptionTypeUi();

        if (selectValue(planSelect)) {
            onPlanChange();
        }

        const prefilledDeviceCost = deviceCostEl?.dataset.cost;
        if (selectValue(deviceSelect)) {
            if (prefilledDeviceCost !== '' && prefilledDeviceCost != null) {
                deviceCostValue = parseFloat(prefilledDeviceCost) || 0;
                refreshTotals();
            } else {
                fetchDeviceCost();
            }
        } else if (prefilledDeviceCost !== '' && prefilledDeviceCost != null) {
            deviceCostValue = parseFloat(prefilledDeviceCost) || 0;
            refreshTotals();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else if (window.jQuery) {
        window.jQuery(boot);
    } else {
        boot();
    }
})();
