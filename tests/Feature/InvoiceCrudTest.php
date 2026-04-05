<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\TaxSetting;
use App\Models\User;
use App\Support\ChartOfAccountsSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_invoice_keeps_draft_without_journal_entry(): void
    {
        [$company, $user, $customer, $product] = $this->invoiceContext('draft-invoice@example.com');

        $request = Request::create('/invoices', 'POST', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'draft',
            'item_product_id' => [$product->id],
            'item_description' => ['Test product'],
            'item_quantity' => [1],
            'item_price' => [0],
            'item_tax_rate' => [0],
        ]);
        $request->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storeInvoice($request);

        $invoice = Invoice::firstOrFail();
        $this->assertSame('draft', $invoice->status);
        $this->assertSame(5.0, (float) $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_store_invoice_rejects_zero_total_when_sent(): void
    {
        [$company, $user, $customer, $product] = $this->invoiceContext('sent-zero-invoice@example.com');

        $request = Request::create('/invoices', 'POST', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'sent',
            'item_product_id' => [$product->id],
            'item_description' => ['Free product'],
            'item_quantity' => [1],
            'item_price' => [0],
            'item_tax_rate' => [0],
        ]);
        $request->setUserResolver(fn () => $user);

        $this->expectException(ValidationException::class);

        app(AccountingPageController::class)->storeInvoice($request);
    }

    public function test_store_invoice_rejects_quantity_above_available_stock(): void
    {
        [$company, $user, $customer, $product] = $this->invoiceContext('sent-over-stock@example.com');

        $request = Request::create('/invoices', 'POST', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'sent',
            'item_product_id' => [$product->id],
            'item_description' => ['Stock limited product'],
            'item_quantity' => [6],
            'item_price' => [25],
            'item_tax_rate' => [15],
        ]);
        $request->setUserResolver(fn () => $user);

        try {
            app(AccountingPageController::class)->storeInvoice($request);
            $this->fail('Expected stock validation to reject invoice creation.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'الكمية المتاحة للمنتج "Product A" هي 5.00 فقط، بينما إجمالي الكمية المطلوبة 6.00.',
            ], $exception->errors()['item_quantity.0'] ?? []);
        }

        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame(5.0, (float) $product->fresh()->stock_quantity);
    }

    public function test_send_invoice_creates_journal_entry_for_valid_draft(): void
    {
        [$company, $user, $customer, $product] = $this->invoiceContext('send-draft-invoice@example.com');

        $storeRequest = Request::create('/invoices', 'POST', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'draft',
            'item_product_id' => [$product->id],
            'item_description' => ['Paid product'],
            'item_quantity' => [2],
            'item_price' => [25],
            'item_tax_rate' => [15],
        ]);
        $storeRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storeInvoice($storeRequest);

        $invoice = Invoice::firstOrFail();

        $sendRequest = Request::create('/invoices/' . $invoice->id . '/send', 'PATCH');
        $sendRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->sendInvoice($sendRequest, $invoice);

        $invoice->refresh();
        $this->assertSame('sent', $invoice->status);
        $this->assertSame(3.0, (float) $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_send_invoice_uses_configured_output_tax_account(): void
    {
        [$company, $user, $customer, $product] = $this->invoiceContext('send-output-tax@example.com');

        $customOutputVatAccount = Account::create([
            'company_id' => $company->id,
            'code' => '2399',
            'name' => 'Custom Output VAT',
            'name_ar' => 'ضريبة مخرجات مخصصة',
            'account_type' => 'liability',
            'is_active' => true,
            'is_system' => false,
        ]);

        TaxSetting::create([
            'company_id' => $company->id,
            'tax_name' => 'VAT',
            'tax_name_ar' => 'ضريبة المخرجات',
            'tax_type' => 'output_vat',
            'rate' => 15,
            'is_default' => true,
            'account_id' => $customOutputVatAccount->id,
        ]);

        $storeRequest = Request::create('/invoices', 'POST', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'draft',
            'item_product_id' => [$product->id],
            'item_description' => ['Paid product'],
            'item_quantity' => [2],
            'item_price' => [25],
            'item_tax_rate' => [15],
        ]);
        $storeRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storeInvoice($storeRequest);

        $invoice = Invoice::firstOrFail();

        $sendRequest = Request::create('/invoices/' . $invoice->id . '/send', 'PATCH');
        $sendRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->sendInvoice($sendRequest, $invoice);

        $entry = JournalEntry::with('lines')->where('source_type', Invoice::class)->where('source_id', $invoice->id)->firstOrFail();

        $this->assertTrue($entry->lines->contains(fn ($line) => (int) $line->account_id === (int) $customOutputVatAccount->id && (float) $line->credit === 7.5));
    }

    public function test_update_sent_invoice_rebalances_stock_for_changed_items(): void
    {
        [$company, $user, $customer, $product] = $this->invoiceContext('update-sent-invoice@example.com');

        $storeRequest = Request::create('/invoices', 'POST', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'sent',
            'item_product_id' => [$product->id],
            'item_description' => ['Original product'],
            'item_quantity' => [2],
            'item_price' => [25],
            'item_tax_rate' => [15],
        ]);
        $storeRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storeInvoice($storeRequest);

        $invoice = Invoice::firstOrFail();

        $updateRequest = Request::create('/invoices/' . $invoice->id, 'PUT', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-03-28',
            'due_date' => '2026-04-28',
            'status' => 'sent',
            'item_product_id' => [$product->id],
            'item_description' => ['Updated product'],
            'item_quantity' => [1],
            'item_price' => [25],
            'item_tax_rate' => [15],
        ]);
        $updateRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->updateInvoice($updateRequest, $invoice);

        $invoice->refresh();

        $this->assertSame('sent', $invoice->status);
        $this->assertSame(4.0, (float) $product->fresh()->stock_quantity);
        $this->assertSame(1.0, (float) $invoice->items()->firstOrFail()->quantity);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
        ]);
    }

    public function test_destroy_sent_invoice_restores_stock_and_deletes_journal_entry(): void
    {
        [$company, $user, $customer, $product] = $this->invoiceContext('destroy-sent-invoice@example.com');

        $storeRequest = Request::create('/invoices', 'POST', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'sent',
            'item_product_id' => [$product->id],
            'item_description' => ['Disposable product'],
            'item_quantity' => [2],
            'item_price' => [25],
            'item_tax_rate' => [15],
        ]);
        $storeRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storeInvoice($storeRequest);

        $invoice = Invoice::firstOrFail();

        $destroyRequest = Request::create('/invoices/' . $invoice->id, 'DELETE');
        $destroyRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->destroyInvoice($destroyRequest, $invoice);

        $this->assertSame(5.0, (float) $product->fresh()->stock_quantity);
        $this->assertDatabaseMissing('invoices', [
            'id' => $invoice->id,
        ]);
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
        ]);
    }

    public function test_store_invoice_supports_partial_payment_and_optional_attachment(): void
    {
        [$company, $user, $customer, $product, $employee, $branch] = $this->invoiceContext('partial-payment-invoice@example.com');

        $request = Request::create('/invoices', 'POST', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'sent',
            'payment_status' => 'partial',
            'payment_account_id' => $this->paymentAccountId($company),
            'paid_amount' => 20,
            'item_product_id' => [$product->id],
            'item_description' => ['Partial product'],
            'item_quantity' => [2],
            'item_price' => [25],
            'item_tax_rate' => [15],
        ]);
        $request->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storeInvoice($request);

        $invoice = Invoice::firstOrFail();
        $this->assertSame('partial', $invoice->payment_status);
        $this->assertSame(20.0, (float) $invoice->paid_amount);
        $this->assertSame(37.5, (float) $invoice->balance_due);
        $this->assertSame($user->id, (int) $invoice->user_id);
        $this->assertSame($employee->id, (int) $invoice->employee_id);
        $this->assertSame($branch->id, (int) $invoice->branch_id);
        $this->assertNull($invoice->attachment_path);
    }

    public function test_store_invoice_sets_full_payment_to_invoice_total_and_stores_optional_attachment(): void
    {
        Storage::fake('public');

        [$company, $user, $customer, $product] = $this->invoiceContext('full-payment-invoice@example.com');

        $request = Request::create('/invoices', 'POST', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'sent',
            'payment_status' => 'full',
            'payment_account_id' => $this->paymentAccountId($company),
            'paid_amount' => 1,
            'item_product_id' => [$product->id],
            'item_description' => ['Paid product'],
            'item_quantity' => [2],
            'item_price' => [25],
            'item_tax_rate' => [15],
        ], [], [
            'attachment' => UploadedFile::fake()->create('invoice.pdf', 200, 'application/pdf'),
        ]);
        $request->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storeInvoice($request);

        $invoice = Invoice::firstOrFail();
        $this->assertSame('full', $invoice->payment_status);
        $this->assertSame(57.5, (float) $invoice->paid_amount);
        $this->assertSame(0.0, (float) $invoice->balance_due);
        $this->assertNotNull($invoice->attachment_path);
        Storage::disk('public')->assertExists($invoice->attachment_path);
    }

    public function test_owner_can_store_invoice_without_employee_and_uses_default_branch(): void
    {
        [$company, $user, $customer, $product, $employee, $branch] = $this->invoiceContext('owner-without-employee@example.com');

        $user->update(['employee_id' => null]);
        $employee->delete();

        $request = Request::create('/invoices', 'POST', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'sent',
            'payment_status' => 'deferred',
            'item_product_id' => [$product->id],
            'item_description' => ['Owner product'],
            'item_quantity' => [1],
            'item_price' => [25],
            'item_tax_rate' => [15],
        ]);
        $request->setUserResolver(fn () => $user->fresh());

        app(AccountingPageController::class)->storeInvoice($request);

        $invoice = Invoice::firstOrFail();
        $this->assertSame($user->id, (int) $invoice->user_id);
        $this->assertNull($invoice->employee_id);
        $this->assertSame($branch->id, (int) $invoice->branch_id);
        $this->assertSame('deferred', $invoice->payment_status);
    }

    public function test_invoices_page_sorts_by_invoice_date_ascending_when_requested(): void
    {
        $company = Company::create([
            'name' => 'Invoice List Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
        ]);

        $user = User::create([
            'name' => 'Owner User',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'invoice-list@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'language' => 'ar',
            'is_active' => true,
            'must_change_password' => false,
            'company_id' => $company->id,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Customer A',
            'is_active' => true,
        ]);

        Invoice::create([
            'invoice_number' => 'INV-OLDER',
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'invoice_date' => '2026-03-01',
            'due_date' => '2026-04-01',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 0,
            'balance_due' => 115,
            'status' => 'sent',
            'payment_status' => 'deferred',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        Invoice::create([
            'invoice_number' => 'INV-NEWER',
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'invoice_date' => '2026-03-10',
            'due_date' => '2026-04-10',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 0,
            'balance_due' => 115,
            'status' => 'sent',
            'payment_status' => 'deferred',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        $request = Request::create('/invoices', 'GET', [
            'sort_direction' => 'asc',
        ]);
        $request->setUserResolver(fn () => $user);

        $view = app(AccountingPageController::class)->invoices($request);
        $data = $view->getData();

        $this->assertSame('INV-OLDER', $data['invoices']->first()->invoice_number);
        $this->assertSame('asc', $data['sortDirection']);
    }

    private function invoiceContext(string $email): array
    {
        $company = Company::create([
            'name' => 'Invoice Test Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
        ]);

        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN',
            'city' => 'الرياض',
            'is_default' => true,
        ]);

        $employee = Employee::create([
            'employee_number' => 'EMP-0001',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'first_name' => 'Sales',
            'last_name' => 'Rep',
            'email' => 'employee-' . md5($email) . '@example.com',
            'hire_date' => '2026-01-01',
            'salary' => 5000,
            'status' => 'active',
            'employment_type' => 'full_time',
        ]);

        $user = User::create([
            'name' => 'Owner User',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => 'owner',
            'language' => 'ar',
            'is_active' => true,
            'must_change_password' => false,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Customer A',
            'is_active' => true,
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Product A',
            'type' => 'product',
            'unit' => 'pcs',
            'cost_price' => 10,
            'sell_price' => 20,
            'stock_quantity' => 5,
            'min_stock' => 0,
            'tax_rate' => 15,
            'is_active' => true,
        ]);

        return [$company, $user, $customer, $product, $employee, $branch];
    }

    private function paymentAccountId(Company $company): int
    {
        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);

        return (int) Account::query()
            ->where('company_id', $company->id)
            ->where('code', '110201')
            ->value('id');
    }
}
