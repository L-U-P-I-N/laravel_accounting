<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_builds_product_sales_report_for_a_specific_product(): void
    {
        $company = Company::create([
            'name' => 'Reports Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
        ]);

        $user = User::factory()->create([
            'first_name' => 'Owner',
            'last_name' => 'Reports',
            'name' => 'Owner Reports',
            'role' => 'owner',
            'company_id' => $company->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Customer One',
            'email' => 'customer@example.com',
            'credit_limit' => 0,
            'is_active' => true,
        ]);

        $selectedProduct = Product::create([
            'company_id' => $company->id,
            'name' => 'Laptop Pro',
            'name_ar' => 'Laptop Pro',
            'code' => 'LP-1',
            'type' => 'product',
            'unit' => 'piece',
            'cost_price' => 1000,
            'sell_price' => 1500,
            'stock_quantity' => 10,
            'min_stock' => 1,
            'tax_rate' => 15,
            'is_active' => true,
        ]);

        $otherProduct = Product::create([
            'company_id' => $company->id,
            'name' => 'Mouse Basic',
            'name_ar' => 'Mouse Basic',
            'code' => 'MB-1',
            'type' => 'product',
            'unit' => 'piece',
            'cost_price' => 20,
            'sell_price' => 50,
            'stock_quantity' => 25,
            'min_stock' => 1,
            'tax_rate' => 15,
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-1001',
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'invoice_date' => '2026-03-20',
            'due_date' => '2026-03-25',
            'subtotal' => 1550,
            'tax_amount' => 0,
            'total' => 1550,
            'paid_amount' => 0,
            'balance_due' => 1550,
            'status' => 'sent',
            'payment_status' => 'pending',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $selectedProduct->id,
            'description' => 'Laptop Pro',
            'quantity' => 1,
            'unit_price' => 1500,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 1500,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $otherProduct->id,
            'description' => 'Mouse Basic',
            'quantity' => 1,
            'unit_price' => 50,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 50,
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        $request = Request::create('/reports', 'GET', [
            'print' => 1,
            'report_type' => 'product_sales',
            'period' => 'custom',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'product_id' => $selectedProduct->id,
        ]);
        $request->setUserResolver(fn () => $user);

        $view = app(AccountingPageController::class)->reports($request);
        $data = $view->getData();

        $this->assertSame('product_sales', $data['selectedReportType']);
        $this->assertSame('تقرير منتج محدد', $data['report']['title']);
        $this->assertCount(1, $data['reportRows']);
        $this->assertSame('Laptop Pro', $data['reportRows']->first()['label']);
        $this->assertSame(1500.0, $data['reportRows']->first()['value']);
    }

    public function test_reports_page_builds_tax_summary_report(): void
    {
        $company = Company::create([
            'name' => 'Tax Reports Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
        ]);

        $user = User::factory()->create([
            'first_name' => 'Owner',
            'last_name' => 'Tax',
            'name' => 'Owner Tax',
            'role' => 'owner',
            'company_id' => $company->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Customer Tax',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'Supplier Tax',
            'is_active' => true,
        ]);

        $expenseAccount = Account::create([
            'company_id' => $company->id,
            'code' => '6100',
            'name' => 'Office Expense',
            'name_ar' => 'مصروف مكتبي',
            'account_type' => 'expense',
            'is_active' => true,
            'is_system' => false,
        ]);

        $paymentAccount = Account::create([
            'company_id' => $company->id,
            'code' => '1105',
            'name' => 'Cashbox',
            'name_ar' => 'الصندوق',
            'account_type' => 'asset',
            'is_active' => true,
            'is_system' => false,
        ]);

        Invoice::create([
            'invoice_number' => 'INV-TAX-1',
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'invoice_date' => '2026-03-20',
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

        Purchase::create([
            'purchase_number' => 'PO-TAX-1',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-03-21',
            'subtotal' => 80,
            'tax_amount' => 12,
            'total' => 92,
            'paid_amount' => 0,
            'balance_due' => 92,
            'status' => 'approved',
            'payment_status' => 'pending',
        ]);

        Expense::create([
            'expense_number' => 'EXP-TAX-1',
            'company_id' => $company->id,
            'expense_account_id' => $expenseAccount->id,
            'payment_account_id' => $paymentAccount->id,
            'created_by' => $user->id,
            'expense_date' => '2026-03-22',
            'name' => 'Stationery',
            'amount' => 20,
            'tax_rate' => 15,
            'tax_amount' => 3,
            'total' => 23,
            'status' => 'posted',
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        $request = Request::create('/reports', 'GET', [
            'print' => 1,
            'report_type' => 'tax_summary',
            'period' => 'custom',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]);
        $request->setUserResolver(fn () => $user);

        $view = app(AccountingPageController::class)->reports($request);
        $data = $view->getData();

        $this->assertSame('tax_summary', $data['selectedReportType']);
        $this->assertSame('تقرير الضرائب', $data['report']['title']);
        $this->assertCount(4, $data['reportRows']);
        $this->assertSame(15.0, (float) $data['report']['highlights'][0]['value']);
        $this->assertSame(15.0, (float) $data['report']['highlights'][1]['value']);
        $this->assertSame(0.0, (float) $data['report']['highlights'][2]['value']);
    }

    public function test_interactive_report_print_uses_same_payload_as_screen_view(): void
    {
        $company = Company::create([
            'name' => 'Print Match Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
        ]);

        $user = User::factory()->create([
            'first_name' => 'Owner',
            'last_name' => 'Print',
            'name' => 'Owner Print',
            'role' => 'owner',
            'company_id' => $company->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'فرع الرياض',
            'code' => 'RUH-01',
            'is_default' => true,
        ]);

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Customer Print',
            'is_active' => true,
        ]);

        $product = Product::create([
            'company_id' => $company->id,
            'name' => 'Desk Pro',
            'name_ar' => 'Desk Pro',
            'code' => 'DP-1',
            'type' => 'product',
            'unit' => 'piece',
            'cost_price' => 200,
            'sell_price' => 500,
            'stock_quantity' => 10,
            'min_stock' => 1,
            'tax_rate' => 15,
            'is_active' => true,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-PRINT-1',
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_date' => '2026-03-20',
            'due_date' => '2026-03-25',
            'subtotal' => 1000,
            'tax_amount' => 150,
            'total' => 1150,
            'paid_amount' => 0,
            'balance_due' => 1150,
            'status' => 'sent',
            'payment_status' => 'pending',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'description' => 'Desk Pro',
            'quantity' => 2,
            'unit_price' => 500,
            'tax_rate' => 15,
            'tax_amount' => 150,
            'total' => 1150,
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        $screenRequest = Request::create('/reports/view/sales_by_location', 'GET', [
            'period' => 'custom',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]);
        $screenRequest->setUserResolver(fn () => $user);

        $printRequest = Request::create('/reports/view/sales_by_location', 'GET', [
            'period' => 'custom',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'print' => 1,
        ]);
        $printRequest->setUserResolver(fn () => $user);

        $controller = app(AccountingPageController::class);
        $screenView = $controller->reportShow($screenRequest, 'sales_by_location');
        $printView = $controller->reportShow($printRequest, 'sales_by_location');

        $screenData = $screenView->getData();
        $printData = $printView->getData();

        $this->assertSame('reports_show', $screenView->name());
        $this->assertSame('reports_show_print', $printView->name());
        $this->assertSame($screenData['reportPayload']['columns'], $printData['reportPayload']['columns']);
        $this->assertSame($screenData['reportPayload']['rows']->toArray(), $printData['reportPayload']['rows']->toArray());
        $this->assertSame('فرع الرياض', $printData['reportPayload']['rows']->first()['location']);
    }
}
