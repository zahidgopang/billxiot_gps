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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionBillingService
{
    public function __construct(
        private BillingInvoiceService $invoices,
        private DeviceCostResolver $deviceCosts,
        private InventoryService $inventory,
    ) {}

    /**
     * @param  array{subscription_plan_id:int,selling_price:float,device_selling_price?:float,client_id:int,subscription_type:string}  $billing
     */
    public function provisionForSubscription(Subscription $subscription, array $billing, ?User $actor = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $billing, $actor) {
            $plan = SubscriptionPlan::query()->findOrFail($billing['subscription_plan_id']);
            $device = $subscription->device ?? Device::query()->find($subscription->device_id);
            $clientId = (int) $billing['client_id'];
            $subscriptionType = SubscriptionType::from($billing['subscription_type']);

            $companyPrice = (float) $plan->company_price;
            $sellingPrice = (float) $billing['selling_price'];
            $deviceCost = $device
                ? $this->deviceCosts->resolveForClientDevice($device, $clientId)
                : 0.0;
            $deviceSelling = $subscriptionType === SubscriptionType::New
                ? (float) ($billing['device_selling_price'] ?? $deviceCost)
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

            $subscription->load('device');

            $platformInvoice = $this->invoices->createInvoice(
                BillingInvoiceType::Platform,
                [[
                    'line_type' => BillingLineType::Subscription->value,
                    'description' => "Plan: {$plan->name} — Device #{$subscription->device_id}",
                    'unit_cost' => $companyPrice,
                    'unit_price' => $companyPrice,
                    'reference_type' => Subscription::class,
                    'reference_id' => $subscription->id,
                ]],
                clientId: $clientId,
                subscriptionId: $subscription->id,
                actor: $actor,
            );

            $clientInvoice = $this->invoices->createInvoice(
                BillingInvoiceType::Client,
                $this->buildClientInvoiceLines($subscription),
                clientId: $clientId,
                userId: $subscription->user_id,
                subscriptionId: $subscription->id,
                actor: $actor,
            );

            $platformInvoice->update([
                'meta' => array_merge($platformInvoice->meta ?? [], [
                    'paired_invoice_id' => $clientInvoice->id,
                    'paired_invoice_no' => $clientInvoice->invoice_no,
                ]),
            ]);
            $clientInvoice->update([
                'meta' => array_merge($clientInvoice->meta ?? [], [
                    'paired_invoice_id' => $platformInvoice->id,
                    'paired_invoice_no' => $platformInvoice->invoice_no,
                ]),
            ]);

            $subscription->update([
                'platform_invoice_id' => $platformInvoice->id,
                'client_invoice_id' => $clientInvoice->id,
            ]);

            if ($subscription->status === 'active' && $device) {
                $this->ensureDeviceStockCommitted($subscription, $device, $clientId, $actor?->id);
            }

            return $subscription->fresh(['platformInvoice', 'clientInvoice', 'subscriptionPlan']);
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
        $plan = $subscription->subscriptionPlan;
        $planName = $plan?->name ?? $subscription->plan ?? 'Subscription';
        $companyPrice = (float) $subscription->company_price;
        $sellingPrice = (float) $subscription->selling_price;

        $lines = [[
            'line_type' => BillingLineType::Subscription->value,
            'description' => "Subscription: {$planName}",
            'unit_cost' => $companyPrice,
            'unit_price' => $sellingPrice,
            'reference_type' => Subscription::class,
            'reference_id' => $subscription->id,
        ]];

        if ($subscription->isNewSubscriptionType()) {
            $device = $subscription->device;
            $deviceLabel = $device
                ? ($device->name ?: $device->imei)
                : "Device #{$subscription->device_id}";

            $lines[] = [
                'line_type' => BillingLineType::Device->value,
                'description' => "Device: {$deviceLabel}",
                'unit_cost' => (float) $subscription->device_unit_cost,
                'unit_price' => (float) $subscription->device_selling_price,
                'reference_type' => Device::class,
                'reference_id' => $subscription->device_id,
            ];
        }

        return $lines;
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
