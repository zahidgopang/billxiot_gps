<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_stock_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 32)->unique();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('device_type', 32)->default('gps_tracker');
            $table->string('brand', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('condition', 20)->default('new');
            $table->string('status', 20)->default('in_stock');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('warehouse_location', 120)->nullable();
            $table->string('supplier', 120)->nullable();
            $table->string('purchase_order_ref', 64)->nullable();
            $table->date('purchased_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index('status');
            $table->index('device_type');
        });

        if (Schema::hasTable('device_stock_units')) {
            $this->migrateLegacyUnits();
            Schema::drop('device_stock_units');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_stock_orders');

        if (! Schema::hasTable('device_stock_units')) {
            Schema::create('device_stock_units', function (Blueprint $table) {
                $table->id();
                $table->string('stock_code', 32)->unique();
                $table->string('batch_ref', 32)->nullable()->index();
                $table->unsignedInteger('quantity')->default(1);
                $table->string('device_type', 32)->default('gps_tracker');
                $table->string('brand', 120)->nullable();
                $table->string('model', 120)->nullable();
                $table->string('condition', 20)->default('new');
                $table->string('status', 20)->default('in_stock');
                $table->decimal('unit_cost', 12, 2)->default(0);
                $table->decimal('selling_price', 12, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('warehouse_location', 120)->nullable();
                $table->string('supplier', 120)->nullable();
                $table->string('purchase_order_ref', 64)->nullable();
                $table->date('purchased_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    private function migrateLegacyUnits(): void
    {
        $rows = DB::table('device_stock_units')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return;
        }

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->batch_ref ?: ('unit-'.$row->id);
            $grouped[$key][] = $row;
        }

        foreach ($grouped as $key => $items) {
            $first = $items[0];
            $orderCode = str_starts_with((string) $key, 'unit-')
                ? ($first->stock_code ?: 'ORD-LEGACY-'.$first->id)
                : (string) $key;

            if (DB::table('device_stock_orders')->where('order_code', $orderCode)->exists()) {
                $orderCode .= '-'.$first->id;
            }

            DB::table('device_stock_orders')->insert([
                'order_code' => $orderCode,
                'quantity' => count($items),
                'device_type' => $first->device_type,
                'brand' => $first->brand,
                'model' => $first->model,
                'condition' => $first->condition,
                'status' => $first->status,
                'unit_cost' => $first->unit_cost,
                'selling_price' => $first->selling_price,
                'currency' => $first->currency,
                'warehouse_location' => $first->warehouse_location,
                'supplier' => $first->supplier,
                'purchase_order_ref' => $first->purchase_order_ref,
                'purchased_at' => $first->purchased_at,
                'notes' => $first->notes,
                'created_by' => $first->created_by,
                'created_at' => $first->created_at,
                'updated_at' => $first->updated_at,
            ]);
        }
    }
};
