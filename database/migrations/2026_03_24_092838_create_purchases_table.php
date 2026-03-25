<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number', 50);
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('company_id');
            $table->date('purchase_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft, approved, partial, paid, cancelled
            $table->string('payment_status', 20)->default('pending'); // pending, partial, paid, overdue
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('currency', 5)->default('SAR');
            $table->decimal('exchange_rate', 10, 4)->default(1.0000);
            $table->timestamps();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('cascade');
            $table->index(['company_id', 'purchase_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'purchase_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
