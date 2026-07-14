<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Shared suggestion icons (SVG Repo side views) ship with nose facing East.
     * They were often saved with rotation_offset 0 (North), so Live Tracking
     * rotated them as if they were top-down nose-up artwork.
     */
    public function up(): void
    {
        DB::table('shared_map_icons')
            ->where('rotation_offset', 0)
            ->update(['rotation_offset' => -90]);

        $devices = DB::table('tc_devices')
            ->select(['id', 'attributes'])
            ->where(function ($q) {
                $q->where('attributes', 'like', '%Shared/%')
                    ->orWhere('attributes', 'like', '%shared_%');
            })
            ->get();

        foreach ($devices as $row) {
            $attrs = json_decode((string) $row->attributes, true);
            if (! is_array($attrs)) {
                continue;
            }

            $path = (string) ($attrs['map_builtin_icon_path'] ?? '');
            $type = (string) ($attrs['vehicle_type'] ?? '');
            $usesShared = str_starts_with($path, 'Shared/')
                || str_starts_with($type, 'shared_');
            if (! $usesShared) {
                continue;
            }

            $offset = $attrs['map_icon_rotation_offset'] ?? null;
            if ($offset !== null && $offset !== '' && (int) $offset !== 0) {
                continue;
            }

            $attrs['map_icon_rotation_offset'] = '-90';
            DB::table('tc_devices')->where('id', $row->id)->update([
                'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible data correction — do not force icons back to North.
    }
};
