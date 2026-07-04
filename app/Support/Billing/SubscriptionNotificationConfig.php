<?php

namespace App\Support\Billing;

use App\Models\RoutePlan;
use App\Models\Subscription;
use Illuminate\Http\Request;

/**
 * Per-subscription notification item selection and pricing (email / WhatsApp channels).
 *
 * Config shape:
 * [
 *   'types' => ['panic' => ['price' => 5.0], ...],
 *   'routes' => ['12' => ['price' => 10.0], ...],
 * ]
 */
class SubscriptionNotificationConfig
{
    public function __construct(
        private SubscriptionPlanNotificationCatalog $catalog,
    ) {}

    /**
     * @return array{types: array<string, array{price: float|null}>, routes: array<string, array{price: float|null}>}
     */
    public function emptyConfig(): array
    {
        return ['types' => [], 'routes' => []];
    }

    /**
     * @return array{types: array<string, array{price: float|null}>, routes: array<string, array{price: float|null}>}
     */
    public function normalize(?array $config): array
    {
        if (! is_array($config) || $config === []) {
            return $this->emptyConfig();
        }

        $types = [];
        foreach ($config['types'] ?? [] as $key => $item) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            $types[$key] = ['price' => $this->parsePrice(is_array($item) ? ($item['price'] ?? null) : null)];
        }

        $routes = [];
        foreach ($config['routes'] ?? [] as $key => $item) {
            $routeId = (int) $key;
            if ($routeId <= 0) {
                continue;
            }
            $routes[(string) $routeId] = ['price' => $this->parsePrice(is_array($item) ? ($item['price'] ?? null) : null)];
        }

