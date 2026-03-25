<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 30);
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('expense_account_id');
            $table->unsignedBigInteger('payment_account_id');
            $table->unsignedBigInteger('created_by');
            $table->date('expense_date');
            $table->string('name', 200);
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('tax_rate', 8, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20)->default('posted');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('expense_account_id')->references('id')->on('accounts')->onDelete('restrict');
            $table->foreign('payment_account_id')->references('id')->on('accounts')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->unique(['company_id', 'expense_number']);
            $table->index(['company_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
