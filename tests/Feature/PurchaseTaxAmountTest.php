<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Account;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseTaxAmountTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_purchase_persists_tax_amount_for_purchase_and_items(): void
    {
        [$company, $user, $supplier, $product] = $this->purchaseContext();

        Storage::fake('public');

        $request = Request::create('/purchases', 'POST', [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'draft',
            'payment_status' => 'pending',
            'supplier_invoice_number' => 'SUP-INV-001',
            'payment_date' => '2026-03-27',
            'item_product_id' => [$product->id],
            'item_description' => ['Inventory Item'],
            'item_quantity' => [2],
            'item_price' => [100],
            'item_tax_rate' => [15],
        ]);
        $request->setUserResolver(fn () => $user);
        $request->files->set('attachment', UploadedFile::fake()->create('invoice.pdf', 150, 'application/pdf'));

        app(AccountingPageController::class)->storePurchase($request);

        $purchase = Purchase::with('items')->firstOrFail();
        $item = $purchase->items->firstOrFail();

        $this->assertSame($company->id, $purchase->company_id);
        $this->assertSame(200.0, (float) $purchase->subtotal);
        $this->assertSame(30.0, (float) $purchase->tax_amount);
        $this->assertSame(230.0, (float) $purchase->total);

        $this->assertSame(2.0, (float) $item->quantity);
        $this->assertSame(100.0, (float) $item->unit_price);
        $this->assertSame(15.0, (float) $item->tax_rate);
        $this->assertSame(30.0, (float) $item->tax_amount);
        $this->assertSame(230.0, (float) $item->total);
    }

    public function test_update_purchase_refreshes_tax_amount_for_purchase_and_items(): void
    {
        [$company, $user, $supplier, $product] = $this->purchaseContext();

        Storage::fake('public');

        $storeRequest = Request::create('/purchases', 'POST', [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'draft',
            'payment_status' => 'pending',
            'supplier_invoice_number' => 'SUP-INV-001',
            'payment_date' => '2026-03-27',
            'item_product_id' => [$product->id],
            'item_description' => ['Inventory Item'],
            'item_quantity' => [2],
            'item_price' => [100],
            'item_tax_rate' => [15],
        ]);
        $storeRequest->setUserResolver(fn () => $user);
        $storeRequest->files->set('attachment', UploadedFile::fake()->create('invoice.pdf', 150, 'application/pdf'));

        app(AccountingPageController::class)->storePurchase($storeRequest);

        $purchase = Purchase::firstOrFail();

        $updateRequest = Request::create('/purchases/' . $purchase->id, 'PUT', [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-03-28',
            'due_date' => '2026-04-30',
            'status' => 'approved',
            'payment_status' => 'pending',
            'supplier_invoice_number' => 'SUP-INV-001',
            'payment_date' => '2026-03-29',
            'item_product_id' => [$product->id],
            'item_description' => ['Inventory Item Updated'],
            'item_quantity' => [3],
            'item_price' => [90],
            'item_tax_rate' => [10],
        ]);
        $updateRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->updatePurchase($updateRequest, $purchase);

        $purchase->refresh()->load('items');
        $item = $purchase->items->firstOrFail();

        $this->assertSame($company->id, $purchase->company_id);
        $this->assertSame('approved', $purchase->status);
        $this->assertSame(270.0, (float) $purchase->subtotal);
        $this->assertSame(27.0, (float) $purchase->tax_amount);
        $this->assertSame(297.0, (float) $purchase->total);

        $this->assertSame('Inventory Item Updated', $item->description);
        $this->assertSame(3.0, (float) $item->quantity);
        $this->assertSame(90.0, (float) $item->unit_price);
        $this->assertSame(10.0, (float) $item->tax_rate);
        $this->assertSame(27.0, (float) $item->tax_amount);
        $this->assertSame(297.0, (float) $item->total);
    }

    public function test_purchase_paid_amount_is_saved_for_partial_and_full_payment(): void
    {
        [$company, $user, $supplier, $product] = $this->purchaseContext();

        Storage::fake('public');

        $storeRequest = Request::create('/purchases', 'POST', [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'draft',
            'payment_status' => 'partial',
            'payment_account_id' => $this->paymentAccountId($company),
            'paid_amount' => 50,
            'payment_date' => '2026-03-27',
            'supplier_invoice_number' => 'SUP-INV-PAID-001',
            'item_product_id' => [$product->id],
            'item_description' => ['Inventory Item'],
            'item_quantity' => [2],
            'item_price' => [100],
            'item_tax_rate' => [15],
        ]);
        $storeRequest->setUserResolver(fn () => $user);
        $storeRequest->files->set('attachment', UploadedFile::fake()->create('invoice.pdf', 150, 'application/pdf'));

        app(AccountingPageController::class)->storePurchase($storeRequest);

        $purchase = Purchase::firstOrFail();

        $this->assertSame('partial', $purchase->payment_status);
        $this->assertSame(50.0, (float) $purchase->paid_amount);
        $this->assertSame(180.0, (float) $purchase->balance_due);
        $this->assertDatabaseMissing('purchases', ['id' => $purchase->id, 'payment_account_id' => null]);
        $this->assertSame($this->paymentAccountId($company), $purchase->payment_account_id);
        $this->assertSame('2026-03-27', optional($purchase->payment_date)?->toDateString());

        $payment = Payment::query()->where('purchase_id', $purchase->id)->where('payment_category', 'purchase_payment')->first();
        $this->assertNotNull($payment);
        $this->assertSame($this->paymentAccountId($company), $payment->payment_account_id);

        $updateRequest = Request::create('/purchases/' . $purchase->id, 'PUT', [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-03-28',
            'due_date' => '2026-04-30',
            'status' => 'approved',
            'payment_status' => 'paid',
            'payment_account_id' => $this->paymentAccountId($company),
            'paid_amount' => 999,
            'payment_date' => '2026-03-28',
            'supplier_invoice_number' => 'SUP-INV-PAID-001',
            'item_product_id' => [$product->id],
            'item_description' => ['Inventory Item'],
            'item_quantity' => [2],
            'item_price' => [100],
            'item_tax_rate' => [15],
        ]);
        $updateRequest->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->updatePurchase($updateRequest, $purchase);

        $purchase->refresh();

        $this->assertSame('paid', $purchase->payment_status);
        $this->assertSame(230.0, (float) $purchase->paid_amount);
        $this->assertSame(0.0, (float) $purchase->balance_due);
        $this->assertDatabaseMissing('purchases', ['id' => $purchase->id, 'payment_account_id' => null]);
        $this->assertSame($this->paymentAccountId($company), $purchase->payment_account_id);
        $this->assertSame('2026-03-28', optional($purchase->payment_date)?->toDateString());
    }

    public function test_approved_purchase_uses_configured_input_tax_account(): void
    {
        [$company, $user, $supplier, $product] = $this->purchaseContext();

        Storage::fake('public');

        $customInputVatAccount = Account::create([
            'company_id' => $company->id,
            'code' => '2398',
            'name' => 'Custom Input VAT',
            'name_ar' => 'ضريبة مدخلات مخصصة',
            'account_type' => 'asset',
            'is_active' => true,
            'is_system' => false,
        ]);

        TaxSetting::create([
            'company_id' => $company->id,
            'tax_name' => 'Input VAT',
            'tax_name_ar' => 'ضريبة المدخلات',
            'tax_type' => 'input_vat',
            'rate' => 15,
            'is_default' => false,
            'account_id' => $customInputVatAccount->id,
        ]);

        $request = Request::create('/purchases', 'POST', [
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-03-27',
            'due_date' => '2026-04-27',
            'status' => 'approved',
            'payment_status' => 'pending',
            'supplier_invoice_number' => 'SUP-INV-001',
            'payment_date' => '2026-03-27',
            'item_product_id' => [$product->id],
            'item_description' => ['Inventory Item'],
            'item_quantity' => [2],
            'item_price' => [100],
            'item_tax_rate' => [15],
        ]);
        $request->setUserResolver(fn () => $user);
        $request->files->set('attachment', UploadedFile::fake()->create('invoice.pdf', 150, 'application/pdf'));

        app(AccountingPageController::class)->storePurchase($request);

        $purchase = Purchase::firstOrFail();
        $entry = \App\Models\JournalEntry::with('lines')->where('source_type', Purchase::class)->where('source_id', $purchase->id)->firstOrFail();

        $this->assertTrue($entry->lines->contains(fn ($line) => (int) $line->account_id === (int) $customInputVatAccount->id && (float) $line->debit === 30.0));
    }

    private function purchaseContext(): array
    {
        $company = Company::create([
            'name' => 'Purchase Test Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
        ]);

        $user = User::create([
            'name' => 'Owner User',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'purchase-tax@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'language' => 'ar',
            'is_active' => true,
            'must_change_password' => false,
            'company_id' => $company->id,
        ]);

        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'Supplier One',
            'email' => 'supplier@example.com',
            'is_active' => true,
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'name' => 'Purchase Product',
            'type' => 'product',
            'unit' => 'pcs',
            'cost_price' => 80,
            'sell_price' => 100,
            'stock_quantity' => 10,
            'min_stock' => 0,
            'tax_rate' => 15,
            'is_active' => true,
        ]);

        app(\App\Support\ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);

        return [$company, $user, $supplier, $product];
    }

    private function paymentAccountId(Company $company): int
    {
        return (int) Account::query()
            ->where('company_id', $company->id)
            ->where('code', '110201')
            ->value('id');
    }
}