        return ['types' => $types, 'routes' => $routes];
    }

    /**
     * @return array{types: array<string, array{price: float|null}>, routes: array<string, array{price: float|null}>}|null
     */
    public function parseChannelRequest(Request $request, string $channel, bool $enabled): ?array
    {
        if (! $enabled) {
            return null;
        }

        $prefix = $channel === 'email' ? 'notification_email' : 'notification_whatsapp';
        $raw = $request->input($prefix, []);
        if (! is_array($raw)) {
            return $this->emptyConfig();
        }

        $allowedTypes = array_flip($this->catalog->standardTypeKeys());
        $validRouteIds = RoutePlan::query()
            ->where('status', RoutePlan::STATUS_ACTIVE)
            ->pluck('id')
            ->map(fn ($id) => (string) (int) $id)
            ->flip()
            ->all();

        $types = [];
        foreach ($raw['types'] ?? [] as $typeKey => $row) {
            if (! is_string($typeKey) || ! isset($allowedTypes[$typeKey])) {
                continue;
            }
            if (! is_array($row) || empty($row['enabled'])) {
                continue;
            }
            $types[$typeKey] = ['price' => $this->parsePrice($row['price'] ?? null)];
        }

        $routes = [];
        foreach ($raw['routes'] ?? [] as $routeKey => $row) {
            $routeId = (string) (int) $routeKey;
            if (! isset($validRouteIds[$routeId])) {
                continue;
            }
            if (! is_array($row) || empty($row['enabled'])) {
                continue;
            }
            $routes[$routeId] = ['price' => $this->parsePrice($row['price'] ?? null)];
        }

        return ['types' => $types, 'routes' => $routes];
    }

    public function usesLegacyEntitlements(?array $config): bool
    {
        return $config === null;
    }

    public function typeEnabled(?array $config, string $eventType): bool
    {
        if ($this->usesLegacyEntitlements($config)) {
            return true;
        }

        $config = $this->normalize($config);
        $key = $this->catalog->canonicalKey($eventType);

        return isset($config['types'][$key]);
    }

    public function routeEnabled(?array $config, ?int $routeId): bool
    {
        if ($this->usesLegacyEntitlements($config)) {
            return true;
        }

        if ($routeId === null || $routeId <= 0) {
            return false;
        }

        $config = $this->normalize($config);

        return isset($config['routes'][(string) $routeId]);
    }

    /**
     * @return list<array{key: string, label: string, enabled: bool, price: string|float|null, is_route: bool}>
     */
    public function formRows(?array $config, string $channelPrefix, bool $prefillAllWhenEmpty = false): array
    {
        $config = $this->normalize($config);
        $oldPrefix = old($channelPrefix, []);
        $rows = [];

        foreach ($this->catalog->standardTypeOptions() as $option) {
            $key = $option['key'];
            $oldRow = is_array($oldPrefix['types'][$key] ?? null) ? $oldPrefix['types'][$key] : null;
            $stored = $config['types'][$key] ?? null;

            $enabled = $oldRow !== null
                ? ! empty($oldRow['enabled'])
                : ($stored !== null || $prefillAllWhenEmpty);

            $price = $oldRow !== null
                ? ($oldRow['price'] ?? '')
                : ($stored['price'] ?? '');

            $rows[] = [
                'key' => $key,
                'label' => $option['label'],
                'enabled' => $enabled,
                'price' => $price,
                'is_route' => false,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{key: string, label: string, enabled: bool, price: string|float|null, is_route: bool}>
     */
    public function formRouteRows(?array $config, string $channelPrefix, bool $prefillAllWhenEmpty = false): array
    {
        $config = $this->normalize($config);
        $oldPrefix = old($channelPrefix, []);
        $rows = [];

        $routes = RoutePlan::query()
            ->where('status', RoutePlan::STATUS_ACTIVE)
            ->orderBy('start_city')
            ->orderBy('destination_city')
            ->get();

        foreach ($routes as $route) {
            $key = (string) $route->id;
            $oldRow = is_array($oldPrefix['routes'][$key] ?? null) ? $oldPrefix['routes'][$key] : null;
            $stored = $config['routes'][$key] ?? null;

            $enabled = $oldRow !== null
                ? ! empty($oldRow['enabled'])
                : ($stored !== null || $prefillAllWhenEmpty);

            $price = $oldRow !== null
                ? ($oldRow['price'] ?? '')
                : ($stored['price'] ?? '');

            $rows[] = [
                'key' => $key,
                'label' => $route->routeLabel(),
                'enabled' => $enabled,
                'price' => $price,
                'is_route' => true,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{line_type: string, description: string, unit_cost: float, unit_price: float, reference_type: string, reference_id: int}>
     */
    public function invoiceLines(Subscription $subscription, string $channel): array
    {
        $config = $channel === 'email'
            ? $subscription->notification_email_config
            : $subscription->notification_whatsapp_config;

        if ($this->usesLegacyEntitlements($config)) {
            return $this->legacyInvoiceLine($subscription, $channel);
        }

        $config = $this->normalize($config);
        $lineType = $channel === 'email'
            ? \App\Enums\BillingLineType::NotificationEmail->value
            : \App\Enums\BillingLineType::NotificationWhatsApp->value;
        $channelLabel = $channel === 'email' ? 'Email' : 'WhatsApp';
        $lines = [];

        foreach ($config['types'] as $typeKey => $item) {
            $price = (float) ($item['price'] ?? 0);
            if ($price <= 0) {
                continue;
            }
            $lines[] = [
                'line_type' => $lineType,
                'description' => "{$channelLabel}: ".$this->catalog->labelForKey($typeKey),
                'unit_cost' => 0,
                'unit_price' => $price,
                'reference_type' => Subscription::class,
                'reference_id' => $subscription->id,
            ];
        }

        foreach ($config['routes'] as $routeId => $item) {
            $price = (float) ($item['price'] ?? 0);
            if ($price <= 0) {
                continue;
            }
            $route = RoutePlan::query()->find((int) $routeId);
            $label = $route?->routeLabel() ?? "Route #{$routeId}";
            $lines[] = [
                'line_type' => $lineType,
                'description' => "{$channelLabel} route: {$label}",
                'unit_cost' => 0,
                'unit_price' => $price,
                'reference_type' => Subscription::class,
                'reference_id' => $subscription->id,
            ];
        }

        return $lines;
    }

    public function channelAddonTotal(Subscription $subscription, string $channel): float
    {
        $config = $channel === 'email'
            ? $subscription->notification_email_config
            : $subscription->notification_whatsapp_config;

        if ($this->usesLegacyEntitlements($config)) {
            if ($channel === 'email') {
                return (float) ($subscription->notification_email_price ?? 0);
            }

            return (float) ($subscription->notification_whatsapp_price ?? 0);
        }

        return $this->sumConfigPrices($config);
    }

    public function sumConfigPrices(?array $config): float
    {
        if ($config === null) {
            return 0.0;
        }

        $total = 0.0;
        $config = $this->normalize($config);

        foreach ($config['types'] as $item) {
            $total += (float) ($item['price'] ?? 0);
        }
        foreach ($config['routes'] as $item) {
            $total += (float) ($item['price'] ?? 0);
        }

        return $total;
    }

    /**
     * @return list<array{line_type: string, description: string, unit_cost: float, unit_price: float, reference_type: string, reference_id: int}>
     */
    private function legacyInvoiceLine(Subscription $subscription, string $channel): array
    {
        $price = $channel === 'email'
            ? (float) ($subscription->notification_email_price ?? 0)
            : (float) ($subscription->notification_whatsapp_price ?? 0);

        if ($price <= 0) {
            return [];
        }

        return [[
            'line_type' => $channel === 'email'
                ? \App\Enums\BillingLineType::NotificationEmail->value
                : \App\Enums\BillingLineType::NotificationWhatsApp->value,
            'description' => $channel === 'email'
                ? 'Email notifications add-on'
                : 'WhatsApp notifications add-on',
            'unit_cost' => 0,
            'unit_price' => $price,
            'reference_type' => Subscription::class,
            'reference_id' => $subscription->id,
        ]];
    }

    private function parsePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $price = (float) $value;

        return $price >= 0 ? $price : null;
    }
}
