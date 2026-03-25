<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id')->index();
            }

            if (! Schema::hasColumn('products', 'name')) {
                $table->string('name')->nullable()->after('company_id');
            }

            if (! Schema::hasColumn('products', 'name_ar')) {
                $table->string('name_ar')->nullable()->after('name');
            }

            if (! Schema::hasColumn('products', 'code')) {
                $table->string('code', 50)->nullable()->after('name_ar');
            }

            if (! Schema::hasColumn('products', 'type')) {
                $table->string('type', 20)->default('product')->after('code');
            }

            if (! Schema::hasColumn('products', 'unit')) {
                $table->string('unit', 50)->default('وحدة')->after('type');
            }

            if (! Schema::hasColumn('products', 'cost_price')) {
                $table->decimal('cost_price', 12, 2)->default(0)->after('unit');
            }

            if (! Schema::hasColumn('products', 'sell_price')) {
                $table->decimal('sell_price', 12, 2)->default(0)->after('cost_price');
            }

            if (! Schema::hasColumn('products', 'stock_quantity')) {
                $table->decimal('stock_quantity', 12, 2)->default(0)->after('sell_price');
            }

            if (! Schema::hasColumn('products', 'min_stock')) {
                $table->decimal('min_stock', 12, 2)->default(0)->after('stock_quantity');
            }

            if (! Schema::hasColumn('products', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->default(0)->after('min_stock');
            }

            if (! Schema::hasColumn('products', 'description')) {
                $table->text('description')->nullable()->after('tax_rate');
            }

            if (! Schema::hasColumn('products', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = [
                'company_id',
                'name',
                'name_ar',
                'code',
                'type',
                'unit',
                'cost_price',
                'sell_price',
                'stock_quantity',
                'min_stock',
                'tax_rate',
                'description',
                'is_active',
            ];

            $existingColumns = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn('products', $column)));

            if ($existingColumns !== []) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
