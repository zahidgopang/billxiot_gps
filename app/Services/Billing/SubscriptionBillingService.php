<?php

namespace App\Services\Billing;

use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingInvoiceType;
use App\Enums\BillingLineType;
use App\Enums\SubscriptionType;
use App\Models\Device;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionBillingService
{
    public function __construct(
        private BillingInvoiceService $invoices,
        private DeviceCostResolver $deviceCosts,
        private InventoryService $inventory,
        private \App\Support\Billing\SubscriptionNotificationConfig $notificationConfig,
    ) {}

    /**
     * @param  array{subscription_plan_id:int,selling_price:float,device_selling_price?:float,client_id:int,subscription_type:string}  $billing
     */
    public function provisionForSubscription(Subscription $subscription, array $billing, ?User $actor = null): Subscription
    {
        return $this->provisionConsolidatedBatch(collect([$subscription]), $billing, $actor)->first();
    }

    /**
     * @param  Collection<int, Subscription>  $subscriptions
     * @param  array{subscription_plan_id:int,selling_price:float,device_selling_price?:float,client_id:int,subscription_type:string}  $billing
     * @return Collection<int, Subscription>
     */
    public function provisionConsolidatedBatch(Collection $subscriptions, array $billing, ?User $actor = null): Collection
    {
        if ($subscriptions->isEmpty()) {
            return $subscriptions;
        }

        return DB::transaction(function () use ($subscriptions, $billing, $actor) {
            $plan = SubscriptionPlan::query()->findOrFail($billing['subscription_plan_id']);
            $clientId = (int) $billing['client_id'];
            $subscriptionType = SubscriptionType::from($billing['subscription_type']);
            $companyPrice = (float) $plan->company_price;
            $sellingPrice = (float) $billing['selling_price'];
            $deviceSelling = $subscriptionType === SubscriptionType::New
                ? (float) ($billing['device_selling_price'] ?? 0)
                : 0.0;

            foreach ($subscriptions as $subscription) {
                $device = $subscription->device ?? Device::query()->find($subscription->device_id);
                $deviceCost = $device
                    ? $this->deviceCosts->resolveForClientDevice($device, $clientId)
                    : 0.0;

                $subscription->update([
                    'subscription_plan_id' => $plan->id,
                    'plan' => $plan->name,
                    'client_id' => $clientId,
                    'subscription_type' => $subscriptionType->value,
                    'company_price' => $companyPrice,
                    'selling_price' => $sellingPrice,
                    'device_unit_cost' => $deviceCost,
                    'device_selling_price' => $deviceSelling,
                ]);
            }

            $subscriptions->each(fn (Subscription $subscription) => $subscription->load('device'));
            $first = $subscriptions->first();
            $consolidated = $subscriptions->count() > 1;
            $batchMeta = [
                'consolidated' => $consolidated,
                'subscription_ids' => $subscriptions->pluck('id')->all(),
                'device_ids' => $subscriptions->pluck('device_id')->all(),
                'device_count' => $subscriptions->count(),
            ];

            $platformInvoice = $this->invoices->createInvoice(
                BillingInvoiceType::Platform,
                $this->buildConsolidatedPlatformLines($subscriptions, $plan, $companyPrice),
                clientId: $clientId,
                subscriptionId: $first->id,
                actor: $actor,
            );

            $clientInvoice = $this->invoices->createInvoice(
                BillingInvoiceType::Client,
                $this->buildConsolidatedClientLines($subscriptions),
                clientId: $clientId,
                userId: $first->user_id,
                subscriptionId: $first->id,
                actor: $actor,
            );

            $platformInvoice->update([
                'meta' => array_merge($platformInvoice->meta ?? [], $batchMeta, [
                    'paired_invoice_id' => $clientInvoice->id,
                    'paired_invoice_no' => $clientInvoice->invoice_no,
                ]),
            ]);
            $clientInvoice->update([
                'meta' => array_merge($clientInvoice->meta ?? [], $batchMeta, [
                    'paired_invoice_id' => $platformInvoice->id,
                    'paired_invoice_no' => $platformInvoice->invoice_no,
                ]),
            ]);

            foreach ($subscriptions as $subscription) {
                $subscription->update([
                    'platform_invoice_id' => $platformInvoice->id,
                    'client_invoice_id' => $clientInvoice->id,
                ]);

                if ($subscription->status === 'active' && $subscription->device) {
                    $this->ensureDeviceStockCommitted($subscription, $subscription->device, $clientId, $actor?->id);
                }
            }

            return $subscriptions->map(
                fn (Subscription $subscription) => $subscription->fresh(['platformInvoice', 'clientInvoice', 'subscriptionPlan'])
            );
        });
    }

    public function syncClientInvoice(Subscription $subscription): void
    {
        $subscription->loadMissing(['clientInvoice', 'device', 'subscriptionPlan']);
        $invoice = $subscription->clientInvoice;

        if (! $invoice || $invoice->isCancelled()) {
            return;
        }

        if ($invoice->status === BillingInvoiceStatus::Paid->value) {
            return;
        }

        if ((float) $invoice->amount_paid > 0) {
            return;
        }

        $this->invoices->replaceLines($invoice, $this->buildClientInvoiceLines($subscription));
    }

    /**
     * @return list<array{line_type:string,description:string,quantity?:int,unit_cost:float,unit_price:float,reference_type?:string,reference_id?:int}>
     */
    private function buildClientInvoiceLines(Subscription $subscription): array
    {
        return $this->buildConsolidatedClientLines(collect([$subscription]));
    }

    /**
     * @param  Collection<int, Subscription>  $subscriptions
     * @return list<array{line_type:string,description:string,quantity?:int,unit_cost:float,unit_price:float,reference_type?:string,reference_id?:int}>
     */
    private function buildConsolidatedClientLines(Collection $subscriptions): array
    {
        $lines = [];
        $first = $subscriptions->first();
        $multiDevice = $subscriptions->count() > 1;

        foreach ($subscriptions as $subscription) {
            $subscription->loadMissing(['device', 'subscriptionPlan']);
            $planName = $subscription->subscriptionPlan?->name ?? $subscription->plan ?? 'Subscription';
            $description = $multiDevice
                ? "Subscription: {$planName} — {$this->deviceLabel($subscription)}"
                : "Subscription: {$planName}";

            $lines[] = [
                'line_type' => BillingLineType::Subscription->value,
                'description' => $description,
                'unit_cost' => (float) $subscription->company_price,
                'unit_price' => (float) $subscription->selling_price,
                'reference_type' => Subscription::class,
                'reference_id' => $subscription->id,
            ];

            if ($subscription->isNewSubscriptionType()) {
                $lines[] = [
                    'line_type' => BillingLineType::Device->value,
                    'description' => "Device: {$this->deviceLabel($subscription)}",
                    'unit_cost' => (float) $subscription->device_unit_cost,
                    'unit_price' => (float) $subscription->device_selling_price,
                    'reference_type' => Device::class,
                    'reference_id' => $subscription->device_id,
                ];
            }
        }

        if ($first && $first->notification_email_enabled) {
            foreach ($this->notificationConfig->invoiceLines($first, 'email') as $line) {
                $lines[] = $line;
            }
        }

        if ($first && $first->notification_whatsapp_enabled) {
            foreach ($this->notificationConfig->invoiceLines($first, 'whatsapp') as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param  Collection<int, Subscription>  $subscriptions
     * @return list<array{line_type:string,description:string,quantity?:int,unit_cost:float,unit_price:float,reference_type?:string,reference_id?:int}>
     */
    private function buildConsolidatedPlatformLines(Collection $subscriptions, SubscriptionPlan $plan, float $companyPrice): array
    {
        $lines = [];

        foreach ($subscriptions as $subscription) {
            $lines[] = [
                'line_type' => BillingLineType::Subscription->value,
                'description' => "Plan: {$plan->name} — {$this->deviceLabel($subscription)}",
                'unit_cost' => $companyPrice,
                'unit_price' => $companyPrice,
                'reference_type' => Subscription::class,
                'reference_id' => $subscription->id,
            ];
        }

        return $lines;
    }

    private function deviceLabel(Subscription $subscription): string
    {
        $device = $subscription->device;

        return $device
            ? ($device->name ?: $device->imei)
            : "Device #{$subscription->device_id}";
    }

    public function handleStatusChange(Subscription $subscription, string $previousStatus, ?User $actor = null): void
    {
        if ($subscription->status === 'cancelled' && $previousStatus !== 'cancelled') {
            $this->cancelBilling($subscription, $actor);
            $this->returnDeviceStock($subscription, $actor?->id);
        }
    }

    public function cancelBilling(Subscription $subscription, ?User $actor = null): void
    {
        if ($subscription->platformInvoice) {
            $this->invoices->cancelInvoice($subscription->platformInvoice, $actor);
        }
        if ($subscription->clientInvoice) {
            $this->invoices->cancelInvoice($subscription->clientInvoice, $actor);
        }
    }

    private function ensureDeviceStockCommitted(Subscription $subscription, Device $device, int $clientId, ?int $actorId): void
    {
        $deviceType = Device::canonicalDeviceType($device->device_type ?? $device->category ?? '') ?? 'personal';

        try {
            $this->inventory->consumeForInstall($clientId, $deviceType, (int) $device->id, $actorId);
        } catch (ValidationException) {
            // Already consumed at install time.
        }
    }

    private function returnDeviceStock(Subscription $subscription, ?int $actorId): void
    {
        $device = $subscription->device;
        if (! $device || ! $subscription->client_id) {
            return;
        }

        $deviceType = Device::canonicalDeviceType($device->device_type ?? $device->category ?? '') ?? 'personal';
        $this->inventory->returnFromSubscriptionCancel(
            (int) $subscription->client_id,
            $deviceType,
            (int) $device->id,
            $actorId
        );
    }
}
