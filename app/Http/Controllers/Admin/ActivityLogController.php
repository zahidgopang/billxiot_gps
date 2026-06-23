<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithTenantAuthorization;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use InteractsWithTenantAuthorization;

    public function __construct(
        private ActivityLogService $activityLog,
    ) {}

    public function index(Request $request)
    {
        $this->authorizePermission('activity.view');

        $actor = $request->user();
        $filterClientId = $request->filled('client_id') ? (int) $request->client_id : null;

        if ($filterClientId !== null) {
            $this->authorizeVisibleClient($request, $filterClientId);
        }

        $query = $this->activityLog->baseQuery();
        $this->activityLog->applyTenantScope($query, $actor, $filterClientId);
        $this->activityLog->applyFilters($query, $request);

        $logs = $query->paginate(30)->withQueryString();

        if ($logs->currentPage() > 1 && $logs->lastPage() > 0 && $logs->currentPage() > $logs->lastPage()) {
            return redirect()->to($logs->url($logs->lastPage()));
        }

        $causers = $this->activityLog->causersInScope($actor, $filterClientId);
        $filterClients = $this->activityLog->filterClientsForActor($actor);
        $clientScoped = $this->activityLog->isClientScopedView($actor);
        $showClientColumn = ! $clientScoped && $filterClients->count() > 1;
        $panel = $this->panelPrefix();

        $clientCompany = null;
        if ($clientScoped) {
            $primaryId = $this->tenantScope()->primaryClientIdForUser($actor)
                ?? ($filterClients->first()?->id);
            $clientCompany = $filterClients->firstWhere('id', $primaryId)
                ?? $filterClients->first();
        }

        return view('admin.activity-log.index', compact(
            'logs',
            'causers',
            'filterClients',
            'clientScoped',
            'showClientColumn',
            'panel',
            'clientCompany',
        ));
    }
}
