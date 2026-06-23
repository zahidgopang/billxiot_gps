<?php

namespace App\Services\Billing;

use App\Enums\BillingInvoiceStatus;
use App\Enums\BillingInvoiceType;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BillingInvoiceService
{
    public function nextInvoiceNo(BillingInvoiceType $type): string
    {
        $prefix = $type->numberPrefix() . '-' . now()->format('Y') . '-';
        $last = BillingInvoice::query()
            ->where('invoice_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('invoice_no');

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array{line_type:string,description:string,quantity?:int,unit_cost:float,unit_price:float,reference_type?:string,reference_id?:int}>  $lines
     */
    public function createInvoice(
        BillingInvoiceType $type,
        array $lines,
        ?int $clientId = null,
        ?int $userId = null,
        ?int $subscriptionId = null,
        ?User $actor = null,
        ?Carbon $dueDate = null,
        ?string $notes = null,
    ): BillingInvoice {
        return DB::transaction(function () use ($type, $lines, $clientId, $userId, $subscriptionId, $actor, $dueDate, $notes) {
            $subtotal = 0.0;
            foreach ($lines as $line) {
                $qty = (int) ($line['quantity'] ?? 1);
                $subtotal += $qty * (float) $line['unit_price'];
            }

            $invoice = BillingInvoice::create([
                'invoice_no' => $this->nextInvoiceNo($type),
                'invoice_type' => $type->value,
                'client_id' => $clientId,
                'user_id' => $userId,
                'subscription_id' => $subscriptionId,
                'subtotal' => $subtotal,
                'tax_amount' => 0,
                'total' => $subtotal,
                'amount_paid' => 0,
                'balance_due' => $subtotal,
                'currency' => 'USD',
                'status' => BillingInvoiceStatus::defaultFor($type)->value,
                'due_date' => ($dueDate ?? now()->addDays(30))->toDateString(),
                'issued_at' => now(),
                'notes' => $notes,
                'created_by' => $actor?->id,
            ]);

            foreach ($lines as $line) {
                $qty = (int) ($line['quantity'] ?? 1);
                $unitPrice = (float) $line['unit_price'];
                $invoice->lines()->create([
                    'line_type' => $line['line_type'],
                    'description' => $line['description'],
                    'quantity' => $qty,
                    'unit_cost' => (float) ($line['unit_cost'] ?? 0),
                    'unit_price' => $unitPrice,
                    'line_total' => $qty * $unitPrice,
                    'reference_type' => $line['reference_type'] ?? null,
                    'reference_id' => $line['reference_id'] ?? null,
                ]);
            }

            return $invoice->fresh(['lines']);
        });
    }

    /**
     * Replace all line items and recalculate totals (unpaid balance only).
     *
     * @param  list<array{line_type:string,description:string,quantity?:int,unit_cost:float,unit_price:float,reference_type?:string,reference_id?:int}>  $lines
     */
    public function replaceLines(BillingInvoice $invoice, array $lines): BillingInvoice
    {
        return DB::transaction(function () use ($invoice, $lines) {
            $invoice->lines()->delete();

            $subtotal = 0.0;
            foreach ($lines as $line) {
                $qty = (int) ($line['quantity'] ?? 1);
                $unitPrice = (float) $line['unit_price'];
                $lineTotal = $qty * $unitPrice;
                $subtotal += $lineTotal;

                $invoice->lines()->create([
                    'line_type' => $line['line_type'],
                    'description' => $line['description'],
                    'quantity' => $qty,
                    'unit_cost' => (float) ($line['unit_cost'] ?? 0),
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'reference_type' => $line['reference_type'] ?? null,
                    'reference_id' => $line['reference_id'] ?? null,
                ]);
            }

            $amountPaid = (float) $invoice->amount_paid;
            $invoice->subtotal = $subtotal;
            $invoice->tax_amount = 0;
            $invoice->total = $subtotal;
            $invoice->balance_due = max(0, round($subtotal - $amountPaid, 2));
            $invoice->status = $this->resolveStatus($invoice)->value;
            $invoice->save();

            return $invoice->fresh(['lines']);
        });
    }

    public function recordPayment(
        BillingInvoice $invoice,
        float $amount,
        ?User $actor = null,
        ?string $method = null,
        ?string $reference = null,
        ?string $notes = null,
    ): BillingPayment {
        return DB::transaction(function () use ($invoice, $amount, $actor, $method, $reference, $notes) {
            $payment = $invoice->payments()->create([
                'amount' => $amount,
                'payment_method' => $method,
                'reference' => $reference,
                'paid_at' => now(),
                'notes' => $notes,
                'recorded_by' => $actor?->id,
            ]);

            $invoice->amount_paid = round((float) $invoice->amount_paid + $amount, 2);
            $invoice->balance_due = max(0, round((float) $invoice->total - (float) $invoice->amount_paid, 2));
            $invoice->status = $this->resolveStatus($invoice)->value;
            if ($invoice->balance_due <= 0) {
                $invoice->paid_at = now();
            }
            $invoice->save();

            return $payment;
        });
    }

    public function cancelInvoice(BillingInvoice $invoice, ?User $actor = null): void
    {
        if ($invoice->isCancelled()) {
            return;
        }

        $invoice->update([
            'status' => BillingInvoiceStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancelled_by' => $actor?->id,
            'balance_due' => 0,
        ]);
    }

    public function markOverdueInvoices(): int
    {
        return BillingInvoice::query()
            ->where('invoice_type', BillingInvoiceType::Platform->value)
            ->whereIn('status', [
                BillingInvoiceStatus::Unpaid->value,
                BillingInvoiceStatus::Partial->value,
            ])
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => BillingInvoiceStatus::Overdue->value]);
    }

    public function resolveStatus(BillingInvoice $invoice): BillingInvoiceStatus
    {
        if ($invoice->status === BillingInvoiceStatus::Cancelled->value) {
            return BillingInvoiceStatus::Cancelled;
        }

        $paid = (float) $invoice->amount_paid;
        $total = (float) $invoice->total;

        if ($total > 0 && $paid >= $total) {
            return BillingInvoiceStatus::Paid;
        }

        if ($paid > 0 && $paid < $total) {
            return BillingInvoiceStatus::Partial;
        }

        $type = $invoice->typeEnum();
        if ($type === BillingInvoiceType::Platform
            && $invoice->due_date
            && $invoice->due_date->isPast()) {
            return BillingInvoiceStatus::Overdue;
        }

        return BillingInvoiceStatus::defaultFor($type);
    }
}
