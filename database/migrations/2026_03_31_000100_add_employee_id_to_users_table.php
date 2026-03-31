<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'employee_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('employee_id')->nullable()->after('company_id')->constrained('employees')->nullOnDelete();
                $table->unique('employee_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'employee_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['employee_id']);
                $table->dropConstrainedForeignId('employee_id');
            });
        }
    }
};
