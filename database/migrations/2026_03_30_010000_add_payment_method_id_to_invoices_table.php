<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS sales');

        if (! Schema::hasColumn('invoices', 'payment_method_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('payment_method_id')->nullable()->after('sales_channel_id')->constrained('payment_methods')->nullOnDelete();
            });
        }

        $this->backfillInvoicePaymentMethods();
        $this->createSalesViewWithPaymentMethod();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS sales');

        if (Schema::hasColumn('invoices', 'payment_method_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_method_id');
            });
        }

        $this->createSalesViewWithoutPaymentMethod();
    }

    private function backfillInvoicePaymentMethods(): void
    {
        if (! Schema::hasTable('payment_methods')) {
            return;
        }

        $companyIds = DB::table('invoices')->distinct()->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $defaultPaymentMethodId = DB::table('payment_methods')
                ->where('company_id', $companyId)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->value('id');

            if (! $defaultPaymentMethodId) {
                continue;
            }

            DB::table('invoices')
                ->where('company_id', $companyId)
                ->whereNull('payment_method_id')
                ->update(['payment_method_id' => $defaultPaymentMethodId]);
        }
    }

    private function createSalesViewWithPaymentMethod(): void
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
                invoices.payment_method_id,
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

    private function createSalesViewWithoutPaymentMethod(): void
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
    }
};
