<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Concerns\RespondsWithMobileJson;
use App\Services\Tracking\CompanyMapCardService;
use App\Services\Tracking\TrackingSettingsService;
use Illuminate\Http\Request;

class TrackingSettingsController extends Controller
{
    use RespondsWithMobileJson;

    public function __construct(
        private TrackingSettingsService $settings,
        private CompanyMapCardService $companyMapCard,
    ) {}

    public function show(Request $request)
    {
        return $this->mobileSuccess([
            'settings' => $this->settings->forActor($request->user()),
            'keys' => TrackingSettingsService::KEYS,
            'company_map_card' => $this->companyMapCard->mapPayload(),
        ]);
    }

    public function companyMapCard()
    {
        return $this->mobileSuccess([
            'company_map_card' => $this->companyMapCard->mapPayload(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => 'required|array',
        ]);

        $result = $this->settings->update($request->user(), $validated['settings']);

        if (! ($result['success'] ?? false)) {
            return $this->mobileError(
                (string) ($result['message'] ?? __('app.tracking.settings_save_failed')),
                422
            );
        }

        return $this->mobileSuccess([
            'settings' => $result['settings'],
            'company_map_card' => $this->companyMapCard->mapPayload(),
            'message' => (string) __('app.tracking.settings_saved'),
        ]);
    }
}
