<?php

namespace App\Services\Tracking;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\Authorization\RbacService;

/**
 * Global company information card for the web live map (super admin configures once).
 */
class CompanyMapCardService
{
    public const STORAGE_KEY = 'company_map_card';

    /** @var list<string> */
    public const TEXT_KEYS = [
        'company_name',
        'company_number',
        'operation_card',
        'support',
    ];

    public function __construct(
        private RbacService $rbac,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->normalize(AppSetting::getValue(self::STORAGE_KEY));
    }

    /**
     * @deprecated Use settings() — kept for callers that pass an actor.
     *
     * @return array<string, mixed>
     */
    public function forActor(?User $actor = null): array
    {
        return $this->settings();
    }

    /**
     * Payload for the live map (bus name/plate come from the selected vehicle).
     *
     * @return array<string, mixed>
     */
    public function mapPayload(): array
    {
        $data = $this->settings();

        return [
            'enabled' => $this->shouldDisplay($data),
            'company_name' => $data['company_name'],
            'company_number' => $data['company_number'],
            'operation_card' => $data['operation_card'],
            'support' => $data['support'],
        ];
    }

    /**
     * @deprecated Use mapPayload()
     *
     * @return array<string, mixed>
     */
    public function mapPayloadForActor(?User $actor = null): array
    {
        return $this->mapPayload();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{success: bool, company_map_card: array<string, mixed>, message?: string}
     */
    public function updateGlobal(User $actor, array $input): array
    {
        if (! $this->rbac->isSuperAdmin($actor)) {
            return [
                'success' => false,
                'company_map_card' => $this->settings(),
                'message' => (string) __('app.common.failed'),
            ];
        }

        $next = $this->normalize($input);
        $next['show_on_map'] = filter_var($input['show_on_map'] ?? false, FILTER_VALIDATE_BOOLEAN);

        AppSetting::putValue(self::STORAGE_KEY, $next);

        return [
            'success' => true,
            'company_map_card' => $next,
            'message' => (string) __('app.tracking.settings_saved'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function shouldDisplay(array $data): bool
    {
        if (! ($data['show_on_map'] ?? false)) {
            return false;
        }

        foreach (self::TEXT_KEYS as $key) {
            if (trim((string) ($data[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalize(array $raw): array
    {
        $out = ['show_on_map' => false];
        foreach (self::TEXT_KEYS as $key) {
            $out[$key] = trim((string) ($raw[$key] ?? ''));
        }
        if (array_key_exists('show_on_map', $raw)) {
            $out['show_on_map'] = filter_var($raw['show_on_map'], FILTER_VALIDATE_BOOLEAN);
        }

        return $out;
    }
}
