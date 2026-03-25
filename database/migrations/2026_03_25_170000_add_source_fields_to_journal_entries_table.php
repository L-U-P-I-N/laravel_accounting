<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->string('entry_origin', 20)->default('manual')->after('entry_type');
            $table->string('source_type', 50)->nullable()->after('reference');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');

            $table->index(['company_id', 'entry_origin']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'entry_origin']);
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropColumn(['entry_origin', 'source_type', 'source_id']);
        });
    }
};
