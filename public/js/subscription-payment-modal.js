(function () {
    'use strict';

    function getModal() {
        const el = document.getElementById('subscriptionPaymentModal');
        if (!el || !window.bootstrap?.Modal) {
            return null;
        }
        return {
            el,
            instance: window.bootstrap.Modal.getOrCreateInstance(el),
            method: document.getElementById('subscriptionPaymentMethod'),
            reference: document.getElementById('subscriptionPaymentReference'),
            receipt: document.getElementById('subscriptionPaymentReceipt'),
            notes: document.getElementById('subscriptionPaymentNotes'),
            confirmBtn: document.getElementById('subscriptionPaymentConfirmBtn'),
            skipBtn: document.getElementById('subscriptionPaymentSkipBtn'),
        };
    }

    function readFields(modal) {
        return {
            payment_method: modal.method?.value || '',
            payment_reference: modal.reference?.value || '',
            receipt_no: modal.receipt?.value || '',
            payment_notes: modal.notes?.value || '',
        };
    }

    function writeFields(modal, data) {
        const d = data || {};
        if (modal.method) modal.method.value = d.payment_method || '';
        if (modal.reference) modal.reference.value = d.payment_reference || '';
        if (modal.receipt) modal.receipt.value = d.receipt_no || '';
        if (modal.notes) modal.notes.value = d.payment_notes || '';
    }

    function clearFields(modal) {
        writeFields(modal, {});
    }

    /**
     * @param {{ onConfirm: function(Object): void, initial?: Object }} options
     */
    function open(options) {
        const modal = getModal();
        if (!modal) {
            options.onConfirm({});
            return;
        }

        clearFields(modal);
        writeFields(modal, options.initial || {});

        let confirmed = false;

        function onConfirmClick() {
            confirmed = true;
            const data = readFields(modal);
            modal.instance.hide();
            options.onConfirm(data);
        }

        function onHidden() {
            modal.el.removeEventListener('hidden.bs.modal', onHidden);
            modal.confirmBtn?.removeEventListener('click', onConfirmClick);
            if (!confirmed) {
                options.onConfirm(readFields(modal));
            }
        }

        modal.confirmBtn?.addEventListener('click', onConfirmClick, { once: true });
        modal.el.addEventListener('hidden.bs.modal', onHidden);
        modal.instance.show();
    }

    function initForm() {
        const statusEl = document.getElementById('subscription-client-invoice-status');
        const methodHidden = document.getElementById('subscription-client-invoice-payment-method');
        const referenceHidden = document.getElementById('subscription-client-invoice-payment-reference');
        const receiptHidden = document.getElementById('subscription-client-invoice-receipt-no');
        const notesHidden = document.getElementById('subscription-client-invoice-payment-notes');

        if (!statusEl) {
            return;
        }

        function syncToHidden(data) {
            if (methodHidden) methodHidden.value = data.payment_method || '';
            if (referenceHidden) referenceHidden.value = data.payment_reference || '';
            if (receiptHidden) receiptHidden.value = data.receipt_no || '';
            if (notesHidden) notesHidden.value = data.payment_notes || '';
        }

        function clearHidden() {
            syncToHidden({});
        }

        statusEl.addEventListener('change', function () {
            const v = statusEl.value || 'unpaid';
            if (v !== 'paid') {
                clearHidden();
                return;
            }
            open({
                initial: {
                    payment_method: methodHidden?.value || '',
                    payment_reference: referenceHidden?.value || '',
                    receipt_no: receiptHidden?.value || '',
                    payment_notes: notesHidden?.value || '',
                },
                onConfirm: syncToHidden,
            });
        });
    }

    function initListing(csrf) {
        document.querySelectorAll('.btn-client-invoice-paid').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const url = btn.dataset.payUrl;
                if (!url) {
                    return;
                }

                open({
                    onConfirm: function (payment) {
                        fetch(url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(payment),
                        })
                            .then(function (res) {
                                return res.json().then(function (data) {
                                    return { res, data };
                                });
                            })
                            .then(function ({ res, data }) {
                                if (!res.ok) {
                                    throw new Error(data.message || 'Request failed.');
                                }
                                if (typeof window.adminFlashToast === 'function') {
                                    window.adminFlashToast('success', 'Marked as paid.');
                                    setTimeout(function () { location.reload(); }, 400);
                                } else {
                                    location.reload();
                                }
                            })
                            .catch(function (err) {
                                if (window.Swal) {
                                    Swal.fire({ icon: 'error', title: 'Error', text: err.message });
                                }
                            });
                    },
                });
            });
        });
    }

    window.SubscriptionPaymentModal = {
        open,
        initForm,
        initListing,
    };
})();
