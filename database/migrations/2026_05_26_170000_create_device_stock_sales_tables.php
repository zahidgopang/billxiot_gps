<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_stock_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('device_stock_orders', 'sold_quantity')) {
                $table->unsignedInteger('sold_quantity')->default(0)->after('quantity');
                $table->index('sold_quantity');
            }
        });

        Schema::create('device_stock_sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 32)->unique();
            $table->unsignedBigInteger('client_id')->index();
            $table->string('currency', 3)->default('USD');
            $table->string('status', 20)->default('issued'); // issued|cancelled
            $table->date('issued_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });

        Schema::create('device_stock_sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id')->index();
            $table->unsignedBigInteger('stock_order_id')->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0); // selling price per unit (snapshot)
            $table->decimal('unit_cost', 12, 2)->default(0); // cost per unit (snapshot)
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('device_stock_sales')->cascadeOnDelete();
            $table->foreign('stock_order_id')->references('id')->on('device_stock_orders')->cascadeOnDelete();

            $table->index(['stock_order_id', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_stock_sale_items');
        Schema::dropIfExists('device_stock_sales');

        Schema::table('device_stock_orders', function (Blueprint $table) {
            if (Schema::hasColumn('device_stock_orders', 'sold_quantity')) {
                $table->dropIndex(['sold_quantity']);
                $table->dropColumn('sold_quantity');
            }
        });
    }
};

