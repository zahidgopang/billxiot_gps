<div class="ud-fleet-status mb-4">
    <div class="ud-fleet-status__row">
        <span class="ud-fleet-status__label">{{ __('app.user.dashboard.fleet_status') }}</span>
        <span class="ud-fleet-status__chip ud-fleet-status__chip--active">
            <i class="fas fa-check-circle"></i>{{ $activeDevices ?? 0 }} {{ __('app.user.dashboard.active_short') }}
        </span>
        <span class="ud-fleet-status__chip ud-fleet-status__chip--online">
            <i class="fas fa-wifi"></i>{{ $onlineNow ?? 0 }} {{ __('app.user.dashboard.online_short') }}
        </span>
        <span class="ud-fleet-status__chip ud-fleet-status__chip--offline">
            <i class="fas fa-plug"></i>{{ $offlineNow ?? 0 }} {{ __('app.user.dashboard.offline_short') }}
        </span>
    </div>
</div>
