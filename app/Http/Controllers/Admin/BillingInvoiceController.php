<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingInvoiceType;
use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\BillingInvoice;
use App\Services\Billing\BillingInvoiceService;
use Illuminate\Http\Request;

class BillingInvoiceController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private BillingInvoiceService $billing,
    ) {}

    public function index(Request $request)
    {
        $this->authorizePermission('billing.view');

        $type = $request->query('type', BillingInvoiceType::Platform->value);
        if (! in_array($type, [BillingInvoiceType::Platform->value, BillingInvoiceType::Client->value], true)) {
            $type = BillingInvoiceType::Platform->value;
        }

        $q = BillingInvoice::query()->with([
            'client',
            'user',
            'subscription.platformInvoice',
            'subscription.clientInvoice',
        ]);

        $clientIds = $this->scopedClientIds($request->user());
        if ($clientIds !== null) {
            $q->whereIn('client_id', $clientIds !== [] ? $clientIds : [0]);
        }

        $q->where('invoice_type', $type);

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        if ($search = trim((string) $request->query('q'))) {
            $like = '%'.$search.'%';
            $q->where(function ($query) use ($like) {
                $query->where('invoice_no', 'like', $like)
                    ->orWhere('meta->paired_invoice_no', 'like', $like)
                    ->orWhereHas('subscription', function ($sub) use ($like) {
                        $sub->where(function ($s) use ($like) {
                            $s->whereHas('platformInvoice', fn ($inv) => $inv->where('invoice_no', 'like', $like))
                                ->orWhereHas('clientInvoice', fn ($inv) => $inv->where('invoice_no', 'like', $like));
                        });
                    });
            });
        }

        $invoices = $q->orderByDesc('issued_at')->paginate(20)->withQueryString();

        return view('admin.billing-invoices.index', [
            'invoices' => $invoices,
            'type' => $type,
            'panel' => $this->panelPrefix(),
        ]);
    }

    public function show(Request $request, BillingInvoice $billingInvoice)
    {
        $this->authorizePermission('billing.view');
        $this->authorizeInvoice($billingInvoice);

        $billingInvoice->load([
            'lines',
            'payments.recorder',
            'client',
            'user',
            'subscription.device',
            'subscription.platformInvoice',
            'subscription.clientInvoice',
        ]);

        $pairedInvoice = $billingInvoice->pairedInvoice();
        $listTab = $billingInvoice->isPlatformType()
            ? BillingInvoiceType::Platform->value
            : BillingInvoiceType::Client->value;

        if ($request->query('modal') === '1' || $request->ajax()) {
            return view('admin.billing-invoices._modal', [
                'invoice' => $billingInvoice,
                'pairedInvoice' => $pairedInvoice,
                'panel' => $this->panelPrefix(),
                'listTab' => $listTab,
            ]);
        }

        return view('admin.billing-invoices.show', [
            'invoice' => $billingInvoice,
            'pairedInvoice' => $pairedInvoice,
            'panel' => $this->panelPrefix(),
            'listTab' => $listTab,
        ]);
    }

    public function storePayment(Request $request, BillingInvoice $billingInvoice)
    {
        $this->authorizePermission('billing.manage');
        $this->authorizeInvoice($billingInvoice);

        if ($billingInvoice->isCancelled()) {
            return back()->withErrors(['amount' => __('app.billing.invoice_cancelled')]);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . max(0.01, (float) $billingInvoice->balance_due),
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $this->billing->recordPayment(
            $billingInvoice,
            (float) $data['amount'],
            $request->user(),
            $data['payment_method'] ?? null,
            $data['reference'] ?? null,
            $data['notes'] ?? null,
        );

        return back()->with('success', __('app.billing.payment_recorded'));
    }

    private function authorizeInvoice(BillingInvoice $invoice): void
    {
        $clientIds = $this->scopedClientIds(auth()->user());
        if ($clientIds === null) {
            return;
        }

        if (! in_array((int) $invoice->client_id, $clientIds, true)) {
            abort(403);
        }
    }

    /**
     * @return list<int>|null
     */
    private function scopedClientIds($user): ?array
    {
        if ($this->rbac()->isSuperAdmin($user)) {
            return null;
        }

        return $this->tenantScope()->visibleClientIds($user);
    }
}
