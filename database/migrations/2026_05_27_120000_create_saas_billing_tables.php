<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_months');
            $table->decimal('company_price', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->json('features')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'is_public', 'sort_order']);
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 32)->unique();
            $table->string('invoice_type', 20); // platform | client
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance_due', 12, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('status', 20)->default('unpaid');
            $table->date('due_date')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index(['invoice_type', 'status']);
            $table->index(['client_id', 'issued_at']);
        });

        Schema::create('billing_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_invoice_id')->constrained('billing_invoices')->cascadeOnDelete();
            $table->string('line_type', 30); // subscription | device | adjustment
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->nullableMorphs('reference');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_invoice_id')->constrained('billing_invoices')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 50)->nullable();
            $table->string('reference', 100)->nullable();
            $table->timestamp('paid_at');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable()->after('plan')->constrained('subscription_plans')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->after('subscription_plan_id')->constrained('clients')->nullOnDelete();
            $table->decimal('company_price', 12, 2)->nullable()->after('status');
            $table->decimal('selling_price', 12, 2)->nullable()->after('company_price');
            $table->decimal('device_unit_cost', 12, 2)->nullable()->after('selling_price');
            $table->decimal('device_selling_price', 12, 2)->nullable()->after('device_unit_cost');
            $table->foreignId('platform_invoice_id')->nullable()->after('device_selling_price')->constrained('billing_invoices')->nullOnDelete();
            $table->foreignId('client_invoice_id')->nullable()->after('platform_invoice_id')->constrained('billing_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_invoice_id');
            $table->dropConstrainedForeignId('platform_invoice_id');
            $table->dropColumn([
                'device_selling_price',
                'device_unit_cost',
                'selling_price',
                'company_price',
                'client_id',
                'subscription_plan_id',
            ]);
        });

        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_invoice_lines');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('subscription_plans');
    }
};
