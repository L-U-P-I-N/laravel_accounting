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
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('supplier_invoice_number', 100)->nullable()->after('purchase_number');
            $table->string('attachment_path')->nullable()->after('supplier_invoice_number');
            $table->date('payment_date')->nullable()->after('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn([
                'supplier_invoice_number',
                'attachment_path',
                'payment_date',
            ]);
        });
    }
};
