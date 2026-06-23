/**
 * Admin panel RTL enhancements (Arabic locale).
 */
(function () {
    'use strict';

    if (document.documentElement.getAttribute('dir') !== 'rtl') {
        return;
    }

    function patchSelect2() {
        if (typeof window.jQuery === 'undefined' || !window.jQuery.fn.select2) {
            return;
        }

        const $ = window.jQuery;
        const orig = $.fn.select2;
        $.fn.select2 = function (options) {
            const opts = typeof options === 'object' && options !== null ? { ...options } : options;
            if (typeof opts === 'object' && opts !== null) {
                opts.dir = opts.dir || 'rtl';
            }
            return orig.call(this, opts);
        };
        $.fn.select2.defaults = orig.defaults;
    }

    function initDataTablesRtl() {
        if (typeof window.jQuery === 'undefined' || !window.jQuery.fn.DataTable) {
            return;
        }

        const $ = window.jQuery;
        const arLang = {
            emptyTable: 'لا توجد بيانات متاحة في الجدول',
            info: 'عرض _START_ إلى _END_ من أصل _TOTAL_ سجل',
            infoEmpty: 'عرض 0 إلى 0 من أصل 0 سجل',
            infoFiltered: '(منتقاة من مجموع _MAX_ سجل)',
            lengthMenu: 'أظهر _MENU_ سجلات',
            loadingRecords: 'جارٍ التحميل...',
            processing: 'جارٍ المعالجة...',
            search: 'بحث:',
            zeroRecords: 'لم يعثر على أية سجلات',
            paginate: {
                first: 'الأول',
                last: 'الأخير',
                next: 'التالي',
                previous: 'السابق',
            },
        };

        $('.data-table').each(function () {
            if ($.fn.DataTable.isDataTable(this)) {
                return;
            }
            $(this).DataTable({
                responsive: true,
                pageLength: 25,
                language: arLang,
            });
        });
    }

    function markTechnicalFields() {
        document.querySelectorAll('table tbody td').forEach((td) => {
            const text = (td.textContent || '').trim();
            if (/^\d{10,20}$/.test(text)) {
                td.classList.add('admin-ltr');
                td.setAttribute('dir', 'ltr');
            }
        });

        document.querySelectorAll('input[name="imei"], input[name="q"][placeholder*="IMEI"]').forEach((el) => {
            el.classList.add('admin-ltr');
            el.setAttribute('dir', 'ltr');
        });
    }

    patchSelect2();

    document.addEventListener('DOMContentLoaded', function () {
        if (document.body.classList.contains('admin-panel')) {
            markTechnicalFields();
        }

        if (typeof window.flatpickr !== 'undefined') {
            flatpickr.localize(flatpickr.l10ns.default);
        }
    });

    window.adminInitDataTablesRtl = initDataTablesRtl;
})();
