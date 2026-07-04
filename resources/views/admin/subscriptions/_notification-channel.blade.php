@php
    $channelPrefix = $channelPrefix ?? 'notification_email';
    $typeRows = $typeRows ?? [];
    $routeRows = $routeRows ?? [];
    $enabled = (bool) ($enabled ?? false);
    $panelId = $channelPrefix === 'notification_email' ? 'email' : 'whatsapp';
@endphp

<div class="subscription-notif-channel" data-channel="{{ $panelId }}">
    <div class="form-check mb-2">
        <input type="checkbox"
               class="form-check-input subscription-notif-channel-toggle"
               name="{{ $channelPrefix === 'notification_email' ? 'notification_email_enabled' : 'notification_whatsapp_enabled' }}"
               id="subscription-notification-{{ $panelId }}-enabled"
               value="1"
               data-channel="{{ $panelId }}"
               @checked($enabled)>
        <label class="form-check-label fw-semibold" for="subscription-notification-{{ $panelId }}-enabled">
            @if($panelId === 'email')
                <i class="fas fa-envelope me-1 text-primary"></i>{{ __('app.billing.enable_email_notifications') }}
            @else
                <i class="fab fa-whatsapp me-1 text-success"></i>{{ __('app.billing.enable_whatsapp_notifications') }}
            @endif
        </label>
    </div>

    <div id="subscription-notification-{{ $panelId }}-items" @class(['subscription-notif-items', 'd-none' => ! $enabled])>
        <p class="admin-hint mb-2">{{ __('app.billing.subscription_notif_items_hint') }}</p>

        <div class="row g-2 mb-3">
            @foreach($typeRows as $row)
                <div class="col-md-6">
                    <div class="border rounded-3 p-2 h-100 subscription-notif-item">
                        <div class="form-check mb-2">
                            <input type="checkbox"
                                   class="form-check-input subscription-notif-type-check"
                                   data-channel="{{ $panelId }}"
                                   name="{{ $channelPrefix }}[types][{{ $row['key'] }}][enabled]"
                                   id="{{ $panelId }}-type-{{ $row['key'] }}"
                                   value="1"
                                   @checked($row['enabled'])>
                            <label class="form-check-label" for="{{ $panelId }}-type-{{ $row['key'] }}">
                                {{ $row['label'] }}
                            </label>
                        </div>
                        <input type="number"
                               step="0.01"
                               min="0"
                               class="form-control form-control-sm admin-ltr subscription-notif-price"
                               dir="ltr"
                               data-channel="{{ $panelId }}"
                               name="{{ $channelPrefix }}[types][{{ $row['key'] }}][price]"
                               value="{{ $row['price'] }}"
                               placeholder="{{ __('app.billing.notification_price_optional') }}"
                               @disabled(! $row['enabled'])>
                    </div>
                </div>
            @endforeach
        </div>

        @if(! empty($routeRows))
            <h6 class="fw-semibold small mb-2">
                <i class="fas fa-route me-1"></i>{{ __('app.billing.plan_route_notifications') }}
            </h6>
            <div class="row g-2">
                @foreach($routeRows as $row)
                    <div class="col-md-6">
                        <div class="border rounded-3 p-2 h-100 subscription-notif-item">
                            <div class="form-check mb-2">
                                <input type="checkbox"
                                       class="form-check-input subscription-notif-type-check"
                                       data-channel="{{ $panelId }}"
                                       name="{{ $channelPrefix }}[routes][{{ $row['key'] }}][enabled]"
                                       id="{{ $panelId }}-route-{{ $row['key'] }}"
                                       value="1"
                                       @checked($row['enabled'])>
                                <label class="form-check-label" for="{{ $panelId }}-route-{{ $row['key'] }}">
                                    {{ $row['label'] }}
                                </label>
                            </div>
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   class="form-control form-control-sm admin-ltr subscription-notif-price"
                                   dir="ltr"
                                   data-channel="{{ $panelId }}"
                                   name="{{ $channelPrefix }}[routes][{{ $row['key'] }}][price]"
                                   value="{{ $row['price'] }}"
                                   placeholder="{{ __('app.billing.notification_price_optional') }}"
                                   @disabled(! $row['enabled'])>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
