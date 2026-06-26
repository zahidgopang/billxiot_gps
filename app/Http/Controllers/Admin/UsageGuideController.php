<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UsageGuideController extends Controller
{
    public function index(): View
    {
        abort_unless(Gate::allows('super-admin'), 403);

        return view('admin.usage.index', $this->usageViewData());
    }

    public function pdf(): Response|StreamedResponse
    {
        abort_unless(Gate::allows('super-admin'), 403);

        $data = $this->usageViewData();
        $filename = 'billxiot-gps-usage-guide-' . now()->format('Y-m-d') . '.pdf';

        return Pdf::loadView('admin.usage.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    /**
     * @return array{
     *     flowSteps: list<array<string, mixed>>,
     *     roles: list<array<string, mixed>>,
     *     troubleshooting: list<array<string, mixed>>,
     *     generatedAt: \Illuminate\Support\Carbon,
     *     brandName: string
     * }
     */
    private function usageViewData(): array
    {
        return [
            'flowSteps' => $this->flowSteps(),
            'roles' => $this->roleMatrix(),
            'troubleshooting' => $this->troubleshooting(),
            'generatedAt' => now(),
            'brandName' => (string) config('branding.name', 'BillX GPS'),
        ];
    }

    /**
     * @return list<array{num: int, icon: string, accent: string, title: string, who: string, panel: string, route: ?string, route_label: ?string, bullets: list<string>, checks: list<string>, note: ?string}>
     */
    private function flowSteps(): array
    {
        return [
            [
                'num' => 1,
                'icon' => 'fa-building',
                'accent' => '#1976d2',
                'title' => __('app.admin.usage.step1_title'),
                'who' => __('app.admin.usage.step1_who'),
                'panel' => __('app.admin.usage.step1_panel'),
                'route' => route('admin.clients.index'),
                'route_label' => __('app.admin.usage.link_clients'),
                'bullets' => [
                    __('app.admin.usage.step1_b1'),
                    __('app.admin.usage.step1_b2'),
                    __('app.admin.usage.step1_b3'),
                ],
                'checks' => [
                    __('app.admin.usage.step1_c1'),
                    __('app.admin.usage.step1_c2'),
                ],
                'note' => __('app.admin.usage.step1_note'),
            ],
            [
                'num' => 2,
                'icon' => 'fa-warehouse',
                'accent' => '#5c6bc0',
                'title' => __('app.admin.usage.step2_title'),
                'who' => __('app.admin.usage.step2_who'),
                'panel' => __('app.admin.usage.step2_panel'),
                'route' => route('admin.device-stock.index'),
                'route_label' => __('app.admin.usage.link_stock'),
                'bullets' => [
                    __('app.admin.usage.step2_b1'),
                    __('app.admin.usage.step2_b2'),
                    __('app.admin.usage.step2_b3'),
                ],
                'checks' => [
                    __('app.admin.usage.step2_c1'),
                ],
                'note' => null,
            ],
            [
                'num' => 3,
                'icon' => 'fa-file-invoice-dollar',
                'accent' => '#00897b',
                'title' => __('app.admin.usage.step3_title'),
                'who' => __('app.admin.usage.step3_who'),
                'panel' => __('app.admin.usage.step3_panel'),
                'route' => route('admin.device-stock-sales.index'),
                'route_label' => __('app.admin.usage.link_sales'),
                'bullets' => [
                    __('app.admin.usage.step3_b1'),
                    __('app.admin.usage.step3_b2'),
                    __('app.admin.usage.step3_b3'),
                ],
                'checks' => [
                    __('app.admin.usage.step3_c1'),
                    __('app.admin.usage.step3_c2'),
                ],
                'note' => __('app.admin.usage.step3_note'),
            ],
            [
                'num' => 4,
                'icon' => 'fa-user',
                'accent' => '#0288d1',
                'title' => __('app.admin.usage.step4_title'),
                'who' => __('app.admin.usage.step4_who'),
                'panel' => __('app.admin.usage.step4_panel'),
                'route' => route('admin.users.index'),
                'route_label' => __('app.admin.usage.link_users'),
                'bullets' => [
                    __('app.admin.usage.step4_b1'),
                    __('app.admin.usage.step4_b2'),
                    __('app.admin.usage.step4_b3'),
                ],
                'checks' => [
                    __('app.admin.usage.step4_c1'),
                    __('app.admin.usage.step4_c2'),
                ],
                'note' => __('app.admin.usage.step4_note'),
            ],
            [
                'num' => 5,
                'icon' => 'fa-satellite-dish',
                'accent' => '#e65100',
                'title' => __('app.admin.usage.step5_title'),
                'who' => __('app.admin.usage.step5_who'),
                'panel' => __('app.admin.usage.step5_panel'),
                'route' => route('admin.devices.index'),
                'route_label' => __('app.admin.usage.link_devices'),
                'bullets' => [
                    __('app.admin.usage.step5_b1'),
                    __('app.admin.usage.step5_b2'),
                    __('app.admin.usage.step5_b3'),
                    __('app.admin.usage.step5_b4'),
                ],
                'checks' => [
                    __('app.admin.usage.step5_c1'),
                    __('app.admin.usage.step5_c2'),
                    __('app.admin.usage.step5_c3'),
                ],
                'note' => null,
            ],
            [
                'num' => 6,
                'icon' => 'fa-credit-card',
                'accent' => '#c62828',
                'title' => __('app.admin.usage.step6_title'),
                'who' => __('app.admin.usage.step6_who'),
                'panel' => __('app.admin.usage.step6_panel'),
                'route' => route('admin.subscriptions.index'),
                'route_label' => __('app.admin.usage.link_subscriptions'),
                'bullets' => [
                    __('app.admin.usage.step6_b1'),
                    __('app.admin.usage.step6_b2'),
                    __('app.admin.usage.step6_b3'),
                    __('app.admin.usage.step6_b4'),
                ],
                'checks' => [
                    __('app.admin.usage.step6_c1'),
                    __('app.admin.usage.step6_c2'),
                ],
                'note' => __('app.admin.usage.step6_note'),
            ],
            [
                'num' => 7,
                'icon' => 'fa-map-location-dot',
                'accent' => '#2e7d32',
                'title' => __('app.admin.usage.step7_title'),
                'who' => __('app.admin.usage.step7_who'),
                'panel' => __('app.admin.usage.step7_panel'),
                'route' => null,
                'route_label' => null,
                'bullets' => [
                    __('app.admin.usage.step7_b1'),
                    __('app.admin.usage.step7_b2'),
                    __('app.admin.usage.step7_b3'),
                ],
                'checks' => [
                    __('app.admin.usage.step7_c1'),
                    __('app.admin.usage.step7_c2'),
                ],
                'note' => null,
            ],
        ];
    }

    /**
     * @return list<array{action: string, super: bool, admin: bool, client: bool, end: bool}>
     */
    private function roleMatrix(): array
    {
        return [
            ['action' => __('app.admin.usage.role_create_client'), 'super' => true, 'admin' => true, 'client' => false, 'end' => false],
            ['action' => __('app.admin.usage.role_warehouse_stock'), 'super' => true, 'admin' => true, 'client' => false, 'end' => false],
            ['action' => __('app.admin.usage.role_stock_sale'), 'super' => true, 'admin' => true, 'client' => false, 'end' => false],
            ['action' => __('app.admin.usage.role_end_users'), 'super' => true, 'admin' => true, 'client' => true, 'end' => false],
            ['action' => __('app.admin.usage.role_devices'), 'super' => true, 'admin' => true, 'client' => true, 'end' => false],
            ['action' => __('app.admin.usage.role_subscriptions'), 'super' => true, 'admin' => true, 'client' => true, 'end' => false],
            ['action' => __('app.admin.usage.role_live_map'), 'super' => true, 'admin' => true, 'client' => true, 'end' => true],
        ];
    }

    /**
     * @return list<array{symptom: string, cause: string, fix: string}>
     */
    private function troubleshooting(): array
    {
        return [
            [
                'symptom' => __('app.admin.usage.ts1_symptom'),
                'cause' => __('app.admin.usage.ts1_cause'),
                'fix' => __('app.admin.usage.ts1_fix'),
            ],
            [
                'symptom' => __('app.admin.usage.ts2_symptom'),
                'cause' => __('app.admin.usage.ts2_cause'),
                'fix' => __('app.admin.usage.ts2_fix'),
            ],
            [
                'symptom' => __('app.admin.usage.ts3_symptom'),
                'cause' => __('app.admin.usage.ts3_cause'),
                'fix' => __('app.admin.usage.ts3_fix'),
            ],
            [
                'symptom' => __('app.admin.usage.ts4_symptom'),
                'cause' => __('app.admin.usage.ts4_cause'),
                'fix' => __('app.admin.usage.ts4_fix'),
            ],
        ];
    }
}
