<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropSalesView();

        if (! Schema::hasColumn('invoices', 'payment_account_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('payment_account_id')->nullable()->after('sales_channel_id')->constrained('accounts')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('purchases', 'payment_account_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->foreignId('payment_account_id')->nullable()->after('payment_date')->constrained('accounts')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('payments', 'payment_account_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('payment_account_id')->nullable()->after('journal_entry_id')->constrained('accounts')->nullOnDelete();
            });
        }

        $this->createSalesView();
    }

    public function down(): void
    {
        $this->dropSalesView();

        if (Schema::hasColumn('payments', 'payment_account_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_account_id');
            });
        }

        if (Schema::hasColumn('purchases', 'payment_account_id')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_account_id');
            });
        }

        if (Schema::hasColumn('invoices', 'payment_account_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_account_id');
            });
        }

        $this->createSalesView();
    }

    private function dropSalesView(): void
    {
        DB::statement('DROP VIEW IF EXISTS sales');
    }

    private function createSalesView(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

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
    }
};
