@php
    $panel = $panel ?? (request()->routeIs('client.*') ? 'client' : 'admin');
    $clientId = $selectedClient ?? null;
    if ($panel === 'client' && auth()->check()) {
        $clientId = app(\App\Services\Authorization\TenantScopeService::class)->ensureClientForManager(auth()->user());
    }
    if ($clientId === null && ! empty($subscription ?? null)) {
        $clientId = $subscription->client_id;
    }
@endphp
<script>
    window.subscriptionBillingRoutes = {
        devicePricing: @json(route($panel . '.subscriptions.device-pricing')),
        planPricingUrl: @json(route($panel . '.subscription-plans.pricing', ['subscriptionPlan' => '__PLAN__'])),
        clientId: @json($clientId),
    };
</script>
<script src="{{ protected_js('subscription-billing.js') }}"></script>
