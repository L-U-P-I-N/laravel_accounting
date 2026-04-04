<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class CustomerCrudRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_routes_support_index_create_update_and_delete(): void
    {
        $company = Company::create([
            'name' => 'Customers Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
            'city' => 'الرياض',
        ]);

        $user = User::factory()->create([
            'first_name' => 'Route',
            'last_name' => 'Owner',
            'name' => 'Route Owner',
            'role' => 'owner',
            'company_id' => $company->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        $controller = app(AccountingPageController::class);

        $indexRequest = Request::create('/customers', 'GET');
        $indexRequest->setUserResolver(fn () => $user);

        $indexView = $controller->customers($indexRequest);
        $indexData = $indexView->getData();

        $this->assertSame('customers', $indexView->getName());
        $this->assertSame($company->id, $indexData['company']->id);
        $this->assertCount(0, $indexData['customers']);

        $storeRequest = Request::create('/customers', 'POST', [
            'name' => 'Customer One',
            'name_ar' => 'العميل الأول',
            'code' => '',
            'email' => 'customer.one@example.com',
            'phone' => '123456',
            'mobile' => '0500000000',
            'address' => 'Riyadh',
            'city' => 'الرياض',
            'tax_number' => 'TAX-1',
            'credit_limit' => '2500',
            'is_active' => '1',
            'customer_modal' => 'create',
        ]);
        $storeRequest->setUserResolver(fn () => $user);

        $storeResponse = $controller->storeCustomer($storeRequest);

        $this->assertSame(route('customers'), $storeResponse->getTargetUrl());

        $customer = Customer::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame('Customer One', $customer->name);
        $this->assertSame('الرياض', $customer->city);
        $this->assertSame('المملكة العربية السعودية', $customer->country);
        $this->assertNotNull($customer->code);

        $updateRequest = Request::create('/customers/' . $customer->id, 'PUT', [
            'name' => 'Customer Updated',
            'name_ar' => 'العميل المحدّث',
            'code' => $customer->code,
            'email' => 'customer.updated@example.com',
            'phone' => '654321',
            'mobile' => '0555555555',
            'address' => 'Updated Address',
            'city' => 'الرياض',
            'tax_number' => 'TAX-2',
            'credit_limit' => '3500',
            'is_active' => '0',
            'customer_modal' => 'edit-' . $customer->id,
        ]);
        $updateRequest->setUserResolver(fn () => $user);

        $updateResponse = $controller->updateCustomer($updateRequest, $customer);

        $this->assertSame(route('customers'), $updateResponse->getTargetUrl());

        $customer->refresh();

        $this->assertSame('Customer Updated', $customer->name);
        $this->assertSame('customer.updated@example.com', $customer->email);
        $this->assertFalse($customer->is_active);

        $deleteRequest = Request::create('/customers/' . $customer->id, 'DELETE');
        $deleteRequest->setUserResolver(fn () => $user);

        $deleteResponse = $controller->destroyCustomer($deleteRequest, $customer);

        $this->assertSame(route('customers'), $deleteResponse->getTargetUrl());

        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);
    }
}
