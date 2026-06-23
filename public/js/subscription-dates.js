/**
 * Subscription create/edit: plan billing cycle (monthly/yearly) sets end date from start.
 */
(function () {
    'use strict';

    let syncingDates = false;

    function findForm() {
        return document.getElementById('subscription-form')
            || document.querySelector('form[data-subscription-dates]');
    }

    function todayLocal() {
        const d = new Date();
        d.setHours(0, 0, 0, 0);
        return d;
    }

    function parseYmd(str) {
        if (!str) return null;
        const parts = String(str).trim().split('-');
        if (parts.length !== 3) return null;
        const dt = new Date(
            parseInt(parts[0], 10),
            parseInt(parts[1], 10) - 1,
            parseInt(parts[2], 10)
        );
        return isNaN(dt.getTime()) ? null : dt;
    }

    function formatYmd(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function endDateFromBillingCycle(start, cycle) {
        if (!start || !cycle) return null;
        const end = new Date(start.getTime());
        if (cycle === 'yearly') {
            end.setFullYear(end.getFullYear() + 1);
        } else {
            end.setMonth(end.getMonth() + 1);
        }
        return end;
    }

    function readStartYmd(input) {
        if (!input) return '';
        if (window.FormEnhancements && typeof window.FormEnhancements.getDateValue === 'function') {
            return window.FormEnhancements.getDateValue(input);
        }
        return (input.value || '').trim();
    }

    function writeStartYmd(input, ymd) {
        if (!input) return;

        const fp = input._flatpickr;
        if (fp) {
            fp.setDate(ymd, false);
            if (fp.altInput && fp.selectedDates.length) {
                fp.altInput.value = fp.formatDate(fp.selectedDates[0], fp.config.altFormat);
            }
            return;
        }

        input.value = ymd;
        input.setAttribute('value', ymd);
    }

    function writeEndYmd(input, ymd) {
        if (!input) return;
        input.value = ymd;
        input.setAttribute('value', ymd);
    }

    function planSelectValue(planSelect) {
        if (!planSelect) {
            return '';
        }
        const $ = window.jQuery;
        if ($ && $.fn.select2 && $(planSelect).hasClass('select2-hidden-accessible')) {
            const val = $(planSelect).val();
            return val == null ? '' : String(val);
        }
        return planSelect.value || '';
    }

    function selectedPlanOption(planSelect) {
        const planId = planSelectValue(planSelect);
        if (!planId || !planSelect) {
            return null;
        }
        return Array.from(planSelect.options).find(function (opt) {
            return opt.value === planId;
        }) || null;
    }

    function selectedBillingCycle(form) {
        const planSelect = form.querySelector('#subscription-plan-id');
        const opt = selectedPlanOption(planSelect);
        return opt ? (opt.dataset.billingCycle || 'monthly') : null;
    }

    function updateBillingCycleLabel(form) {
        const planSelect = form.querySelector('#subscription-plan-id');
        const labelInput = form.querySelector('#subscription-billing-cycle');
        if (!labelInput || !planSelect) {
            return;
        }
        const opt = selectedPlanOption(planSelect);
        labelInput.value = opt
            ? (opt.dataset.billingCycleLabel || opt.dataset.billingCycle || '')
            : '';
    }

    function applyPlanDates(form, resetStart) {
        if (syncingDates) {
            return;
        }

        const cycle = selectedBillingCycle(form);
        const startsInput = form.querySelector('#subscription-starts-at');
        const endsInput = form.querySelector('#subscription-ends-at');

        updateBillingCycleLabel(form);

        if (!cycle || !startsInput || !endsInput) {
            return;
        }

        syncingDates = true;

        try {
            const start = resetStart
                ? todayLocal()
                : (parseYmd(readStartYmd(startsInput)) || todayLocal());

            const startYmd = formatYmd(start);
            writeStartYmd(startsInput, startYmd);

            const end = endDateFromBillingCycle(start, cycle);
            if (end) {
                writeEndYmd(endsInput, formatYmd(end));
            }
        } finally {
            syncingDates = false;
        }
    }

    function hookStartPickerManualChange(form, startsInput) {
        if (!startsInput._flatpickr || startsInput.dataset.fpStartHooked === '1') {
            return;
        }

        startsInput.dataset.fpStartHooked = '1';
        const fp = startsInput._flatpickr;
        const previous = fp.config.onChange;

        fp.set('onChange', function (selectedDates, dateStr, instance) {
            if (typeof previous === 'function') {
                previous(selectedDates, dateStr, instance);
            } else if (Array.isArray(previous)) {
                previous.forEach(function (fn) {
                    if (typeof fn === 'function') {
                        fn(selectedDates, dateStr, instance);
                    }
                });
            }

            if (syncingDates) {
                return;
            }

            applyPlanDates(form, false);
        });
    }

    function bindSubscriptionForm(form) {
        const startsInput = form.querySelector('#subscription-starts-at');
        const endsInput = form.querySelector('#subscription-ends-at');
        const planSelect = form.querySelector('#subscription-plan-id');

        if (!planSelect || !startsInput || !endsInput) {
            return;
        }

        if (!form.dataset.subscriptionDatesBound) {
            form.dataset.subscriptionDatesBound = '1';

            function onPlanDatesChange() {
                applyPlanDates(form, true);
            }

            form.addEventListener('change', function (event) {
                if (event.target && event.target.id === 'subscription-plan-id') {
                    onPlanDatesChange();
                }
            });

            const $ = window.jQuery;
            if ($ && $.fn.select2) {
                $(planSelect).on('change select2:select select2:clear', onPlanDatesChange);
            }
        }

        hookStartPickerManualChange(form, startsInput);

        if (!form.dataset.subscriptionDatesApplied) {
            form.dataset.subscriptionDatesApplied = '1';

            const isCreate = form.dataset.subscriptionCreate === '1';
            const endVal = (endsInput.value || '').trim();

            if (isCreate || !endVal) {
                applyPlanDates(form, true);
            } else {
                updateBillingCycleLabel(form);
                applyPlanDates(form, false);
            }
        }
    }

    function init() {
        const form = findForm();
        if (!form) {
            return;
        }

        if (window.FormEnhancements?.initDatePickers) {
            window.FormEnhancements.initDatePickers(form);
        }

        bindSubscriptionForm(form);

        window.setTimeout(function () {
            if (window.FormEnhancements?.initDatePickers) {
                window.FormEnhancements.initDatePickers(form);
            }
            bindSubscriptionForm(form);
        }, 250);
    }

    if (window.jQuery) {
        window.jQuery(init);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.SubscriptionBillingDates = {
        apply: applyPlanDates,
        bind: bindSubscriptionForm,
    };
})();
