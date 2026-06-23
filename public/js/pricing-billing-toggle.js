(function () {
    'use strict';

    function initPricingBillingToggle() {
        const root = document.getElementById('pricing-plans-root');
        if (!root) {
            return;
        }

        const buttons = root.querySelectorAll('[data-billing-toggle]');
        const cards = root.querySelectorAll('[data-plan-cycle]');
        const emptyMonthly = root.querySelector('[data-pricing-empty="monthly"]');
        const emptyYearly = root.querySelector('[data-pricing-empty="yearly"]');
        const grid = root.querySelector('[data-pricing-grid]');

        if (!buttons.length || !cards.length) {
            return;
        }

        const activeClasses = ['bg-white', 'dark:bg-slate-900', 'shadow-sm', 'text-slate-900', 'dark:text-white'];
        const inactiveClasses = ['text-slate-500', 'dark:text-slate-400'];

        function setActiveButton(cycle) {
            buttons.forEach(function (btn) {
                const isActive = btn.dataset.billingToggle === cycle;
                activeClasses.forEach(function (c) {
                    btn.classList.toggle(c, isActive);
                });
                inactiveClasses.forEach(function (c) {
                    btn.classList.toggle(c, !isActive);
                });
                btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        }

        function applyCycle(cycle) {
            let visible = 0;
            cards.forEach(function (card) {
                const show = card.dataset.planCycle === cycle;
                card.classList.toggle('hidden', !show);
                if (show) {
                    visible++;
                }
            });

            if (emptyMonthly) {
                emptyMonthly.classList.toggle('hidden', cycle !== 'monthly' || visible > 0);
            }
            if (emptyYearly) {
                emptyYearly.classList.toggle('hidden', cycle !== 'yearly' || visible > 0);
            }
            if (grid) {
                grid.classList.toggle('hidden', visible === 0);
            }

            setActiveButton(cycle);
            root.dataset.activeCycle = cycle;
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyCycle(btn.dataset.billingToggle);
            });
        });

        const defaultCycle = root.dataset.defaultCycle || 'monthly';
        applyCycle(defaultCycle);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPricingBillingToggle);
    } else {
        initPricingBillingToggle();
    }
})();
