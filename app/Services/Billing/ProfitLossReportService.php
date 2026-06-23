<?php

namespace App\Services\Billing;

use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingInvoiceType;
use App\Models\BillingInvoice;
use App\Models\DeviceInventorySummary;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Authorization\RbacService;
use App\Services\Authorization\TenantScopeService;
use Illuminate\Database\Eloquent\Builder;

class ProfitLossReportService
{
    public function __construct(
        private RbacService $rbac,
        private TenantScopeService $tenantScope,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forActor(User $actor): array
    {
        $clientIds = $this->scopedClientIds($actor);
        $subscriptionQuery = $this->scopeSubscriptions(Subscription::query(), $actor, $clientIds);
        $platformInvoiceQuery = $this->scopeInvoices(
            BillingInvoice::query()->where('invoice_type', BillingInvoiceType::Platform->value),
            $actor,
            $clientIds
        );
        $clientInvoiceQuery = $this->scopeInvoices(
            BillingInvoice::query()->where('invoice_type', BillingInvoiceType::Client->value),
            $actor,
            $clientIds
        );

        $subs = (clone $subscriptionQuery)->get();

        $subscriptionProfit = $subs->sum(fn (Subscription $s) => $s->subscriptionProfit());
        $deviceProfit = $subs->sum(fn (Subscription $s) => $s->deviceProfit());
        $companyRevenue = (clone $platformInvoiceQuery)
            ->where('status', '!=', BillingInvoiceStatus::Cancelled->value)
            ->sum('total');
        $clientRevenue = (clone $clientInvoiceQuery)
            ->where('status', '!=', BillingInvoiceStatus::Cancelled->value)
            ->sum('total');

        $platformPending = (clone $platformInvoiceQuery)
            ->whereIn('status', [
                BillingInvoiceStatus::Unpaid->value,
                BillingInvoiceStatus::Partial->value,
                BillingInvoiceStatus::Overdue->value,
                BillingInvoiceStatus::Due->value,
            ])
            ->sum('balance_due');

        $clientPending = (clone $clientInvoiceQuery)
            ->whereIn('status', [
                BillingInvoiceStatus::Due->value,
                BillingInvoiceStatus::Partial->value,
                BillingInvoiceStatus::Unpaid->value,
            ])
            ->sum('balance_due');

        $stockValue = $this->stockValueForActor($actor, $clientIds);

        return [
            'scope' => $this->scopeLabel($actor),
            'subscription_profit' => round($subscriptionProfit, 2),
            'device_profit' => round($deviceProfit, 2),
            'total_profit' => round($subscriptionProfit + $deviceProfit, 2),
            'platform_revenue' => round((float) $companyRevenue, 2),
            'client_revenue' => round((float) $clientRevenue, 2),
            'total_revenue' => round((float) $companyRevenue + (float) $clientRevenue, 2),
            'platform_pending_dues' => round((float) $platformPending, 2),
            'client_pending_dues' => round((float) $clientPending, 2),
            'total_pending_dues' => round((float) $platformPending + (float) $clientPending, 2),
            'paid_invoices' => (clone $platformInvoiceQuery)->where('status', BillingInvoiceStatus::Paid->value)->count()
                + (clone $clientInvoiceQuery)->where('status', BillingInvoiceStatus::Paid->value)->count(),
            'unpaid_invoices' => (clone $platformInvoiceQuery)->whereIn('status', [
                BillingInvoiceStatus::Unpaid->value,
                BillingInvoiceStatus::Overdue->value,
            ])->count()
                + (clone $clientInvoiceQuery)->where('status', BillingInvoiceStatus::Due->value)->count(),
            'partial_invoices' => (clone $platformInvoiceQuery)->where('status', BillingInvoiceStatus::Partial->value)->count()
                + (clone $clientInvoiceQuery)->where('status', BillingInvoiceStatus::Partial->value)->count(),
            'cancelled_invoices' => (clone $platformInvoiceQuery)->where('status', BillingInvoiceStatus::Cancelled->value)->count()
                + (clone $clientInvoiceQuery)->where('status', BillingInvoiceStatus::Cancelled->value)->count(),
            'active_subscriptions' => (clone $subscriptionQuery)->where('status', 'active')->count(),
            'cancelled_subscriptions' => (clone $subscriptionQuery)->where('status', 'cancelled')->count(),
            'expired_subscriptions' => (clone $subscriptionQuery)->where('status', 'expired')->count(),
            'stock_available_units' => $stockValue['available_units'],
            'stock_value_estimate' => $stockValue['value_estimate'],
        ];
    }

    /**
     * @return list<int>|null
     */
    private function scopedClientIds(User $actor): ?array
    {
        if ($this->rbac->isSuperAdmin($actor)) {
            return null;
        }

        if ($this->rbac->isVendorAdmin($actor)) {
            return $this->tenantScope->visibleClientIds($actor);
        }

        if ($this->rbac->roleOf($actor) === \App\Enums\AppRole::Client) {
            return [$this->tenantScope->ensureClientForManager($actor)];
        }

        return [];
    }

    /**
     * @param  list<int>|null  $clientIds
     */
    private function scopeSubscriptions(Builder $query, User $actor, ?array $clientIds): Builder
    {
        if ($clientIds === null) {
            return $query;
        }

        if ($clientIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('client_id', $clientIds);
    }

    /**
     * @param  list<int>|null  $clientIds
     */
    private function scopeInvoices(Builder $query, User $actor, ?array $clientIds): Builder
    {
        if ($clientIds === null) {
            return $query;
        }

        if ($clientIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn('client_id', $clientIds);
    }

    /**
     * @param  list<int>|null  $clientIds
     * @return array{available_units:int,value_estimate:float}
     */
    private function stockValueForActor(User $actor, ?array $clientIds): array
    {
        $q = DeviceInventorySummary::query();

        if ($this->rbac->isSuperAdmin($actor)) {
            $q->where('scope', 'warehouse');
        } elseif ($clientIds !== null && $clientIds !== []) {
            $q->where('scope', 'client')->whereIn('scope_id', $clientIds);
        } else {
            return ['available_units' => 0, 'value_estimate' => 0.0];
        }

        $available = (int) $q->sum('available_qty');

        return [
            'available_units' => $available,
            'value_estimate' => 0.0, // FIFO valuation can be added in a later pass
        ];
    }

    private function scopeLabel(User $actor): string
    {
        if ($this->rbac->isSuperAdmin($actor)) {
            return 'super_admin';
        }
        if ($this->rbac->isVendorAdmin($actor)) {
            return 'admin';
        }

        return 'client';
    }
}
