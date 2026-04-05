<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ChartOfAccountsSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class InventoryAndPaymentSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_sent_invoice_creates_payment_and_inventory_movement(): void
    {
        $company = Company::create([
            'name' => 'Invoice Sync Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = $this->createOwner($company, 'invoice-sync@example.com');
        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'عميل اختبار',
            'is_active' => true,
        ]);
        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'منتج اختبار',
            'type' => 'product',
            'unit' => 'وحدة',
            'cost_price' => 10,
            'sell_price' => 25,
            'stock_quantity' => 5,
            'min_stock' => 1,
            'tax_rate' => 15,
            'is_active' => true,
        ]);
        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);
        $paymentAccount = $this->paymentAccount($company->id);

        $request = Request::create('/invoices', 'POST', [
            'customer_id' => $customer->id,
            'invoice_date' => '2026-04-03',
            'due_date' => '2026-05-03',
            'status' => 'sent',
            'payment_status' => 'full',
            'payment_account_id' => $paymentAccount->id,
            'item_product_id' => [$product->id],
            'item_description' => ['منتج اختبار'],
            'item_quantity' => [2],
            'item_price' => [25],
            'item_tax_rate' => [15],
        ]);
        $request->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storeInvoice($request);

        $invoice = Invoice::query()->firstOrFail();
        $payment = Payment::query()->where('invoice_id', $invoice->id)->first();
        $movement = InventoryMovement::query()
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->first();

        $this->assertNotNull($payment);
        $this->assertSame('invoice_receipt', $payment->payment_category);
        $this->assertSame('in', $payment->payment_direction);
        $this->assertSame(57.5, (float) $payment->amount);
        $this->assertNotNull($payment->journal_entry_id);

        $this->assertNotNull($movement);
        $this->assertSame('out', $movement->direction);
        $this->assertSame(2.0, (float) $movement->quantity);
        $this->assertSame(3.0, (float) $product->fresh()->stock_quantity);
    }

    public function test_approve_purchase_applies_stock_and_creates_inventory_movement(): void
    {
        $company = Company::create([
            'name' => 'Purchase Sync Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = $this->createOwner($company, 'purchase-sync@example.com');
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'مورد اختبار',
            'is_active' => true,
        ]);
        $product = Product::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'name' => 'منتج مخزون',
            'type' => 'product',
            'unit' => 'وحدة',
            'cost_price' => 20,
            'sell_price' => 35,
            'stock_quantity' => 1,
            'min_stock' => 1,
            'tax_rate' => 15,
            'is_active' => true,
        ]);

        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);

        $purchase = Purchase::create([
            'purchase_number' => 'PUR-2026-0001',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-04-03',
            'subtotal' => 60,
            'tax_amount' => 9,
            'total' => 69,
            'paid_amount' => 0,
            'balance_due' => 69,
            'status' => 'pending',
            'payment_status' => 'pending',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'description' => 'استلام مخزون',
            'quantity' => 3,
            'unit_price' => 20,
            'tax_rate' => 15,
            'tax_amount' => 9,
            'total' => 69,
        ]);

        $request = Request::create('/purchases/' . $purchase->id . '/approve', 'PATCH');
        $request->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->approvePurchase($request, $purchase);

        $purchase->refresh();
        $movement = InventoryMovement::query()
            ->where('source_type', Purchase::class)
            ->where('source_id', $purchase->id)
            ->first();

        $this->assertSame('approved', $purchase->status);
        $this->assertSame(4.0, (float) $product->fresh()->stock_quantity);
        $this->assertNotNull($movement);
        $this->assertSame('in', $movement->direction);
        $this->assertSame(3.0, (float) $movement->quantity);
    }

    public function test_supplier_payment_is_recorded_in_payments_table(): void
    {
        $company = Company::create([
            'name' => 'Supplier Payment Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = $this->createOwner($company, 'supplier-sync@example.com');
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'مورد سداد',
            'is_active' => true,
        ]);
        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);
        $paymentAccount = $this->paymentAccount($company->id);

        Purchase::create([
            'purchase_number' => 'PUR-OPEN-1',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-04-01',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 0,
            'balance_due' => 115,
            'status' => 'approved',
            'payment_status' => 'pending',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        $request = Request::create('/suppliers/' . $supplier->id . '/payments', 'POST', [
            'payment_amount' => 75,
            'payment_account_id' => $paymentAccount->id,
            'payment_reference' => 'SUP-PMT-TEST',
        ]);
        $request->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storeSupplierPayment($request, $supplier);

        $payment = Payment::query()->where('supplier_id', $supplier->id)->where('payment_category', 'supplier_payment')->first();

        $this->assertNotNull($payment);
        $this->assertSame('out', $payment->payment_direction);
        $this->assertSame(75.0, (float) $payment->amount);
        $this->assertSame('SUP-PMT-TEST', $payment->reference);
        $this->assertNotNull($payment->journal_entry_id);
    }

    public function test_purchase_partial_payment_is_recorded_without_overwriting_history(): void
    {
        $company = Company::create([
            'name' => 'Purchase Payment Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = $this->createOwner($company, 'purchase-payment@example.com');
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'مورد دفعات',
            'is_active' => true,
        ]);
        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);
        $paymentAccount = $this->paymentAccount($company->id);

        $purchase = Purchase::create([
            'purchase_number' => 'PUR-2026-0100',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-04-03',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 20,
            'balance_due' => 95,
            'status' => 'approved',
            'payment_status' => 'partial',
            'payment_account_id' => $paymentAccount->id,
            'payment_date' => '2026-04-03',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        app(\App\Support\PaymentSyncService::class)->syncPurchasePayment($purchase->fresh(), null);

        $request = Request::create('/purchases/' . $purchase->id . '/payments', 'POST', [
            'payment_amount' => 30,
            'payment_date' => '2026-04-04',
            'payment_account_id' => $paymentAccount->id,
            'payment_reference' => 'PUR-PMT-TEST',
        ]);
        $request->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storePurchasePayment($request, $purchase);

        $purchase->refresh();
        $payments = Payment::query()
            ->where('purchase_id', $purchase->id)
            ->where('payment_category', 'purchase_payment')
            ->orderBy('id')
            ->get();

        $this->assertSame(50.0, (float) $purchase->paid_amount);
        $this->assertSame(65.0, (float) $purchase->balance_due);
        $this->assertSame('partial', $purchase->payment_status);
        $this->assertCount(2, $payments);
        $this->assertSame('PUR-2026-0100', $payments->first()->reference);
        $this->assertSame('PUR-PMT-TEST', $payments->last()->reference);
        $this->assertSame(30.0, (float) $payments->last()->amount);
        $this->assertNotNull($payments->last()->journal_entry_id);
    }

    public function test_operations_activity_report_returns_payments_and_inventory_movements(): void
    {
        $company = Company::create([
            'name' => 'Operations Activity Co',
            'country_code' => 'SA',
            'city' => 'جدة',
            'currency' => 'SAR',
        ]);

        $user = $this->createOwner($company, 'operations-activity@example.com');
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'مورد تقرير',
            'is_active' => true,
        ]);
        $product = Product::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'name' => 'منتج حركة',
            'type' => 'product',
            'unit' => 'وحدة',
            'cost_price' => 12,
            'sell_price' => 20,
            'stock_quantity' => 10,
            'min_stock' => 1,
            'tax_rate' => 15,
            'is_active' => true,
        ]);
        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);
        $paymentAccount = $this->paymentAccount($company->id);

        $purchase = Purchase::create([
            'purchase_number' => 'PUR-2026-0200',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-04-05',
            'subtotal' => 40,
            'tax_amount' => 6,
            'total' => 46,
            'paid_amount' => 20,
            'balance_due' => 26,
            'status' => 'approved',
            'payment_status' => 'partial',
            'payment_account_id' => $paymentAccount->id,
            'payment_date' => '2026-04-05',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        Payment::create([
            'company_id' => $company->id,
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplier->id,
            'payment_account_id' => $paymentAccount->id,
            'amount' => 20,
            'payment_direction' => 'out',
            'payment_category' => 'purchase_payment',
            'payment_date' => '2026-04-05',
            'reference' => 'PUR-PMT-2026-0001',
            'notes' => 'دفعة اختبار',
        ]);

        InventoryMovement::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'movement_type' => 'purchase_receipt',
            'direction' => 'in',
            'source_type' => Purchase::class,
            'source_id' => $purchase->id,
            'reference_number' => 'PUR-2026-0200',
            'movement_date' => '2026-04-05',
            'quantity' => 2,
            'unit_cost' => 12,
            'total_cost' => 24,
            'notes' => 'حركة اختبار',
        ]);

        $request = Request::create('/reports/operations-activity', 'GET', [
            'group_by' => 'day',
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]);
        $request->setUserResolver(fn () => $user);

        $view = app(AccountingPageController::class)->operationsActivityReport($request);
        $data = $view->getData();

        $this->assertSame('day', $data['groupBy']);
        $this->assertCount(1, $data['payments']);
        $this->assertCount(1, $data['inventoryMovements']);
        $this->assertSame(20.0, (float) $data['paymentSummary']['outgoing']);
        $this->assertSame(2.0, (float) $data['movementSummary']['incoming_quantity']);
    }

    public function test_operations_activity_report_sorts_by_date_ascending_when_requested(): void
    {
        $company = Company::create([
            'name' => 'Ops Sort Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = $this->createOwner($company, 'ops-sort@example.com');
        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'مورد ترتيب',
            'is_active' => true,
        ]);
        $product = Product::create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'name' => 'منتج ترتيب',
            'type' => 'product',
            'unit' => 'وحدة',
            'cost_price' => 12,
            'sell_price' => 20,
            'stock_quantity' => 10,
            'min_stock' => 1,
            'tax_rate' => 15,
            'is_active' => true,
        ]);
        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);
        $paymentAccount = $this->paymentAccount($company->id);
        $purchase = Purchase::create([
            'purchase_number' => 'PUR-SORT',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-04-02',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 20,
            'balance_due' => 95,
            'status' => 'approved',
            'payment_status' => 'partial',
            'payment_account_id' => $paymentAccount->id,
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        Payment::create([
            'company_id' => $company->id,
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplier->id,
            'payment_account_id' => $paymentAccount->id,
            'amount' => 30,
            'payment_direction' => 'out',
            'payment_category' => 'purchase_payment',
            'payment_date' => '2026-04-10',
            'reference' => 'PAY-NEW',
        ]);

        Payment::create([
            'company_id' => $company->id,
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplier->id,
            'payment_account_id' => $paymentAccount->id,
            'amount' => 10,
            'payment_direction' => 'out',
            'payment_category' => 'purchase_payment',
            'payment_date' => '2026-04-01',
            'reference' => 'PAY-OLD',
        ]);

        InventoryMovement::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'movement_type' => 'purchase_receipt',
            'direction' => 'in',
            'source_type' => Purchase::class,
            'source_id' => $purchase->id,
            'reference_number' => 'MOV-NEW',
            'movement_date' => '2026-04-12',
            'quantity' => 2,
            'unit_cost' => 12,
            'total_cost' => 24,
        ]);

        InventoryMovement::create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'movement_type' => 'purchase_receipt',
            'direction' => 'in',
            'source_type' => Purchase::class,
            'source_id' => $purchase->id,
            'reference_number' => 'MOV-OLD',
            'movement_date' => '2026-04-03',
            'quantity' => 1,
            'unit_cost' => 12,
            'total_cost' => 12,
        ]);

        $request = Request::create('/reports/operations-activity', 'GET', [
            'group_by' => 'day',
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'sort_direction' => 'asc',
        ]);
        $request->setUserResolver(fn () => $user);

        $view = app(AccountingPageController::class)->operationsActivityReport($request);
        $data = $view->getData();

        $this->assertSame('PAY-OLD', $data['payments']->first()->reference);
        $this->assertSame('MOV-OLD', $data['inventoryMovements']->first()->reference_number);
        $this->assertSame('asc', $data['sortDirection']);
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

    private function paymentAccount(int $companyId): Account
    {
        return Account::query()
            ->where('company_id', $companyId)
            ->where('code', '110201')
            ->firstOrFail();
    }
}
