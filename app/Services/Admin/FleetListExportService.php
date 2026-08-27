<?php

namespace App\Services\Admin;

use App\Models\ClientDevice;
use App\Models\Device;
use App\Models\User;
use App\Services\Tracking\DevicePositionLoader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FleetListExportService
{
    public function __construct(
        private DevicePositionLoader $positionLoader,
    ) {}

    /**
     * @param  Collection<int, Device>  $devices
     */
    public function devicesExcel(Collection $devices, string $filename): StreamedResponse
    {
        $devices->loadMissing('user');
        $this->positionLoader->attachLatestToMany($devices);
        $clientNames = $this->clientNamesByDeviceId($devices);

        $headers = [
            __('app.admin.devices.imei'),
            __('app.forms.vehicle_name'),
            __('app.admin.devices.name'),
            __('app.forms.vehicle_number'),
            __('app.forms.vehicle_model'),
            __('app.admin.devices.type'),
            __('app.forms.vehicle_type'),
            __('app.admin.fleet_export.sim_number'),
            __('app.admin.devices.user'),
            __('app.admin.fleet_export.customer_email'),
            __('app.admin.fleet_export.customer_phone'),
            __('app.admin.users.client_link'),
            __('app.common.status'),
            __('app.admin.devices.last_known'),
        ];

        $rows = [$headers];
        foreach ($devices as $device) {
            $user = $device->user;
            $lastGps = $device->latestLocation?->recorded_at;
            $rows[] = [
                (string) ($device->imei ?? ''),
                (string) ($device->vehicle_name ?? $device->listPrimaryLabel()),
                (string) ($device->name ?? ''),
                (string) ($device->vehiclePlateNumber() ?? ''),
                (string) ($device->vehicle_model ?? ''),
                $device->deviceTypeLabel(),
                $device->vehicleTypeLabel(),
                (string) ($device->sim_number ?? ''),
                (string) ($user?->name ?? ''),
                (string) ($user?->email ?? ''),
                (string) ($user?->phone ?? ''),
                (string) ($clientNames[(int) $device->id] ?? ''),
                (string) ($device->status ?? ''),
                $lastGps ? $lastGps->timezone(config('app.timezone'))->format('Y-m-d H:i:s') : '',
            ];
        }

        return $this->spreadsheetDownload($rows, $filename, (string) __('app.admin.devices.title'));
    }

    /**
     * @param  Collection<int, User>  $users
     */
    public function usersExcel(Collection $users, string $filename): StreamedResponse
    {
        $users->loadMissing(['clients:id,name']);

        $headers = [
            __('app.common.name'),
            __('app.admin.fleet_export.customer_email'),
            __('app.admin.fleet_export.customer_phone'),
            __('app.admin.fleet_export.role'),
            __('app.admin.users.client_link'),
            __('app.admin.fleet_export.vehicles'),
            __('app.common.status'),
        ];

        $rows = [$headers];
        foreach ($users as $user) {
            $isDisabled = (int) ($user->getAttributes()['disabled'] ?? 0) === 1;
            $rows[] = [
                (string) ($user->name ?? ''),
                (string) ($user->email ?? ''),
                (string) ($user->phone ?? ''),
                (string) ($user->role ?? ''),
                $user->clients->pluck('name')->filter()->implode(', '),
                (string) ($user->tracker_devices_count ?? 0),
                $isDisabled ? __('app.common.inactive') : __('app.common.active'),
            ];
        }

        return $this->spreadsheetDownload($rows, $filename, (string) __('app.admin.users.title'));
    }

    /**
     * @param  Collection<int, Device>  $devices
     * @return array<int, string>
     */
    private function clientNamesByDeviceId(Collection $devices): array
    {
        $ids = $devices->pluck('id')->map(fn ($id) => (int) $id)->filter()->all();
        if ($ids === [] || ! Schema::hasTable('client_devices')) {
            return [];
        }

        return ClientDevice::query()
            ->whereIn('device_id', $ids)
            ->with('client:id,name')
            ->get()
            ->mapWithKeys(function (ClientDevice $row) {
                $name = $row->client?->name;

                return [(int) $row->device_id => $name ? (string) $name : ''];
            })
            ->all();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function spreadsheetDownload(array $rows, string $filename, string $sheetName): StreamedResponse
    {
        $sheetName = mb_substr(preg_replace('/[\\\\\/\?\*\[\]]+/', ' ', $sheetName) ?: 'Export', 0, 31);
        $rtl = app()->getLocale() === 'ar';

        return response()->streamDownload(function () use ($rows, $sheetName, $rtl) {
            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo '<?mso-application progid="Excel.Sheet"?>'."\n";
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
            echo ' xmlns:o="urn:schemas-microsoft-com:office:office"';
            echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"';
            echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'."\n";
            echo '<Worksheet ss:Name="'.htmlspecialchars($sheetName, ENT_XML1).'">'."\n";
            if ($rtl) {
                echo '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">';
                echo '<DisplayRightToLeft/>';
                echo '</WorksheetOptions>'."\n";
            }
            echo '<Table>'."\n";
            foreach ($rows as $row) {
                echo '<Row>';
                foreach ($row as $cell) {
                    $value = htmlspecialchars((string) $cell, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    echo '<Cell><Data ss:Type="String">'.$value.'</Data></Cell>';
                }
                echo '</Row>'."\n";
            }
            echo '</Table>'."\n";
            echo '</Worksheet>'."\n";
            echo '</Workbook>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }
}
