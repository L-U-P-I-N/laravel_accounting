<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'company_id' => $company->id,
        ]);
    }

    private function invoiceContext(string $email): array
    {
        $company = Company::create([
            'name' => 'Invoice Test Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
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

        return [$company, $user, $customer, $product];
    }
}
