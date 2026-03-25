<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('role', 20)->default('user'); // admin, accountant, user, viewer
            $table->string('language', 5)->default('ar');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('company_id')->nullable();
            $table->dateTime('last_login')->nullable();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
            $table->index(['company_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'email']);
            $table->dropColumn([
                'first_name', 
                'last_name', 
                'role', 
                'language', 
                'is_active', 
                'company_id', 
                'last_login'
            ]);
        });
    }
};
