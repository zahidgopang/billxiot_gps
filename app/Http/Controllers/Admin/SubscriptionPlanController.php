<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanBillingCycle;
use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Models\RoutePlan;
use App\Models\SubscriptionPlan;
use App\Services\AdminAuditService;
use App\Support\Billing\SubscriptionPlanNotificationCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private AdminAuditService $audit,
        private SubscriptionPlanNotificationCatalog $notificationCatalog,
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
            ...$this->notificationFormData(null),
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
            ...$this->notificationFormData($subscriptionPlan),
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

        $data['notification_email_types'] = $this->parseNotificationTypes($request, 'notification_email_types');
        $data['notification_whatsapp_types'] = $this->parseNotificationTypes($request, 'notification_whatsapp_types');
        $data['notification_route_ids'] = $this->parseRouteIds($request);

        return $data;
    }

    /**
     * @return array{notificationTypeOptions: list<array{key: string, label: string}>, routeOptions: list<array{id: int, label: string}>, selectedEmailTypes: list<string>, selectedWhatsappTypes: list<string>, selectedRouteIds: list<int>}
     */
    private function notificationFormData(?SubscriptionPlan $plan): array
    {
        $defaults = $this->notificationCatalog->defaultTypeKeys();
        $routes = RoutePlan::query()
            ->where('status', RoutePlan::STATUS_ACTIVE)
            ->orderBy('start_city')
            ->orderBy('destination_city')
            ->get()
            ->map(fn (RoutePlan $route) => [
                'id' => (int) $route->id,
                'label' => $route->routeLabel(),
            ])
            ->values()
            ->all();

        return [
            'notificationTypeOptions' => $this->notificationCatalog->standardTypeOptions(),
            'routeOptions' => $routes,
            'selectedEmailTypes' => old(
                'notification_email_types',
                $plan?->notification_email_types ?? $defaults,
            ),
            'selectedWhatsappTypes' => old(
                'notification_whatsapp_types',
                $plan?->notification_whatsapp_types ?? $defaults,
            ),
            'selectedRouteIds' => old(
                'notification_route_ids',
                $plan?->notification_route_ids ?? array_column($routes, 'id'),
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function parseNotificationTypes(Request $request, string $field): array
    {
        $allowed = $this->notificationCatalog->standardTypeKeys();
        $selected = $request->input($field, []);

        if (! is_array($selected)) {
            return [];
        }

        return array_values(array_intersect($allowed, array_map('strval', $selected)));
    }

    /**
     * @return list<int>
     */
    private function parseRouteIds(Request $request): array
    {
        $selected = $request->input('notification_route_ids', []);
        if (! is_array($selected)) {
            return [];
        }

        $validIds = RoutePlan::query()
            ->whereIn('id', array_map('intval', $selected))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values($validIds);
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
