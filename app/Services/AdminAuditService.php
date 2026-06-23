<?php

namespace App\Services;

use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Client;
use App\Models\Device;
use App\Models\DeviceStockOrder;
use App\Models\DeviceStockSale;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Authorization\RbacService;
use App\Services\Authorization\TenantScopeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AdminAuditService
{
    public const LOG_NAME = 'admin';

    public const PROP_CLIENT_ID = 'client_id';

    public const PROP_CLIENT_NAME = 'client_name';

    public const PROP_CATEGORY = 'category';

    public const PROP_ACTOR_ROLE = 'actor_role';

    public const PROP_PANEL = 'panel';

    public function __construct(
        private TenantScopeService $tenantScope,
        private RbacService $rbac,
    ) {}

    public function log(
        string $action,
        string $description,
        ?Model $subject = null,
        array $properties = []
    ): void {
        $admin = Auth::user();

        $clientId = $this->resolveClientId($properties, $subject, $admin instanceof User ? $admin : null);
        $category = $this->resolveCategory($subject, $properties);

        $enriched = array_merge([
            'ip' => request()?->ip(),
            'url' => request()?->fullUrl(),
            'method' => request()?->method(),
            self::PROP_CATEGORY => $category,
            self::PROP_ACTOR_ROLE => $admin instanceof User ? $admin->role : null,
            self::PROP_PANEL => $this->resolvePanel($admin instanceof User ? $admin : null),
        ], $properties);

        if ($clientId !== null) {
            $enriched[self::PROP_CLIENT_ID] = $clientId;
            $enriched[self::PROP_CLIENT_NAME] = $enriched[self::PROP_CLIENT_NAME]
                ?? Client::query()->whereKey($clientId)->value('name');
        }

        $logger = activity(self::LOG_NAME)
            ->event($action)
            ->withProperties($enriched);

        if ($admin instanceof User) {
            $logger->causedBy($admin);
        }

        if ($subject) {
            $logger->performedOn($subject);
        }

        $logger->log($description);
    }

    public function logCreated(Model $subject, string $label, array $extra = []): void
    {
        $this->log('created', "Created {$label}", $subject, $extra);
    }

    public function logUpdated(Model $subject, string $label, array $extra = []): void
    {
        $this->log('updated', "Updated {$label}", $subject, $extra);
    }

    public function logDeleted(Model $subject, string $label, array $extra = []): void
    {
        $this->log('deleted', "Deleted {$label}", $subject, $extra);
    }

    private function resolvePanel(?User $actor): ?string
    {
        if (request()?->routeIs('client.*')) {
            return 'client';
        }

        if (request()?->routeIs('admin.*')) {
            return 'admin';
        }

        if ($actor) {
            return $this->rbac->roleOf($actor)->panel();
        }

        return null;
    }

    private function resolveCategory(?Model $subject, array $properties): string
    {
        if (! empty($properties[self::PROP_CATEGORY])) {
            return (string) $properties[self::PROP_CATEGORY];
        }

        if (! $subject) {
            return 'general';
        }

        return match ($subject::class) {
            User::class => 'user',
            Device::class => 'device',
            Subscription::class => 'subscription',
            SubscriptionPlan::class => 'subscription_plan',
            Client::class => 'client',
            DeviceStockOrder::class => 'stock',
            DeviceStockSale::class => 'stock',
            BillingInvoice::class => 'billing_invoice',
            BillingPayment::class => 'billing_payment',
            default => strtolower(class_basename($subject)),
        };
    }

    private function resolveClientId(array $properties, ?Model $subject, ?User $actor): ?int
    {
        if (! empty($properties[self::PROP_CLIENT_ID])) {
            return (int) $properties[self::PROP_CLIENT_ID];
        }

        if ($subject instanceof Client) {
            return (int) $subject->id;
        }

        if ($subject instanceof Device) {
            return $this->tenantScope->clientIdForDevice($subject);
        }

        if ($subject instanceof User) {
            return $this->tenantScope->primaryClientIdForUser($subject);
        }

        if ($subject instanceof Subscription) {
            $subject->loadMissing('device');

            if ($subject->device instanceof Device) {
                return $this->tenantScope->clientIdForDevice($subject->device);
            }
        }

        if ($actor !== null) {
            if ($this->rbac->isClientManager($actor)) {
                $ids = $this->tenantScope->clientIdsForUser($actor);

                return $ids[0] ?? null;
            }

            if (! $this->rbac->isSuperAdmin($actor)) {
                $visible = $this->tenantScope->visibleClientIds($actor);

                if (count($visible) === 1) {
                    return $visible[0];
                }
            }
        }

        $requestClientId = request()?->input('client_id');

        if ($requestClientId) {
            return (int) $requestClientId;
        }

        return null;
    }
}
