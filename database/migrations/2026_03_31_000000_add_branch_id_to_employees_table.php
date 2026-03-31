<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'branch_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'branch_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
