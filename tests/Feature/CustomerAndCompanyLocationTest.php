<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CustomerAndCompanyLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_and_update_customer_use_company_country_and_selected_city(): void
    {
        [$company, $user] = $this->companyContext();

        $storeRequest = Request::create('/customers', 'POST', [
            'name' => 'Customer A',
            'name_ar' => 'العميل أ',
            'email' => 'customer-a@example.com',
            'phone' => '0550000000',
            'mobile' => '0550000001',
            'city' => 'الرياض',
            'tax_number' => '1234567890',
            'credit_limit' => 1200,
            'is_active' => '1',
        ]);
        $storeRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storeCustomer($storeRequest);

        $customer = Customer::firstOrFail();

        $this->assertSame('الرياض', $customer->city);
        $this->assertSame('المملكة العربية السعودية', $customer->country);
        $this->assertSame($company->id, $customer->company_id);

        $updateRequest = Request::create('/customers/' . $customer->id, 'PUT', [
            'name' => 'Customer A Updated',
            'name_ar' => 'العميل أ المعدل',
            'email' => 'customer-a@example.com',
            'phone' => '0550000000',
            'mobile' => '0550000002',
            'city' => 'جدة',
            'tax_number' => '1234567890',
            'credit_limit' => 1500,
            'is_active' => '1',
        ]);
        $updateRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->updateCustomer($updateRequest, $customer);

        $customer->refresh();

        $this->assertSame('Customer A Updated', $customer->name);
        $this->assertSame('جدة', $customer->city);
        $this->assertSame('المملكة العربية السعودية', $customer->country);
        $this->assertSame(1500.0, (float) $customer->credit_limit);
    }

    public function test_update_company_settings_changes_country_city_and_currency_together(): void
    {
        [$company, $user] = $this->companyContext();

        $request = Request::create('/settings/company', 'PUT', [
            'name' => 'Updated Company',
            'tax_number' => '3000000000',
            'email' => 'company@example.com',
            'phone' => '0110000000',
            'address' => 'Main Street',
            'country_code' => 'AE',
            'city' => 'دبي',
            'currency' => 'AED',
        ]);
        $request->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->updateCompanySettings($request);

        $company->refresh();

        $this->assertSame('Updated Company', $company->name);
        $this->assertSame('AE', $company->country_code);
        $this->assertSame('دبي', $company->city);
        $this->assertSame('AED', $company->currency);
    }

    private function companyContext(): array
    {
        $company = Company::create([
            'name' => 'Location Company',
            'country_code' => 'SA',
            'currency' => 'SAR',
        ]);

        $user = User::create([
            'name' => 'Owner User',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'customer-company-location@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'language' => 'ar',
            'is_active' => true,
            'must_change_password' => false,
            'company_id' => $company->id,
        ]);

        return [$company, $user];
    }
}
