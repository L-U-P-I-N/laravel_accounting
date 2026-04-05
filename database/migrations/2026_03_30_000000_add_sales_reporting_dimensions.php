<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('code', 50)->nullable();
                $table->string('city', 100)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
                $table->index(['company_id', 'is_default']);
            });
        }

        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('slug', 150)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
                $table->index(['company_id', 'is_default']);
            });
        }

        if (! Schema::hasTable('sales_channels')) {
            Schema::create('sales_channels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('code', 50)->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->unique(['company_id', 'name']);
                $table->index(['company_id', 'is_default']);
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->decimal('amount', 15, 2)->default(0);
                $table->date('payment_date');
                $table->string('reference', 100)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'payment_date']);
            });
        }

        if (! Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('category_id')->nullable()->after('supplier_id')->constrained('categories')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('invoices', 'employee_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('employee_id')->nullable()->after('customer_id')->constrained('employees')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('invoices', 'branch_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('invoices', 'sales_channel_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('sales_channel_id')->nullable()->after('branch_id')->constrained('sales_channels')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('invoice_items', 'category_id')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->foreignId('category_id')->nullable()->after('product_id')->constrained('categories')->nullOnDelete();
            });
        }

        $this->backfillSalesReportingData();
        $this->createSalesViews();
    }

    public function down(): void
    {
        $this->dropSalesViews();

        if (Schema::hasColumn('invoice_items', 'category_id')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('category_id');
            });
        }

        if (Schema::hasColumn('invoices', 'sales_channel_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('sales_channel_id');
            });
        }

        if (Schema::hasColumn('invoices', 'branch_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }

        if (Schema::hasColumn('invoices', 'employee_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('employee_id');
            });
        }

        if (Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropConstrainedForeignId('category_id');
            });
        }

        Schema::dropIfExists('payments');
        Schema::dropIfExists('sales_channels');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('branches');
    }

    private function backfillSalesReportingData(): void
    {
        $timestamp = now();
        $companies = DB::table('companies')->select('id', 'city')->get();

        foreach ($companies as $company) {
            $branchId = $this->ensureRecord('branches', [
                'company_id' => $company->id,
                'name' => 'الفرع الرئيسي',
            ], [
                'code' => 'MAIN',
                'city' => $company->city,
                'is_default' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $channelId = $this->ensureRecord('sales_channels', [
                'company_id' => $company->id,
                'name' => 'المبيعات المباشرة',
            ], [
                'code' => 'DIRECT',
                'is_default' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $defaultCategoryId = $this->ensureRecord('categories', [
                'company_id' => $company->id,
                'name' => 'غير مصنف',
            ], [
                'slug' => 'uncategorized',
                'is_default' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $this->backfillProductCategories((int) $company->id, (int) $defaultCategoryId, $timestamp);
            $this->backfillInvoiceItemsCategories((int) $company->id, (int) $defaultCategoryId);
            $this->backfillInvoiceDimensions((int) $company->id, (int) $branchId, (int) $channelId);
            $this->backfillPayments((int) $company->id, $timestamp);
        }
    }

    private function ensureRecord(string $table, array $identity, array $values): int
    {
        $existingId = DB::table($table)->where($identity)->value('id');

        if ($existingId) {
            DB::table($table)->where('id', $existingId)->update(array_merge($values, ['updated_at' => now()]));

            return (int) $existingId;
        }

        return (int) DB::table($table)->insertGetId(array_merge($identity, $values));
    }

    private function backfillProductCategories(int $companyId, int $defaultCategoryId, $timestamp): void
    {
        $products = DB::table('products')
            ->where('company_id', $companyId)
            ->select('id', 'type', 'category_id')
            ->get();

        foreach ($products as $product) {
            if ($product->category_id) {
                continue;
            }

            $categoryId = $defaultCategoryId;
            $type = trim((string) ($product->type ?? ''));

            if ($type !== '') {
                $categoryId = $this->ensureRecord('categories', [
                    'company_id' => $companyId,
                    'name' => $this->categoryNameForType($type),
                ], [
                    'slug' => $this->slugForCategory($type),
                    'is_default' => false,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            DB::table('products')->where('id', $product->id)->update(['category_id' => $categoryId]);
        }
    }

    private function backfillInvoiceItemsCategories(int $companyId, int $defaultCategoryId): void
    {
        $items = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->where('invoices.company_id', $companyId)
            ->whereNull('invoice_items.category_id')
            ->select('invoice_items.id', 'products.category_id as product_category_id')
            ->get();

        foreach ($items as $item) {
            DB::table('invoice_items')
                ->where('id', $item->id)
                ->update(['category_id' => $item->product_category_id ?: $defaultCategoryId]);
        }
    }

    private function backfillInvoiceDimensions(int $companyId, int $branchId, int $channelId): void
    {
        DB::table('invoices')
            ->where('company_id', $companyId)
            ->whereNull('branch_id')
            ->update(['branch_id' => $branchId]);

        DB::table('invoices')
            ->where('company_id', $companyId)
            ->whereNull('sales_channel_id')
            ->update(['sales_channel_id' => $channelId]);
    }

    private function backfillPayments(int $companyId, $timestamp): void
    {
        $invoices = DB::table('invoices')
            ->leftJoin('payments', 'payments.invoice_id', '=', 'invoices.id')
            ->where('invoices.company_id', $companyId)
            ->where('invoices.paid_amount', '>', 0)
            ->whereNull('payments.id')
            ->select('invoices.id', 'invoices.customer_id', 'invoices.invoice_number', 'invoices.invoice_date', 'invoices.paid_amount')
            ->get();

        foreach ($invoices as $invoice) {
            DB::table('payments')->insert([
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'amount' => $invoice->paid_amount,
                'payment_date' => $invoice->invoice_date,
                'reference' => 'AUTO-' . $invoice->invoice_number,
                'notes' => 'دفعة مرحّلة تلقائياً لدعم تقارير المبيعات.',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    private function categoryNameForType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'product' => 'منتجات',
            'service' => 'خدمات',
            default => $type,
        };
    }

    private function slugForCategory(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($value)));

        return trim((string) $slug, '-') ?: 'category';
    }

    private function createSalesViews(): void
    {
        $this->dropSalesViews();

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
