<?php

use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('movement_type', 50);
                $table->string('direction', 10);
                $table->string('source_type', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('reference_number', 100)->nullable();
                $table->date('movement_date');
                $table->decimal('quantity', 15, 2);
                $table->decimal('unit_cost', 15, 2)->nullable();
                $table->decimal('total_cost', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'movement_date']);
                $table->index(['product_id', 'movement_date']);
                $table->index(['source_type', 'source_id']);
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'purchase_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('purchase_id')->nullable()->after('invoice_id')->constrained('purchases')->nullOnDelete();
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'supplier_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('supplier_id')->nullable()->after('customer_id')->constrained('suppliers')->nullOnDelete();
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'journal_entry_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('journal_entry_id')->nullable()->after('customer_id')->constrained('journal_entries')->nullOnDelete();
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'payment_direction')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('payment_direction', 10)->default('in')->after('amount');
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'payment_category')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('payment_category', 50)->default('invoice_receipt')->after('payment_direction');
                $table->index(['company_id', 'payment_category']);
            });
        }

        $this->normalizeExistingPayments();
        $this->backfillInvoiceInventoryMovements();
        $this->backfillPurchasePayments();
        $this->backfillSupplierPayments();
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'payment_category')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex(['company_id', 'payment_category']);
                $table->dropColumn('payment_category');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'payment_direction')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('payment_direction');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'journal_entry_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('journal_entry_id');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'supplier_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('supplier_id');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'purchase_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('purchase_id');
            });
        }

        Schema::dropIfExists('inventory_movements');
    }

    private function normalizeExistingPayments(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        DB::table('payments')
            ->whereNull('payment_direction')
            ->orWhere('payment_direction', '')
            ->update(['payment_direction' => 'in']);

        DB::table('payments')
            ->whereNull('payment_category')
            ->orWhere('payment_category', '')
            ->update(['payment_category' => 'invoice_receipt']);
    }

    private function backfillInvoiceInventoryMovements(): void
    {
        if (! Schema::hasTable('inventory_movements')) {
            return;
        }

        $timestamp = now();
        $sourceType = App\Models\Invoice::class;

        $rows = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->join('products as p', 'p.id', '=', 'ii.product_id')
            ->leftJoin('inventory_movements as im', function ($join) use ($sourceType) {
                $join->on('im.source_id', '=', 'i.id')
                    ->where('im.source_type', '=', $sourceType);
            })
            ->where('i.status', '!=', 'draft')
            ->where('p.type', '=', 'product')
            ->where('ii.quantity', '>', 0)
            ->whereNull('im.id')
            ->select([
                'i.id as invoice_id',
                'i.company_id',
                'i.invoice_number',
                'i.invoice_date',
                'ii.product_id',
                'ii.quantity',
                'p.cost_price',
            ])
            ->orderBy('i.id')
            ->get();

        foreach ($rows as $row) {
            $unitCost = round((float) $row->cost_price, 2);
            $quantity = round((float) $row->quantity, 2);

            DB::table('inventory_movements')->insert([
                'company_id' => $row->company_id,
                'product_id' => $row->product_id,
                'movement_type' => 'invoice_issue',
                'direction' => 'out',
                'source_type' => $sourceType,
                'source_id' => $row->invoice_id,
                'reference_number' => $row->invoice_number,
                'movement_date' => $row->invoice_date,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => round($quantity * $unitCost, 2),
                'notes' => 'حركة مخزون مرحّلة من فاتورة مبيعات قائمة.',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    private function backfillPurchasePayments(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        $timestamp = now();

        $rows = DB::table('purchases as p')
            ->leftJoin('payments as pay', function ($join) {
                $join->on('pay.purchase_id', '=', 'p.id')
                    ->where('pay.payment_category', '=', 'purchase_payment');
            })
            ->where('p.paid_amount', '>', 0)
            ->whereNull('pay.id')
            ->select([
                'p.id',
                'p.company_id',
                'p.supplier_id',
                'p.purchase_number',
                'p.purchase_date',
                'p.payment_date',
                'p.paid_amount',
            ])
            ->get();

        foreach ($rows as $row) {
            DB::table('payments')->insert([
                'company_id' => $row->company_id,
                'invoice_id' => null,
                'purchase_id' => $row->id,
                'customer_id' => null,
                'supplier_id' => $row->supplier_id,
                'journal_entry_id' => null,
                'amount' => $row->paid_amount,
                'payment_direction' => 'out',
                'payment_category' => 'purchase_payment',
                'payment_date' => $row->payment_date ?: $row->purchase_date,
                'reference' => 'AUTO-' . $row->purchase_number,
                'notes' => 'دفعة مرحّلة تلقائياً من المشتريات القائمة.',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    private function backfillSupplierPayments(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        $timestamp = now();
        $sourceType = Supplier::class . ':payment';

        $rows = DB::table('journal_entries as je')
            ->leftJoin('payments as p', 'p.journal_entry_id', '=', 'je.id')
            ->where('je.source_type', $sourceType)
            ->whereNull('p.id')
            ->select([
                'je.id',
                'je.company_id',
                'je.source_id as supplier_id',
                'je.entry_date',
                'je.reference',
                'je.description',
                'je.total_credit',
            ])
            ->get();

        foreach ($rows as $row) {
            DB::table('payments')->insert([
                'company_id' => $row->company_id,
                'invoice_id' => null,
                'purchase_id' => null,
                'customer_id' => null,
                'supplier_id' => $row->supplier_id,
                'journal_entry_id' => $row->id,
                'amount' => $row->total_credit,
                'payment_direction' => 'out',
                'payment_category' => 'supplier_payment',
                'payment_date' => $row->entry_date,
                'reference' => $row->reference,
                'notes' => $row->description,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }
};
