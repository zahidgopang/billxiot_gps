@extends('admin.layouts.app')

@section('title', __('app.admin.company_map_settings_title') . ' - ' . __('app.brand'))
@section('page-title', __('app.admin.company_map_settings_title'))

@section('content')
<div class="container-fluid py-3">
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="mb-1">{{ __('app.admin.company_map_settings_title') }}</h5>
            <p class="small text-muted mb-3">{{ __('app.admin.company_map_settings_hint') }}</p>

            <form id="companyMapSettingsForm" class="row g-3">
                @php $card = $companyMapCard ?? []; @endphp
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr class="small text-muted">
                                    <th>{{ __('app.tracking.company_map_label_company_name') }}</th>
                                    <th style="width:40%"></th>
                                    <th class="text-end">{{ __('app.tracking.company_map_label_company_name_ar') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach([
                                    ['key' => 'company_name', 'en' => 'company_map_label_company_name', 'ar' => 'company_map_label_company_name_ar'],
                                    ['key' => 'company_number', 'en' => 'company_map_label_company_number', 'ar' => 'company_map_label_company_number_ar'],
                                    ['key' => 'operation_card', 'en' => 'company_map_label_operation_card', 'ar' => 'company_map_label_operation_card_ar'],
                                    ['key' => 'support', 'en' => 'company_map_label_support', 'ar' => 'company_map_label_support_ar'],
                                ] as $row)
                                    <tr>
                                        <td class="small fw-semibold">{{ __('app.tracking.'.$row['en']) }}</td>
                                        <td>
                                            <input type="text" name="{{ $row['key'] }}" id="company_{{ $row['key'] }}" class="form-control form-control-sm"
                                                   value="{{ old($row['key'], $card[$row['key']] ?? '') }}">
                                        </td>
                                        <td class="small text-end" dir="rtl">{{ __('app.tracking.'.$row['ar']) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td class="small fw-semibold">{{ __('app.tracking.company_map_label_vehicle_name', ['type' => __('app.forms.vehicle_type_car')]) }}</td>
                                    <td colspan="2" class="small text-muted">{{ __('app.tracking.company_map_vehicle_hint') }}</td>
                                </tr>
                                <tr>
                                    <td class="small fw-semibold">{{ __('app.tracking.company_map_label_vehicle_plate', ['type' => __('app.forms.vehicle_type_car')]) }}</td>
                                    <td colspan="2" class="small text-muted">{{ __('app.tracking.company_map_vehicle_hint') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="show_on_map" value="1" id="company_show_on_map"
                               @checked(old('show_on_map', $card['show_on_map'] ?? false))>
                        <label class="form-check-label" for="company_show_on_map">{{ __('app.tracking.company_map_show_on_map') }}</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-save me-1"></i>{{ __('app.common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('companyMapSettingsForm');
    const updateUrl = @json($updateUrl);
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function notify(icon, title) {
        if (window.Swal) {
            Swal.fire({ icon, title, timer: icon === 'success' ? 1600 : undefined, showConfirmButton: icon !== 'success' });
        } else {
            alert(title);
        }
    }

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        btn?.setAttribute('disabled', 'disabled');
        try {
            const res = await fetch(updateUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: JSON.stringify({
                    company_name: String(form.elements.company_name?.value || '').trim(),
                    company_number: String(form.elements.company_number?.value || '').trim(),
                    operation_card: String(form.elements.operation_card?.value || '').trim(),
                    support: String(form.elements.support?.value || '').trim(),
                    show_on_map: form.elements.show_on_map?.checked === true,
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                notify('error', data.message || @json(__('app.tracking.settings_save_failed')));
                return;
            }
            notify('success', data.message || @json(__('app.tracking.settings_saved')));
        } catch (err) {
            notify('error', @json(__('app.tracking.settings_save_failed')));
        } finally {
            btn?.removeAttribute('disabled');
        }
    });
})();
</script>
@endpush
