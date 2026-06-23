@extends('admin.layouts.app')
@section('title', __('app.billing.plans'))
@section('page-title', __('app.billing.plans'))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="GET" class="admin-filter-bar d-flex gap-2 align-items-end">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('app.common.search') }}…">
            <button type="submit" class="btn btn-sm btn-primary">{{ __('app.common.filter') }}</button>
        </form>
        @can('permission', 'billing.manage')
            <a href="{{ route('admin.subscription-plans.create') }}" class="btn btn-sm btn-primary">{{ __('app.billing.new_plan') }}</a>
        @endcan
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>{{ __('app.common.name') }}</th>
                <th>{{ __('app.billing.billing_cycle') }}</th>
                <th>{{ __('app.billing.company_price') }}</th>
                <th>{{ __('app.common.status') }}</th>
                <th>{{ __('app.billing.public') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse($plans as $plan)
                <tr>
                    <td>{{ $plan->name }}</td>
                    <td>{{ $plan->billingCycleLabel() }}</td>
                    <td>{{ $plan->formattedPrice() }}</td>
                    <td><span class="badge bg-{{ $plan->status === 'active' ? 'success' : 'secondary' }}">{{ $plan->status }}</span></td>
                    <td>{{ $plan->is_public ? 'Yes' : 'No' }}</td>
                    <td class="text-end text-nowrap">
                        @can('permission', 'billing.manage')
                            <a href="{{ route('admin.subscription-plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary">{{ __('app.common.edit') }}</a>
                            <form action="{{ route('admin.subscription-plans.destroy', $plan) }}" method="POST" class="d-inline delete-form">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-sm btn-danger btn-delete">{{ __('app.common.delete') }}</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">{{ __('app.billing.no_plans') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $plans->links() }}
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.btn-delete').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const form = this.closest('form');
                    Swal.fire({
                        title: @json(__('app.billing.delete_plan_confirm')),
                        text: @json(__('app.common.cannot_undo')),
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: @json(__('app.common.delete')),
                        cancelButtonText: @json(__('app.common.cancel')),
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });
        });
    </script>
@endpush
