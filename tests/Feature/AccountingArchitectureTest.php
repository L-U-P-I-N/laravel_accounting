<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AccountingService;
use App\Support\ChartOfAccountsSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AccountingArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_synchronization_creates_linked_accounts_for_entities(): void
    {
        $company = Company::create([
            'name' => 'Sync Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'عميل تجريبي',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'مورد تجريبي',
            'is_active' => true,
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'name' => 'منتج تجريبي',
            'type' => 'product',
            'unit' => 'وحدة',
            'cost_price' => 25,
            'sell_price' => 50,
            'stock_quantity' => 10,
            'min_stock' => 1,
            'tax_rate' => 15,
            'is_active' => true,
        ]);

        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);

        $customer->refresh();
        $supplier->refresh();
        $product->refresh();

        $this->assertNotNull($customer->account_id);
        $this->assertNotNull($supplier->account_id);
        $this->assertNotNull($product->revenue_account_id);
        $this->assertNotNull($product->inventory_account_id);
        $this->assertNotNull($product->cogs_account_id);

        $this->assertDatabaseHas('accounts', ['company_id' => $company->id, 'code' => '1', 'account_type' => 'asset']);
        $this->assertDatabaseHas('accounts', ['company_id' => $company->id, 'code' => '2', 'account_type' => 'liability']);
        $this->assertDatabaseHas('accounts', ['company_id' => $company->id, 'code' => '3', 'account_type' => 'equity']);
        $this->assertDatabaseHas('accounts', ['company_id' => $company->id, 'code' => '4', 'account_type' => 'revenue']);
        $this->assertDatabaseHas('accounts', ['company_id' => $company->id, 'code' => '5', 'account_type' => 'expense']);
        $this->assertDatabaseHas('accounts', ['company_id' => $company->id, 'code' => '31', 'account_type' => 'equity']);
        $this->assertDatabaseHas('accounts', ['company_id' => $company->id, 'code' => '34', 'account_type' => 'equity']);
        $this->assertDatabaseHas('accounts', ['company_id' => $company->id, 'code' => '41', 'account_type' => 'revenue']);
        $this->assertDatabaseHas('accounts', ['company_id' => $company->id, 'code' => '52', 'account_type' => 'expense']);
    }

    public function test_company_synchronization_creates_complete_base_chart_structure(): void
    {
        $company = Company::create([
            'name' => 'Base Chart Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);

        $codes = Account::query()
            ->where('company_id', $company->id)
            ->pluck('code')
            ->all();

        $this->assertContains('11', $codes);
        $this->assertContains('1101', $codes);
        $this->assertContains('110101', $codes);
        $this->assertContains('1102', $codes);
        $this->assertContains('110201', $codes);
        $this->assertContains('1103', $codes);
        $this->assertContains('1106', $codes);
        $this->assertContains('12', $codes);
        $this->assertContains('1201', $codes);
        $this->assertContains('21', $codes);
        $this->assertContains('22', $codes);
        $this->assertContains('2105', $codes);
        $this->assertContains('31', $codes);
        $this->assertContains('34', $codes);
        $this->assertContains('41', $codes);
        $this->assertContains('42', $codes);
        $this->assertContains('51', $codes);
        $this->assertContains('52', $codes);
        $this->assertContains('53', $codes);
        $this->assertContains('5', $codes);
        $this->assertContains('5215', $codes);
        $this->assertContains('5304', $codes);
    }

    public function test_invoice_entry_splits_between_cash_customer_revenue_and_cogs(): void
    {
        $company = Company::create([
            'name' => 'Invoice Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = $this->createOwner($company, 'invoice-owner@example.com');
        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'عميل فاتورة',
            'is_active' => true,
        ]);
        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'صنف فاتورة',
            'type' => 'product',
            'unit' => 'وحدة',
            'cost_price' => 40,
            'sell_price' => 100,
            'stock_quantity' => 10,
            'min_stock' => 1,
            'tax_rate' => 15,
            'is_active' => true,
        ]);
        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);
        $paymentAccount = Account::query()->where('company_id', $company->id)->where('code', '110201')->firstOrFail();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-1001',
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'payment_account_id' => $paymentAccount->id,
            'invoice_date' => '2026-03-31',
            'due_date' => '2026-04-30',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 60,
            'balance_due' => 55,
            'status' => 'sent',
            'payment_status' => 'partial',
            'currency' => 'SAR',
            'exchange_rate' => 1,
            'user_id' => $user->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'description' => 'صنف فاتورة',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 15,
            'tax_amount' => 15,
            'total' => 115,
        ]);

        $entry = app(AccountingService::class)->syncInvoiceEntry($invoice->fresh(['items.product', 'customer.account', 'paymentAccount']), $user);
        $lines = $entry->lines->keyBy('account.code');

        $this->assertSame(60.0, (float) $lines['110201']->debit);
        $this->assertSame(55.0, (float) $lines['1103-C' . $customer->id]->debit);
        $this->assertSame(100.0, (float) $lines['4101-P' . $product->id]->credit);
        $this->assertSame(15.0, (float) $lines['2105']->credit);
        $this->assertSame(40.0, (float) $lines['5101-P' . $product->id]->debit);
        $this->assertSame(40.0, (float) $lines['1106-P' . $product->id]->credit);
    }

    public function test_purchase_entry_splits_between_cash_supplier_inventory_and_vat(): void
    {
        $company = Company::create([
            'name' => 'Purchase Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = $this->createOwner($company, 'purchase-owner@example.com');
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'مورد شراء',
            'is_active' => true,
        ]);
        $product = Product::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'name' => 'صنف شراء',
            'type' => 'product',
            'unit' => 'وحدة',
            'cost_price' => 50,
            'sell_price' => 100,
            'stock_quantity' => 0,
            'min_stock' => 1,
            'tax_rate' => 15,
            'is_active' => true,
        ]);
        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);
        $paymentAccount = Account::query()->where('company_id', $company->id)->where('code', '110201')->firstOrFail();

        $purchase = Purchase::create([
            'purchase_number' => 'PUR-1001',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-03-31',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 30,
            'balance_due' => 85,
            'status' => 'approved',
            'payment_status' => 'partial',
            'payment_account_id' => $paymentAccount->id,
            'payment_date' => '2026-03-31',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'description' => 'صنف شراء',
            'quantity' => 2,
            'unit_price' => 50,
            'tax_rate' => 15,
            'tax_amount' => 15,
            'total' => 115,
        ]);

        $entry = app(AccountingService::class)->syncPurchaseEntry($purchase->fresh(['items.product', 'supplier.account', 'paymentAccount']), $user);
        $lines = $entry->lines->keyBy('account.code');

        $this->assertSame(100.0, (float) $lines['1106-P' . $product->id]->debit);
        $this->assertSame(15.0, (float) $lines['2105']->debit);
        $this->assertSame(30.0, (float) $lines['110201']->credit);
        $this->assertSame(85.0, (float) $lines['2101-S' . $supplier->id]->credit);
    }

    public function test_supplier_payment_entry_uses_supplier_account_and_default_settlement_account(): void
    {
        $company = Company::create([
            'name' => 'Supplier Payment Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = $this->createOwner($company, 'supplier-payment-owner@example.com');
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'مورد دفعة',
            'is_active' => true,
        ]);

        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);

        $entry = app(AccountingService::class)->createSupplierPaymentEntry($supplier->fresh(), 75, $user, 'PAY-1001');
        $lines = $entry->lines->keyBy('account.code');

        $this->assertSame(75.0, (float) $lines['2101-S' . $supplier->id]->debit);
        $this->assertSame(75.0, (float) $lines['110201']->credit);
    }

    public function test_resync_company_accounting_action_rebuilds_linked_accounts_and_journal_entries(): void
    {
        $company = Company::create([
            'name' => 'Resync Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = $this->createOwner($company, 'resync-owner@example.com');
        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'عميل مزامنة',
            'is_active' => true,
        ]);
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'مورد مزامنة',
            'is_active' => true,
        ]);
        $product = Product::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'name' => 'منتج مزامنة',
            'type' => 'product',
            'unit' => 'وحدة',
            'cost_price' => 30,
            'sell_price' => 90,
            'stock_quantity' => 20,
            'min_stock' => 1,
            'tax_rate' => 15,
            'is_active' => true,
        ]);
        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);
        $paymentAccount = Account::query()->where('company_id', $company->id)->where('code', '110201')->firstOrFail();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-RSYNC-1',
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'payment_account_id' => $paymentAccount->id,
            'invoice_date' => '2026-03-31',
            'subtotal' => 90,
            'tax_amount' => 13.5,
            'total' => 103.5,
            'paid_amount' => 40,
            'balance_due' => 63.5,
            'status' => 'sent',
            'payment_status' => 'partial',
            'currency' => 'SAR',
            'exchange_rate' => 1,
            'user_id' => $user->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'description' => 'بند مزامنة',
            'quantity' => 1,
            'unit_price' => 90,
            'tax_rate' => 15,
            'tax_amount' => 13.5,
            'total' => 103.5,
        ]);

        $purchase = Purchase::create([
            'purchase_number' => 'PUR-RSYNC-1',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-03-31',
            'subtotal' => 60,
            'tax_amount' => 9,
            'total' => 69,
            'paid_amount' => 20,
            'balance_due' => 49,
            'status' => 'approved',
            'payment_status' => 'partial',
            'payment_account_id' => $paymentAccount->id,
            'payment_date' => '2026-03-31',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'description' => 'شراء مزامنة',
            'quantity' => 2,
            'unit_price' => 30,
            'tax_rate' => 15,
            'tax_amount' => 9,
            'total' => 69,
        ]);

        $expenseAccount = app(ChartOfAccountsSynchronizer::class)->ensureBaseAccounts($company)->get('5204');
        $paymentAccount = app(ChartOfAccountsSynchronizer::class)->ensureBaseAccounts($company)->get('110201');

        Expense::create([
            'expense_number' => 'EXP-RSYNC-1',
            'company_id' => $company->id,
            'expense_account_id' => $expenseAccount->id,
            'payment_account_id' => $paymentAccount->id,
            'created_by' => $user->id,
            'expense_date' => '2026-03-31',
            'name' => 'إيجار شهر',
            'reference' => 'EXP-REF-1',
            'amount' => 100,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 100,
            'status' => 'posted',
        ]);

        $request = Request::create('/chart-of-accounts/resync', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = app(AccountingPageController::class)->resyncCompanyAccounting($request);

        $customer->refresh();
        $supplier->refresh();
        $product->refresh();

        $this->assertStringEndsWith('/chart-of-accounts', $response->getTargetUrl());
        $this->assertNotNull($customer->account_id);
        $this->assertNotNull($supplier->account_id);
        $this->assertNotNull($product->revenue_account_id);
        $this->assertSame(3, JournalEntry::where('company_id', $company->id)->count());
        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $company->id,
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $company->id,
            'source_type' => Purchase::class,
            'source_id' => $purchase->id,
        ]);
        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $company->id,
            'source_type' => Expense::class,
            'entry_type' => 'expense',
        ]);
    }

    private function createOwner(Company $company, string $email): User
    {
        return User::create([
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
    }
}
