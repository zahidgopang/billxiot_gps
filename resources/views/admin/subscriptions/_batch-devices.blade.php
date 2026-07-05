@php
    $subscriptions = $batch->subscriptions ?? collect();
    $compact = $compact ?? false;
@endphp

@if($subscriptions->isNotEmpty())
    <div class="{{ $compact ? 'mb-0' : 'card mb-3' }}">
        @unless($compact)
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <span>
                    <strong>{{ __('app.admin.subscriptions.attached_vehicles') }}</strong>
                    <span class="badge bg-secondary ms-1">{{ $batch->device_count ?? $subscriptions->count() }}</span>
                </span>
            </div>
        @endunless
        <div class="{{ $compact ? '' : 'card-body p-0' }}">
            <div class="table-responsive">
                <table class="table table-sm mb-0 {{ $compact ? 'table-borderless' : '' }}">
                    @unless($compact)
                        <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>{{ __('app.forms.device') }}</th>
                            <th>{{ __('app.common.status') }}</th>
                        </tr>
                        </thead>
                    @endunless
                    <tbody>
                    @foreach($subscriptions as $index => $sub)
                        <tr>
                            @unless($compact)
                                <td>{{ $index + 1 }}</td>
                            @endunless
                            <td>
                                <strong>{{ $sub->device?->name ?? '—' }}</strong>
                                @if($sub->device?->imei)
                                    <small class="d-block text-muted">{{ $sub->device->imei }}</small>
                                @endif
                            </td>
                            @unless($compact)
                                <td>
                                    @if($sub->status === 'active')
                                        <span class="badge bg-success">{{ __('app.common.active') }}</span>
                                    @elseif($sub->status === 'expired')
                                        <span class="badge bg-danger">{{ __('app.forms.expired') }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('app.forms.cancelled') }}</span>
                                    @endif
                                </td>
                            @endunless
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
