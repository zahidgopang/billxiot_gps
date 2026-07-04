<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Authorization\RbacService;
use App\Services\Tracking\CompanyMapCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyMapSettingsController extends Controller
{
    public function __construct(
        private CompanyMapCardService $companyMapCard,
        private RbacService $rbac,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->rbac->isSuperAdmin($request->user()), 403);

        return view('admin.company-map-settings.index', [
            'companyMapCard' => $this->companyMapCard->settings(),
            'updateUrl' => route('admin.company-map-settings.update'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($this->rbac->isSuperAdmin($request->user()), 403);

        $validated = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'company_number' => 'nullable|string|max:120',
            'operation_card' => 'nullable|string|max:120',
            'support' => 'nullable|string|max:500',
            'show_on_map' => 'sometimes|boolean',
        ]);

        $result = $this->companyMapCard->updateGlobal($request->user(), $validated);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }
}
