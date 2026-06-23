<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_stock_units', function (Blueprint $table) {
            $table->id();
            $table->string('stock_code', 32)->unique();
            $table->string('name')->nullable();
            $table->string('device_type', 32)->default('gps_tracker');
            $table->string('brand', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('imei', 32)->nullable()->unique();
            $table->string('serial_number', 64)->nullable();
            $table->string('condition', 20)->default('new');
            $table->string('status', 20)->default('in_stock');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('warehouse_location', 120)->nullable();
            $table->string('supplier', 120)->nullable();
            $table->string('purchase_order_ref', 64)->nullable();
            $table->date('purchased_at')->nullable();
            $table->date('sold_at')->nullable();
            $table->unsignedBigInteger('fleet_device_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index('status');
            $table->index('device_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_stock_units');
    }
};
