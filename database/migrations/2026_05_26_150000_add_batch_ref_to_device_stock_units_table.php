<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_stock_units', function (Blueprint $table) {
            $table->string('batch_ref', 32)->nullable()->after('stock_code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('device_stock_units', function (Blueprint $table) {
            $table->dropIndex(['batch_ref']);
            $table->dropColumn('batch_ref');
        });
    }
};
