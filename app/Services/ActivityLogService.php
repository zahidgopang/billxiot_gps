<?php

namespace App\Services;

use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Client;
use App\Models\Device;
use App\Models\DeviceStockOrder;
use App\Models\DeviceStockSale;
use App\Models\DeviceStockUnit;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Authorization\RbacService;
use App\Services\Authorization\TenantScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ActivityLogService
{
    public function __construct(
        private RbacService $rbac,
        private TenantScopeService $tenantScope,
    ) {}

    public function baseQuery(): Builder
    {
        return Activity::query()
            ->with(['causer'])
            ->where('log_name', AdminAuditService::LOG_NAME)
            ->orderByDesc('id');
    }

    /**
     * @return list<int>|null null = unrestricted (super admin, no client filter)
     */
    public function scopedClientIds(User $actor, ?int $filterClientId = null): ?array
    {
        if ($this->rbac->isSuperAdmin($actor)) {
            if ($filterClientId !== null) {
                return [$filterClientId];
            }

            return null;
        }

        if ($this->rbac->isClientManager($actor)) {
            $ids = $this->tenantScope->clientIdsForUser($actor);

            if ($filterClientId !== null && in_array($filterClientId, $ids, true)) {
                return [$filterClientId];
            }

            return $ids;
        }

        $ids = $this->tenantScope->visibleClientIds($actor);

        if ($filterClientId !== null && in_array($filterClientId, $ids, true)) {
            return [$filterClientId];
        }

        return $ids;
    }

    public function applyTenantScope(Builder $query, User $actor, ?int $filterClientId = null): Builder
    {
        $clientIds = $this->scopedClientIds($actor, $filterClientId);

        if ($clientIds === null) {
            return $query;
        }

        if ($clientIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $this->applyClientIdsFilter($query, $clientIds, $actor);
    }

    /**
     * @param  list<int>  $clientIds
     */
    private function applyClientIdsFilter(Builder $query, array $clientIds, User $actor): Builder
    {
        $memberUserIds = $this->memberUserIdsForClients($clientIds);

        return $query->where(function (Builder $w) use ($clientIds, $memberUserIds, $actor) {
            $w->where(function (Builder $inner) use ($clientIds) {
                $this->wherePropertiesClientIdIn($inner, $clientIds);
            });

            if ($memberUserIds !== []) {
                $w->orWhere(function (Builder $inner) use ($memberUserIds) {
                    $inner->where('causer_type', User::class)
                        ->whereIn('causer_id', $memberUserIds)
                        ->where(function (Builder $legacy) {
                            $legacy->whereNull('properties')
                                ->orWhereRaw(
                                    'JSON_EXTRACT(properties, ?) IS NULL',
                                    ['$.' . AdminAuditService::PROP_CLIENT_ID]
                                );
                        });
                });
            }

            $w->orWhere(function (Builder $inner) use ($actor) {
                $inner->where('causer_type', User::class)
                    ->where('causer_id', $actor->id);
            });
        });
    }

    /**
     * @param  list<int>  $clientIds
     */
    private function wherePropertiesClientIdIn(Builder $query, array $clientIds): void
    {
        $path = '$.' . AdminAuditService::PROP_CLIENT_ID;

        $query->where(function (Builder $w) use ($clientIds, $path) {
            foreach ($clientIds as $clientId) {
                $w->orWhereRaw(
                    'CAST(JSON_UNQUOTE(JSON_EXTRACT(properties, ?)) AS UNSIGNED) = ?',
                    [$path, (int) $clientId]
                );
            }
        });
    }

    /**
     * @param  list<int>  $clientIds
     * @return list<int>
     */
    private function memberUserIdsForClients(array $clientIds): array
    {
        return DB::table('client_members')
            ->whereIn('client_id', $clientIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->causer_id)
                ->where('causer_type', User::class);
        }

        if ($request->filled('category')) {
            $category = (string) $request->category;
            $legacyAliases = match ($category) {
                'subscription_plan' => ['subscriptionplan'],
                'billing_invoice' => ['billinginvoice'],
                'billing_payment' => ['billingpayment'],
                default => [],
            };
            $matchValues = array_values(array_unique([$category, ...$legacyAliases]));

            $query->where(function (Builder $w) use ($matchValues) {
                foreach ($matchValues as $value) {
                    $w->orWhereRaw(
                        'JSON_UNQUOTE(JSON_EXTRACT(properties, ?)) = ?',
                        ['$.' . AdminAuditService::PROP_CATEGORY, $value]
                    );
                }
            });
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function (Builder $w) use ($q) {
                $w->where('description', 'like', "%{$q}%")
                    ->orWhere('properties', 'like', "%{$q}%");
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return $query;
    }

    /**
     * @return Collection<int, User>
     */
    public function causersInScope(User $actor, ?int $filterClientId = null): Collection
    {
        $clientIds = $this->scopedClientIds($actor, $filterClientId);

        $causerIds = $this->baseQuery();
        $this->applyTenantScope($causerIds, $actor, $filterClientId);

        $ids = $causerIds
            ->where('causer_type', User::class)
            ->reorder() // DISTINCT + ORDER BY id is invalid in MySQL; ordering not needed here
            ->distinct()
            ->pluck('causer_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return collect();
        }

        $users = User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'email']);

        if ($clientIds === null || $clientIds === []) {
            return $users;
        }

        $memberIds = $this->memberUserIdsForClients($clientIds);

        return $users->filter(fn (User $u) => in_array((int) $u->id, $memberIds, true) || in_array((int) $u->id, $ids, true))
            ->values();
    }

    /**
     * @return Collection<int, Client>
     */
    public function filterClientsForActor(User $actor): Collection
    {
        if ($this->rbac->isSuperAdmin($actor)) {
            return Client::query()->orderBy('name')->get(['id', 'name']);
        }

        $ids = $this->rbac->isClientManager($actor)
            ? $this->tenantScope->clientIdsForUser($actor)
            : $this->tenantScope->visibleClientIds($actor);

        if ($ids === []) {
            return collect();
        }

        return Client::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizedProperties(Activity $log): array
    {
        $props = $log->properties instanceof \Illuminate\Support\Collection
            ? $log->properties->toArray()
            : (array) ($log->properties ?? []);

        $skip = [
            'ip', 'url', 'method',
            AdminAuditService::PROP_CLIENT_ID,
            AdminAuditService::PROP_CLIENT_NAME,
            AdminAuditService::PROP_CATEGORY,
            AdminAuditService::PROP_ACTOR_ROLE,
            AdminAuditService::PROP_PANEL,
        ];

        $details = [];
        foreach ($props as $key => $value) {
            if (in_array($key, $skip, true) || $value === null || $value === '') {
                continue;
            }
            $details[$key] = $value;
        }

        return $details;
    }

    public function subjectLabel(Activity $log): string
    {
        $subject = $this->resolveSubject($log);

        if ($subject) {
            if ($subject instanceof DeviceStockOrder || $subject instanceof DeviceStockUnit) {
                return $subject->order_code ?? $subject->name ?? $this->fallbackSubjectLabel($log);
            }

            if ($subject instanceof DeviceStockSale) {
                return $subject->invoice_no ?? $this->fallbackSubjectLabel($log);
            }

            return $subject->name
                ?? $subject->email
                ?? $subject->imei
                ?? $this->fallbackSubjectLabel($log);
        }

        $props = $this->normalizedProperties($log);

        return $props['invoice_no']
            ?? $props['order_code']
            ?? $props['imei']
            ?? $props['email']
            ?? $this->fallbackSubjectLabel($log);
    }

    private function resolveSubject(Activity $log): ?Model
    {
        if (! $log->subject_type || ! $log->subject_id) {
            return null;
        }

        $type = $log->subject_type;

        if ($type === DeviceStockUnit::class) {
            $type = DeviceStockOrder::class;
        }

        if (! class_exists($type)) {
            return null;
        }

        try {
            return $type::query()->find($log->subject_id);
        } catch (\Throwable) {
            return null;
        }
    }

    private function fallbackSubjectLabel(Activity $log): string
    {
        if (! $log->subject_type) {
            return '—';
        }

        $base = class_basename($log->subject_type);
        if ($base === 'DeviceStockUnit') {
            $base = 'DeviceStockOrder';
        }

        return $log->subject_id ? "{$base} #{$log->subject_id}" : $base;
    }

    public function clientLabel(Activity $log): ?string
    {
        $props = $log->properties instanceof \Illuminate\Support\Collection
            ? $log->properties->toArray()
            : (array) ($log->properties ?? []);

        if (! empty($props[AdminAuditService::PROP_CLIENT_NAME])) {
            return (string) $props[AdminAuditService::PROP_CLIENT_NAME];
        }

        $clientId = $props[AdminAuditService::PROP_CLIENT_ID] ?? null;

        if ($clientId) {
            return Client::query()->whereKey($clientId)->value('name');
        }

        return null;
    }

    public function categoryLabel(Activity $log): string
    {
        $props = $log->properties instanceof \Illuminate\Support\Collection
            ? $log->properties->toArray()
            : (array) ($log->properties ?? []);

        $category = $props[AdminAuditService::PROP_CATEGORY] ?? null;

        if (! $category && $log->subject_type) {
            $category = match ($log->subject_type) {
                DeviceStockOrder::class, DeviceStockUnit::class, DeviceStockSale::class => 'stock',
                User::class => 'user',
                Device::class => 'device',
                Subscription::class => 'subscription',
                SubscriptionPlan::class => 'subscription_plan',
                Client::class => 'client',
                BillingInvoice::class => 'billing_invoice',
                BillingPayment::class => 'billing_payment',
                default => strtolower(class_basename($log->subject_type)),
            };
        }

        return $category ?: 'general';
    }

    /**
     * @return list<string>
     */
    public static function filterCategoryKeys(): array
    {
        return [
            'user',
            'device',
            'subscription',
            'subscription_plan',
            'client',
            'stock',
            'billing_invoice',
            'billing_payment',
            'general',
        ];
    }

    public function categoryDisplayLabel(string $category): string
    {
        $normalized = match ($category) {
            'subscriptionplan' => 'subscription_plan',
            'billinginvoice' => 'billing_invoice',
            'billingpayment' => 'billing_payment',
            default => $category,
        };

        $key = 'app.admin.activity_log.category_' . $normalized;
        if (__($key) !== $key) {
            return __($key);
        }

        $legacyKey = 'app.admin.activity_log.category_' . $category;
        if ($legacyKey !== $key && __($legacyKey) !== $legacyKey) {
            return __($legacyKey);
        }

        return ucwords(str_replace('_', ' ', $normalized));
    }

    public function isClientScopedView(User $actor): bool
    {
        return $this->rbac->isClientManager($actor);
    }
}
