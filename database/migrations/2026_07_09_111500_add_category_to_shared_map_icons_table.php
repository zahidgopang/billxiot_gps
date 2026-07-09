<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_map_icons', function (Blueprint $table) {
            $table->string('category', 40)->default('vehicles')->after('label');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('shared_map_icons', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
