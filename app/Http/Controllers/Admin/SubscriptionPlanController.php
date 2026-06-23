<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanBillingCycle;
use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\AdminAuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private AdminAuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorizePermission('billing.manage');

        $plans = SubscriptionPlan::query()
            ->when($request->query('q'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('billing_cycle')
            ->paginate(15)
            ->withQueryString();

        return view('admin.subscription-plans.index', [
            'plans' => $plans,
            'panel' => $this->panelPrefix(),
        ]);
    }

    public function create()
    {
        $this->authorizePermission('billing.manage');

        return view('admin.subscription-plans.create', [
            'plan' => null,
            'panel' => $this->panelPrefix(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePermission('billing.manage');

        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['features'] = $this->parseFeatures($request);

        $plan = SubscriptionPlan::create($data);

        $this->audit->logCreated($plan, "subscription plan {$plan->name}");

        return redirect()->to($this->panelRoute('subscription-plans.index'))
            ->with('success', __('app.billing.plan_created'));
    }

    public function edit(SubscriptionPlan $subscriptionPlan)
    {
        $this->authorizePermission('billing.manage');

        return view('admin.subscription-plans.edit', [
            'plan' => $subscriptionPlan,
            'panel' => $this->panelPrefix(),
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $this->authorizePermission('billing.manage');

        $data = $this->validated($request, $subscriptionPlan);
        $data['features'] = $this->parseFeatures($request);

        $subscriptionPlan->update($data);

        $this->audit->logUpdated($subscriptionPlan, "subscription plan {$subscriptionPlan->name}");

        return redirect()->to($this->panelRoute('subscription-plans.index'))
            ->with('success', __('app.billing.plan_updated'));
    }

    public function destroy(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $this->authorizePermission('billing.manage');

        $name = $subscriptionPlan->name;
        $subscriptionCount = $subscriptionPlan->subscriptions()->count();

        $this->audit->log('deleted', "subscription plan {$name}", $subscriptionPlan, [
            'name' => $name,
            'subscriptions_unlinked' => $subscriptionCount,
        ]);

        $subscriptionPlan->delete();

        return redirect()->to($this->panelRoute('subscription-plans.index'))
            ->with('success', __('app.billing.plan_deleted'));
    }

    private function validated(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'billing_cycle' => ['required', Rule::in(PlanBillingCycle::values())],
            'company_price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'status' => 'required|in:active,inactive',
            'is_public' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);

        $cycle = PlanBillingCycle::from($data['billing_cycle']);
        $data['duration_months'] = $cycle->durationMonths();
        $data['slug'] = SubscriptionPlan::generateUniqueSlug(
            $data['name'],
            $data['billing_cycle'],
            $plan?->id
        );
        $data['is_public'] = $request->boolean('is_public', true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    /**
     * @return list<string>
     */
    private function parseFeatures(Request $request): array
    {
        $raw = $request->input('features_text', '');

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $raw))));
    }
}
