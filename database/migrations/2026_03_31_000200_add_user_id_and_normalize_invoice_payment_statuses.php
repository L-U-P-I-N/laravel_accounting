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
        $this->repairInvoicesTableName();

        if (! Schema::hasColumn('invoices', 'user_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('employee_id')->constrained('users')->nullOnDelete();
            });
        }

        DB::table('invoices')->where('payment_status', 'pending')->update(['payment_status' => 'deferred']);
        DB::table('invoices')->where('payment_status', 'paid')->update(['payment_status' => 'full']);

        $this->createSalesViews();
    }

    public function down(): void
    {
        $this->dropSalesViews();
        $this->repairInvoicesTableName();

        DB::table('invoices')->where('payment_status', 'deferred')->update(['payment_status' => 'pending']);
        DB::table('invoices')->where('payment_status', 'full')->update(['payment_status' => 'paid']);

        if (Schema::hasColumn('invoices', 'user_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        $this->createSalesViews();
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

    private function repairInvoicesTableName(): void
    {
        if (! Schema::hasTable('invoices') && Schema::hasTable('__temp__invoices')) {
            DB::statement('ALTER TABLE "__temp__invoices" RENAME TO "invoices"');
        }
    }
};
