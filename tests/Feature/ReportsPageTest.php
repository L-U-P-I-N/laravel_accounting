<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
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
}
