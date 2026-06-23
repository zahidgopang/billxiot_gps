/**
 * Searchable country dial-code selector (same UX as registration).
 */
(function (window) {
    'use strict';

    function normalizeCode(code) {
        if (!code) return '';
        const trimmed = String(code).trim();
        return trimmed.startsWith('+') ? trimmed : '+' + trimmed.replace(/^\+/, '');
    }

    function findCountry(countries, code) {
        const normalized = normalizeCode(code);
        return countries.find((c) => c.code === normalized)
            || countries.find((c) => c.code === code)
            || null;
    }

    function initCountryCodeSelector(container, options) {
        if (!container || container.dataset.countrySelectorInit === '1') {
            return;
        }

        const countries = options.countries || window.COUNTRIES_DIAL_CODES || [];
        const selector = container.querySelector('[data-country-selector]');
        const dropdown = container.querySelector('[data-country-dropdown]');
        const list = container.querySelector('[data-country-list]');
        const search = container.querySelector('[data-country-search]');
        const flagEl = container.querySelector('[data-country-flag]');
        const codeEl = container.querySelector('[data-country-code-display]');
        const hidden = container.querySelector('input[name="' + (options.fieldName || 'country_code') + '"]')
            || container.querySelector('[data-country-code-input]');

        if (!selector || !dropdown || !list || !hidden) {
            return;
        }

        container.dataset.countrySelectorInit = '1';

        const defaultCode = normalizeCode(options.defaultCode || hidden.value || '+966');
        let selected = findCountry(countries, defaultCode) || countries[0] || null;

        function applySelection(country) {
            if (!country) return;
            selected = country;
            hidden.value = country.code;
            if (flagEl) flagEl.textContent = country.flag;
            if (codeEl) codeEl.textContent = country.code;
        }

        function populateList(filter) {
            const q = (filter || '').trim().toLowerCase();
            list.innerHTML = '';
            const filtered = countries.filter((country) => {
                if (!q) return true;
                return country.name.toLowerCase().includes(q) || country.code.includes(q);
            });

            filtered.forEach((country) => {
                const option = document.createElement('div');
                option.className = 'country-option';
                option.setAttribute('role', 'option');
                option.innerHTML =
                    '<span class="country-option-flag">' + country.flag + '</span>' +
                    '<span class="country-option-name">' + country.name + '</span>' +
                    '<span class="country-option-code">' + country.code + '</span>';

                option.addEventListener('click', function (e) {
                    e.stopPropagation();
                    applySelection(country);
                    selector.classList.remove('active');
                    if (search) {
                        search.value = '';
                    }
                    populateList('');
                });

                list.appendChild(option);
            });
        }

        selector.addEventListener('click', function (e) {
            e.stopPropagation();
            selector.classList.toggle('active');
            if (selector.classList.contains('active') && search) {
                search.focus();
            }
        });

        document.addEventListener('click', function (e) {
            if (!container.contains(e.target)) {
                selector.classList.remove('active');
            }
        });

        if (search) {
            search.addEventListener('input', function () {
                populateList(search.value);
            });
        }

        applySelection(selected);
        populateList('');
    }

    function boot() {
        document.querySelectorAll('[data-country-phone-row]').forEach(function (row) {
            const countries = window.COUNTRIES_DIAL_CODES || [];
            const defaultCode = row.getAttribute('data-default-country-code') || '';
            initCountryCodeSelector(row, {
                countries: countries,
                defaultCode: defaultCode,
                fieldName: row.getAttribute('data-country-field') || 'country_code',
            });
        });
    }

    window.CountryCodeSelector = {
        init: initCountryCodeSelector,
        normalizeCode: normalizeCode,
        findCountry: findCountry,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window);
