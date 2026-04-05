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

        $this->createSalesViewWithoutPaymentMethod();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS sales');

        $this->createSalesViewWithoutPaymentMethod();
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
