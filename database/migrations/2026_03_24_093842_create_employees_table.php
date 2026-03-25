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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 50);
            $table->unsignedBigInteger('company_id');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 120)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->string('position', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->decimal('salary', 15, 2)->default(0);
            $table->string('status', 20)->default('active'); // active, terminated, on_leave
            $table->string('employment_type', 20)->default('full_time'); // full_time, part_time, contract
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->index(['company_id', 'employee_number']);
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
