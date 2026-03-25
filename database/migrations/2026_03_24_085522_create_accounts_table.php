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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('account_type', 30); // asset, liability, equity, revenue, expense, cogs
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // حسابات النظام لا تحذف
            $table->text('description')->nullable();
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->unsignedBigInteger('company_id');
            $table->timestamps();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('accounts')->onDelete('set null');
            $table->index(['company_id', 'code']);
            $table->index(['company_id', 'account_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
