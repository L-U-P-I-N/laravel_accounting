<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoice_items', 'invoice_id')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->after('id');
            $table->unsignedBigInteger('product_id')->nullable()->after('invoice_id');
            $table->string('description')->nullable()->after('product_id');
            $table->decimal('quantity', 15, 2)->default(1)->after('description');
            $table->decimal('unit_price', 15, 2)->default(0)->after('quantity');
            $table->decimal('tax_rate', 8, 2)->default(0)->after('unit_price');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate');
            $table->decimal('total', 15, 2)->default(0)->after('tax_amount');

            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoice_items', 'invoice_id')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['invoice_id']);
            $table->dropColumn(['invoice_id', 'product_id', 'description', 'quantity', 'unit_price', 'tax_rate', 'tax_amount', 'total']);
        });
    }
};
