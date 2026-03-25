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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country_code', 5)->default('SA');
            $table->string('currency', 5)->default('SAR');
            $table->string('tax_number', 50)->nullable();
            $table->string('commercial_reg', 50)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('fiscal_year_start', 5)->default('01-01');
            
            // Subscription
            $table->string('subscription_plan', 20)->default('basic');
            $table->string('subscription_status', 20)->default('trial'); // trial, active, expired, cancelled
            $table->dateTime('subscription_start')->nullable();
            $table->dateTime('subscription_end')->nullable();
            $table->string('stripe_customer_id', 100)->nullable();
            $table->string('stripe_subscription_id', 100)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
