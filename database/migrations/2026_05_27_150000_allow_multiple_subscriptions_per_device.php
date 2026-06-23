<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = $this->deviceIdIndexes();

        if ($indexes['unique']) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropUnique(['device_id']);
            });
        }

        // Non-unique index may already exist from 2026_05_17_100000 (device_id was added with ->index()).
        if (! $indexes['non_unique']) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index('device_id');
            });
        }
    }

    public function down(): void
    {
        $indexes = $this->deviceIdIndexes();

        if ($indexes['non_unique'] && ! $indexes['unique']) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex(['device_id']);
            });
        }

        if (! $indexes['unique']) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->unique('device_id');
            });
        }
    }

    /**
     * @return array{unique: bool, non_unique: bool}
     */
    private function deviceIdIndexes(): array
    {
        $unique = false;
        $nonUnique = false;

        try {
            $rows = DB::select('SHOW INDEX FROM `subscriptions`');
        } catch (\Throwable) {
            return ['unique' => false, 'non_unique' => false];
        }

        foreach ($rows as $row) {
            $index = array_change_key_case((array) $row, CASE_LOWER);

            if (($index['column_name'] ?? null) !== 'device_id') {
                continue;
            }

            if ((int) ($index['non_unique'] ?? 1) === 0) {
                $unique = true;
            } else {
                $nonUnique = true;
            }
        }

        return ['unique' => $unique, 'non_unique' => $nonUnique];
    }
};
