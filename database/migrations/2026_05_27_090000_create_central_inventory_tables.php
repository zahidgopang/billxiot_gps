<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();
            $table->string('device_type', 32);
            $table->string('brand', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('sku', 64)->nullable();
            $table->timestamps();

            $table->unique(['device_type', 'brand', 'model']);
            $table->index('device_type');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('scope', 20); // warehouse|client
            $table->unsignedBigInteger('scope_id')->nullable()->index(); // client_id when scope=client
            $table->string('type', 32); // purchase|sale_transfer|install|return|adjustment
            $table->integer('quantity'); // signed (+in, -out)
            $table->string('source_type', 191)->nullable(); // class or label
            $table->unsignedBigInteger('source_id')->nullable();
            $table->dateTime('occurred_at')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('inventory_products')->cascadeOnDelete();
            $table->index(['scope', 'scope_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });

        Schema::create('inventory_fifo_layers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('scope', 20); // warehouse|client
            $table->unsignedBigInteger('scope_id')->nullable()->index(); // client_id when scope=client
            $table->dateTime('received_at')->index();
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('remaining_qty')->default(0);
            $table->unsignedBigInteger('source_purchase_order_id')->nullable()->index(); // device_stock_orders.id
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('inventory_products')->cascadeOnDelete();
            $table->index(['scope', 'scope_id', 'received_at']);
        });

        Schema::create('device_inventory_summary', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('scope', 20); // warehouse|client
            $table->unsignedBigInteger('scope_id')->nullable()->index(); // client_id when scope=client

            $table->unsignedInteger('total_purchased')->default(0);
            $table->unsignedInteger('total_sold')->default(0);
            $table->unsignedInteger('total_installed')->default(0);
            $table->unsignedInteger('total_returned')->default(0);
            $table->integer('available_qty')->default(0);

            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('inventory_products')->cascadeOnDelete();
            $table->unique(['product_id', 'scope', 'scope_id']);
            $table->index(['scope', 'scope_id', 'available_qty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_inventory_summary');
        Schema::dropIfExists('inventory_fifo_layers');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_products');
    }
};

