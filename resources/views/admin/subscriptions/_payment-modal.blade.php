<div class="modal fade" id="subscriptionPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-money-check-alt me-2"></i>{{ __('app.billing.payment_details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.common.cancel') }}"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">{{ __('app.billing.payment_details_hint') }}</p>

                <div class="mb-2">
                    <label class="form-label small" for="subscriptionPaymentMethod">{{ __('app.billing.paid_by') }}</label>
                    <select id="subscriptionPaymentMethod" class="form-select form-select-sm">
                        <option value="">—</option>
                        <option value="Cash">{{ __('app.billing.payment_cash') }}</option>
                        <option value="Bank">{{ __('app.billing.payment_bank') }}</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small" for="subscriptionPaymentReference">{{ __('app.billing.payment_reference') }}</label>
                    <input type="text" id="subscriptionPaymentReference" class="form-control form-control-sm"
                           placeholder="{{ __('app.billing.payment_reference_placeholder') }}">
                </div>
                <div class="mb-2">
                    <label class="form-label small" for="subscriptionPaymentReceipt">{{ __('app.billing.receipt_no') }}</label>
                    <input type="text" id="subscriptionPaymentReceipt" class="form-control form-control-sm"
                           placeholder="{{ __('app.billing.receipt_no_placeholder') }}">
                </div>
                <div class="mb-0">
                    <label class="form-label small" for="subscriptionPaymentNotes">{{ __('app.billing.payment_notes') }}</label>
                    <input type="text" id="subscriptionPaymentNotes" class="form-control form-control-sm"
                           placeholder="{{ __('app.billing.payment_notes_placeholder') }}">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" id="subscriptionPaymentSkipBtn">{{ __('app.billing.payment_skip') }}</button>
                <button type="button" class="btn btn-primary btn-sm" id="subscriptionPaymentConfirmBtn">{{ __('app.common.save') }}</button>
            </div>
        </div>
    </div>
</div>
