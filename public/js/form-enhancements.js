/**
 * Global Select2 + Flatpickr for admin & user forms.
 */
(function (window, $) {
    'use strict';

    function shouldSkipSelect(el) {
        return el.classList.contains('no-select2')
            || el.id === 'dateRange'
            || el.closest('#dateRange');
    }

    function shouldSkipDate(el) {
        return el.classList.contains('no-flatpickr')
            || el.id === 'dateRange'
            || el.dataset.flatpickrManual !== undefined;
    }

    function isGlobalInitScope(root) {
        return !root || root === document || root === document.documentElement;
    }

    function formatYmd(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function getDateValue(el) {
        if (!el) {
            return '';
        }

        const fp = el._flatpickr;
        if (fp) {
            if (fp.selectedDates.length) {
                return fp.formatDate(fp.selectedDates[0], 'Y-m-d');
            }

            return (fp.input.value || '').trim();
        }

        return (el.value || '').trim();
    }

    function setDateValue(el, ymd, triggerChange) {
        if (!el) {
            return;
        }

        const fireChange = triggerChange === true;
        const fp = el._flatpickr;
        if (fp) {
            if (!ymd) {
                fp.clear();
                return;
            }

            fp.setDate(ymd, fireChange);

            if (fp.altInput && fp.selectedDates.length) {
                fp.altInput.value = fp.formatDate(fp.selectedDates[0], fp.config.altFormat);
            }

            return;
        }

        el.value = ymd || '';
        if (ymd) {
            el.setAttribute('value', ymd);
        } else {
            el.removeAttribute('value');
        }
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function appendFlatpickrOnChange(fp, handler) {
        if (!fp || typeof handler !== 'function') {
            return;
        }

        const existing = fp.config.onChange;

        fp.set('onChange', function (selectedDates, dateStr, instance) {
            if (typeof existing === 'function') {
                existing(selectedDates, dateStr, instance);
            } else if (Array.isArray(existing)) {
                existing.forEach(function (fn) {
                    if (typeof fn === 'function') {
                        fn(selectedDates, dateStr, instance);
                    }
                });
            }

            handler(selectedDates, dateStr, instance);
        });
    }

    function bindCalendarTrigger(el) {
        const field = el.closest('.admin-date-wrap__field');
        const trigger = field?.querySelector('.admin-date-wrap__trigger');
        const fp = el._flatpickr;

        if (!trigger || !fp || el.dataset.flatpickrStatic === 'true') {
            return;
        }

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            fp.open();
        });
    }

    function linkMinDateFromStart(startEl, endEl) {
        if (!startEl || !endEl) {
            return;
        }

        const applyMin = function () {
            const startDate = startEl._flatpickr?.selectedDates?.[0];

            if (startDate && endEl._flatpickr) {
                endEl._flatpickr.set('minDate', startDate);
            }
        };

        applyMin();
        startEl.addEventListener('change', applyMin);
    }

    function buildDatePickerConfig(el) {
        const config = {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'M j, Y',
            allowInput: true,
            disableMobile: true,
            clickOpens: true,
        };

        if (el.dataset.mode === 'range') {
            config.mode = 'range';
            delete config.altInput;
            delete config.altFormat;
        }

        if (el.dataset.flatpickrStatic === 'true') {
            config.clickOpens = false;
            config.allowInput = false;
            config.altInput = false;
        } else if (!config.altInputClass) {
            config.altInputClass = 'form-control form-control-sm flatpickr-input admin-ltr';
        }

        if (el.dataset.maxDate) {
            config.maxDate = el.dataset.maxDate;
        } else if (el.dataset.allowFuture !== 'true') {
            config.maxDate = 'today';
        }

        if (el.dataset.minDate) {
            config.minDate = el.dataset.minDate;
        }

        const modal = el.closest('.modal');
        if (modal) {
            config.appendTo = document.body;
        }

        return config;
    }

    function initOneDatePicker(el) {
        if (shouldSkipDate(el) || el._flatpickr) {
            return;
        }

        if (el.type === 'date') {
            el.type = 'text';
            el.classList.add('js-date-picker');
        }

        try {
            flatpickr(el, buildDatePickerConfig(el));
        } catch (e) {
            console.warn('Flatpickr init failed', e);
            return;
        }

        bindCalendarTrigger(el);

        if (el.dataset.minDateFrom) {
            const startEl = document.querySelector(el.dataset.minDateFrom);
            linkMinDateFromStart(startEl, el);
        }
    }

    function resolveDropdownParent($el) {
        const $modal = $el.closest('.modal');
        if ($modal.length) {
            return $modal;
        }

        // Always attach to body (not filter bars / forms) so dropdowns never
        // stack inside SweetAlert toasts or other fixed UI at top-end.
        return $(document.body);
    }

    function hasEmptyOption($el) {
        return $el.find('option').filter(function () {
            return String(this.value) === '';
        }).length > 0;
    }

    function initSelect2(root) {
        if (typeof $ === 'undefined' || !$.fn.select2) {
            return;
        }

        const $root = root ? $(root) : $(document);
        $root.find('select').each(function () {
            const el = this;
            const $el = $(el);

            if (shouldSkipSelect(el) || $el.hasClass('select2-hidden-accessible')) {
                return;
            }

            const inFilterBar = $el.closest('.admin-filter-bar').length > 0;
            const emptyOption = hasEmptyOption($el);
            const defaultPlaceholder = (window.APP_I18N && window.APP_I18N.selectPlaceholder)
                || 'Select…';
            const placeholder = el.getAttribute('data-placeholder')
                || (emptyOption ? $el.find('option[value=""]').first().text() : '')
                || defaultPlaceholder;

            const options = {
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: (placeholder || defaultPlaceholder).trim(),
                allowClear: !el.required && (emptyOption || inFilterBar),
                dropdownParent: resolveDropdownParent($el),
            };

            if (document.documentElement.getAttribute('dir') === 'rtl') {
                options.dir = 'rtl';
            }

            if (el.getAttribute('data-search') === 'false') {
                options.minimumResultsForSearch = Infinity;
            }

            if (inFilterBar) {
                options.selectionCssClass = 'form-select form-select-sm';
                options.dropdownCssClass = 'form-select-sm';
            }

            try {
                $el.select2(options);
            } catch (e) {
                console.warn('Select2 init failed', e);
            }
        });
    }

    function initDatePickers(root) {
        if (typeof flatpickr === 'undefined') {
            return;
        }

        const scope = root || document;
        const globalScope = isGlobalInitScope(root);

        const candidates = Array.from(
            scope.querySelectorAll('input[type="date"], .js-date-picker')
        ).filter((el) => {
            if (shouldSkipDate(el) || el._flatpickr) {
                return false;
            }

            if (globalScope && el.closest('.modal')) {
                return false;
            }

            return true;
        });

        const withLink = [];
        const withoutLink = [];

        candidates.forEach((el) => {
            if (el.dataset.minDateFrom) {
                withLink.push(el);
            } else {
                withoutLink.push(el);
            }
        });

        withoutLink.forEach(initOneDatePicker);
        withLink.forEach(initOneDatePicker);
    }

    function destroyDatePickers(root) {
        const scope = root || document;
        scope.querySelectorAll('.js-date-picker').forEach((el) => {
            if (el._flatpickr) {
                el._flatpickr.destroy();
            }
        });
    }

    function init(root) {
        initSelect2(root);
        initDatePickers(root);
    }

    window.FormEnhancements = {
        init,
        initSelect2,
        initDatePickers,
        destroyDatePickers,
        getDateValue,
        setDateValue,
        formatYmd,
        appendFlatpickrOnChange,
    };

    $(document).ready(function () {
        init();

        document.querySelectorAll('.modal').forEach((modal) => {
            modal.addEventListener('shown.bs.modal', function () {
                init(modal);
            });
        });
    });
})(window, window.jQuery);
