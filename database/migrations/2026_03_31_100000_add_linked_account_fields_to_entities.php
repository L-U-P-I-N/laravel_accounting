<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'account_id')) {
                $table->foreignId('account_id')
                    ->nullable()
                    ->after('tax_number')
                    ->constrained('accounts')
                    ->nullOnDelete();
            }
        });

        Schema::table('suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('suppliers', 'account_id')) {
                $table->foreignId('account_id')
                    ->nullable()
                    ->after('tax_number')
                    ->constrained('accounts')
                    ->nullOnDelete();
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'revenue_account_id')) {
                $table->foreignId('revenue_account_id')
                    ->nullable()
                    ->after('category_id')
                    ->constrained('accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'inventory_account_id')) {
                $table->foreignId('inventory_account_id')
                    ->nullable()
                    ->after('revenue_account_id')
                    ->constrained('accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('products', 'cogs_account_id')) {
                $table->foreignId('cogs_account_id')
                    ->nullable()
                    ->after('inventory_account_id')
                    ->constrained('accounts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['revenue_account_id', 'inventory_account_id', 'cogs_account_id'] as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'account_id')) {
                $table->dropConstrainedForeignId('account_id');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'account_id')) {
                $table->dropConstrainedForeignId('account_id');
            }
        });
    }
};
