<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_map_icons', function (Blueprint $table) {
            $table->smallInteger('rotation_offset')->default(0)->after('mime');
        });

        // Existing SVG Repo side-view icons face East; offset so nose aligns with GPS heading.
        DB::table('shared_map_icons')->update(['rotation_offset' => -90]);
    }

    public function down(): void
    {
        Schema::table('shared_map_icons', function (Blueprint $table) {
            $table->dropColumn('rotation_offset');
        });
    }
};
