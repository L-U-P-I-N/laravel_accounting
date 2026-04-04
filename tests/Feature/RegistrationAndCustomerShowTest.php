<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Http\Controllers\AuthController;
use App\Models\Account;
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

    public function test_register_creates_the_restructured_default_chart_of_accounts(): void
    {
        $request = Request::create('/register', 'POST', [
            'email' => 'register-accounts@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'first_name' => 'Owner',
            'last_name' => 'Accounts',
            'company_name' => 'Register Accounts Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
        ]);

        app(AuthController::class)->register($request);

        $company = Company::query()->where('name', 'Register Accounts Co')->firstOrFail();
        $accounts = Account::query()->where('company_id', $company->id)->get()->keyBy('code');

        $this->assertArrayHasKey('1', $accounts);
        $this->assertArrayHasKey('11', $accounts);
        $this->assertArrayHasKey('1101', $accounts);
        $this->assertArrayHasKey('110101', $accounts);
        $this->assertArrayHasKey('1102', $accounts);
        $this->assertArrayHasKey('110201', $accounts);
        $this->assertArrayHasKey('1103', $accounts);
        $this->assertArrayHasKey('2', $accounts);
        $this->assertArrayHasKey('21', $accounts);
        $this->assertArrayHasKey('2101', $accounts);
        $this->assertArrayHasKey('2105', $accounts);
        $this->assertArrayHasKey('3', $accounts);
        $this->assertArrayHasKey('31', $accounts);
        $this->assertArrayHasKey('3101', $accounts);
        $this->assertArrayHasKey('4', $accounts);
        $this->assertArrayHasKey('41', $accounts);
        $this->assertArrayHasKey('4101', $accounts);
        $this->assertArrayHasKey('5', $accounts);
        $this->assertArrayHasKey('52', $accounts);
        $this->assertArrayHasKey('5204', $accounts);

        $this->assertSame('الأصول', $accounts['1']->name_ar);
        $this->assertSame($accounts['1']->id, $accounts['11']->parent_id);
        $this->assertSame($accounts['11']->id, $accounts['1101']->parent_id);
        $this->assertSame($accounts['1101']->id, $accounts['110101']->parent_id);
        $this->assertSame($accounts['11']->id, $accounts['1102']->parent_id);
        $this->assertSame($accounts['1102']->id, $accounts['110201']->parent_id);
        $this->assertSame($accounts['2']->id, $accounts['21']->parent_id);
        $this->assertSame($accounts['21']->id, $accounts['2101']->parent_id);
        $this->assertSame($accounts['3']->id, $accounts['31']->parent_id);
        $this->assertSame($accounts['4']->id, $accounts['41']->parent_id);
        $this->assertSame($accounts['5']->id, $accounts['52']->parent_id);
        $this->assertSame($accounts['52']->id, $accounts['5204']->parent_id);
        $this->assertArrayNotHasKey('1000', $accounts);
        $this->assertArrayNotHasKey('2000', $accounts);
        $this->assertArrayNotHasKey('4000', $accounts);
        $this->assertArrayNotHasKey('6000', $accounts);
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

    public function test_customer_show_sorts_invoices_by_date_ascending_when_requested(): void
    {
        $company = Company::create([
            'name' => 'Customer Sort Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = User::create([
            'name' => 'Owner User',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'customer-sort@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'language' => 'ar',
            'is_active' => true,
            'must_change_password' => false,
            'company_id' => $company->id,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Customer Sort',
            'is_active' => true,
        ]);

        Invoice::create([
            'invoice_number' => 'INV-2',
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'invoice_date' => '2026-03-20',
            'due_date' => '2026-04-20',
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

        Invoice::create([
            'invoice_number' => 'INV-1',
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'invoice_date' => '2026-03-05',
            'due_date' => '2026-04-05',
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

        $request = Request::create('/customers/' . $customer->id, 'GET', [
            'sort_direction' => 'asc',
        ]);
        $request->setUserResolver(fn () => $user);
        view()->share('errors', new ViewErrorBag());

        $view = app(AccountingPageController::class)->showCustomer($request, $customer);
        $data = $view->getData();

        $this->assertSame('INV-1', $data['customer']->invoices->first()->invoice_number);
        $this->assertSame('asc', $data['sortDirection']);
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
