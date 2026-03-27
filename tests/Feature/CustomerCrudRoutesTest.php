<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->get(route('customers'))
            ->assertOk()
            ->assertSee('العملاء');

        $this->post(route('customers.store'), [
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
        ])
            ->assertRedirect(route('customers'));

        $customer = Customer::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame('Customer One', $customer->name);
        $this->assertSame('الرياض', $customer->city);
        $this->assertSame('السعودية', $customer->country);
        $this->assertNotNull($customer->code);

        $this->put(route('customers.update', $customer), [
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
        ])
            ->assertRedirect(route('customers'));

        $customer->refresh();

        $this->assertSame('Customer Updated', $customer->name);
        $this->assertSame('customer.updated@example.com', $customer->email);
        $this->assertFalse($customer->is_active);

        $this->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers'));

        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);
    }
}
