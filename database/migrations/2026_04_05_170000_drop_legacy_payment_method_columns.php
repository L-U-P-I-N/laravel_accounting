<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropSalesViews();
        $this->dropLegacyPaymentsIndexes();

        if (Schema::hasColumn('payments', 'payment_method_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_method_id');
            });
        }

        if (Schema::hasColumn('invoices', 'payment_method_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_method_id');
            });
        }

        if (Schema::hasColumn('purchases', 'payment_method_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_method_id');
            });
        }

        if (Schema::hasColumn('purchases', 'payment_method')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropColumn('payment_method');
            });
        }

        Schema::dropIfExists('payment_methods');

        $this->createSalesViews();
    }

    public function down(): void
    {
        $this->dropSalesViews();

        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('code', 50)->nullable();
                $table->string('type', 50)->default('other');
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
                $table->index(['company_id', 'is_default']);
            });
        }

        if (! Schema::hasColumn('invoices', 'payment_method_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('payment_method_id')->nullable()->after('sales_channel_id')->constrained('payment_methods')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('purchases', 'payment_method')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->string('payment_method', 50)->nullable()->after('payment_status');
            });
        }

        if (! Schema::hasColumn('purchases', 'payment_method_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->foreignId('payment_method_id')->nullable()->after('payment_method')->constrained('payment_methods')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('payments', 'payment_method_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('payment_method_id')->nullable()->after('customer_id')->constrained('payment_methods')->nullOnDelete();
            });
        }

        $this->restoreLegacyPaymentsIndexes();

        $this->createSalesViews();
    }

    private function dropLegacyPaymentsIndexes(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        try {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex('payments_invoice_id_payment_method_id_index');
            });
        } catch (\Throwable) {
            // The legacy composite index may already be absent in some environments.
        }
    }

    private function restoreLegacyPaymentsIndexes(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'payment_method_id')) {
            return;
        }

        try {
            Schema::table('payments', function (Blueprint $table) {
                $table->index(['invoice_id', 'payment_method_id']);
            });
        } catch (\Throwable) {
            // Ignore duplicate recreation attempts when rolling back on databases with the index already present.
        }
    }

    private function createSalesViews(): void
    {
        DB::statement('CREATE VIEW sales AS
            SELECT
                invoices.id,
                invoices.invoice_number AS sale_number,
                invoices.customer_id,
                invoices.company_id,
                invoices.employee_id,
                invoices.branch_id,
                invoices.sales_channel_id AS channel_id,
                invoices.invoice_date AS sale_date,
                invoices.subtotal,
                invoices.tax_amount,
                invoices.total AS total_amount,
                invoices.paid_amount,
                invoices.balance_due,
                invoices.status,
                invoices.payment_status,
                invoices.created_at,
                invoices.updated_at
            FROM invoices');

        DB::statement('CREATE VIEW sales_items AS
            SELECT
                invoice_items.id,
                invoice_items.invoice_id AS sale_id,
                invoice_items.product_id,
                invoice_items.category_id,
                invoice_items.description,
                invoice_items.quantity,
                invoice_items.unit_price AS price,
                invoice_items.tax_rate,
                invoice_items.tax_amount,
                invoice_items.total AS total_amount,
                invoice_items.created_at,
                invoice_items.updated_at
            FROM invoice_items');
    }

    private function dropSalesViews(): void
    {
        DB::statement('DROP VIEW IF EXISTS sales_items');
        DB::statement('DROP VIEW IF EXISTS sales');
    }
};
