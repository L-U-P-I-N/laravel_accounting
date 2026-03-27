<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Http\Controllers\AuthController;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class RegistrationAndCustomerShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_saves_company_city_from_selected_country_cities(): void
    {
        $request = Request::create('/register', 'POST', [
            'email' => 'register-city@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'company_name' => 'Register City Co',
            'country_code' => 'AE',
            'city' => 'دبي',
        ]);

        app(AuthController::class)->register($request);

        $company = Company::firstOrFail();

        $this->assertSame('AE', $company->country_code);
        $this->assertSame('دبي', $company->city);
        $this->assertSame('AED', $company->currency);
    }

    public function test_customer_show_returns_customer_and_invoices_data(): void
    {
        $company = Company::create([
            'name' => 'Customer Show Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = User::create([
            'name' => 'Owner User',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'customer-show@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'language' => 'ar',
            'is_active' => true,
            'must_change_password' => false,
            'company_id' => $company->id,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Customer Show',
            'city' => 'جدة',
            'country' => 'المملكة العربية السعودية',
            'credit_limit' => 3000,
            'is_active' => true,
        ]);

        Invoice::create([
            'invoice_number' => 'INV-2001',
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'invoice_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 0,
            'balance_due' => 115,
            'status' => 'sent',
            'payment_status' => 'pending',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        $request = Request::create('/customers/' . $customer->id, 'GET');
        $request->setUserResolver(fn () => $user);

        $view = app(AccountingPageController::class)->showCustomer($request, $customer);
        $data = $view->getData();

        $this->assertSame('customer_show', $view->name());
        $this->assertSame($customer->id, $data['customer']->id);
        $this->assertCount(1, $data['customer']->invoices);
        $this->assertSame(115.0, (float) $data['customer']->balance);
    }

    public function test_customers_page_filters_by_city_and_status(): void
    {
        $company = Company::create([
            'name' => 'Customer Filter Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = User::create([
            'name' => 'Owner User',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'customers-filter@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'language' => 'ar',
            'is_active' => true,
            'must_change_password' => false,
            'company_id' => $company->id,
        ]);

        $matchingCustomer = Customer::create([
            'company_id' => $company->id,
            'name' => 'عميل جدة النشط',
            'city' => 'جدة',
            'country' => 'المملكة العربية السعودية',
            'is_active' => true,
        ]);

        Customer::create([
            'company_id' => $company->id,
            'name' => 'عميل جدة غير النشط',
            'city' => 'جدة',
            'country' => 'المملكة العربية السعودية',
            'is_active' => false,
        ]);

        Customer::create([
            'company_id' => $company->id,
            'name' => 'عميل الرياض النشط',
            'city' => 'الرياض',
            'country' => 'المملكة العربية السعودية',
            'is_active' => true,
        ]);

        $request = Request::create('/customers?city=جدة&status=active', 'GET', [
            'city' => 'جدة',
            'status' => 'active',
        ]);
        $request->setUserResolver(fn () => $user);

        $view = app(AccountingPageController::class)->customers($request);
        $data = $view->getData();

        $this->assertSame('customers', $view->name());
        $this->assertCount(1, $data['customers']);
        $this->assertSame($matchingCustomer->id, $data['customers']->first()->id);
        $this->assertSame('جدة', $data['customerFilters']['city']);
        $this->assertSame('active', $data['customerFilters']['status']);
    }

    public function test_invoice_views_render_customer_location_with_unified_labels(): void
    {
        $company = Company::create([
            'name' => 'Invoice Location Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = User::create([
            'name' => 'Owner User',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'invoice-location@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'language' => 'ar',
            'is_active' => true,
            'must_change_password' => false,
            'company_id' => $company->id,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'عميل الفاتورة',
            'city' => 'جدة',
            'country' => 'المملكة العربية السعودية',
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-LOC-1001',
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'invoice_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 0,
            'balance_due' => 115,
            'status' => 'sent',
            'payment_status' => 'pending',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        $request = Request::create('/invoices/' . $invoice->id, 'GET');
        $request->setUserResolver(fn () => $user);
        $this->be($user);
        view()->share('errors', new ViewErrorBag());

        $invoiceView = app(AccountingPageController::class)->invoiceShow($request, $invoice);
        $invoicePdfView = app(AccountingPageController::class)->invoicePdf($request, $invoice);

        $renderedInvoiceView = $invoiceView->render();
        $renderedInvoicePdfView = $invoicePdfView->render();

        $this->assertStringContainsString('المدينة: جدة', $renderedInvoiceView);
        $this->assertStringContainsString('الدولة: المملكة العربية السعودية', $renderedInvoiceView);
        $this->assertStringContainsString('المدينة: جدة', $renderedInvoicePdfView);
        $this->assertStringContainsString('الدولة: المملكة العربية السعودية', $renderedInvoicePdfView);
    }
}
