<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class SupplierLocationInheritanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_supplier_uses_company_country_and_selected_city(): void
    {
        $company = Company::create([
            'name' => 'Location Test Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
        ]);

        $user = User::create([
            'name' => 'Owner User',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'supplier-location@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'language' => 'ar',
            'is_active' => true,
            'must_change_password' => false,
            'company_id' => $company->id,
        ]);

        $request = Request::create('/suppliers', 'POST', [
            'name' => 'Supplier A',
            'name_ar' => 'المورد أ',
            'email' => 'supplier-a@example.com',
            'phone' => '0500000000',
            'city' => 'الرياض',
            'tax_number' => '1234567890',
            'credit_limit' => 5000,
            'is_active' => '1',
        ]);
        $request->setUserResolver(fn () => $user);

        app(AccountingPageController::class)->storeSupplier($request);

        $supplier = Supplier::firstOrFail();

        $this->assertSame('الرياض', $supplier->city);
        $this->assertSame('المملكة العربية السعودية', $supplier->country);
        $this->assertSame($company->id, $supplier->company_id);
    }

    public function test_supplier_show_renders_unified_location_labels(): void
    {
        $company = Company::create([
            'name' => 'Supplier Show Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = User::create([
            'name' => 'Owner User',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'supplier-show@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'language' => 'ar',
            'is_active' => true,
            'must_change_password' => false,
            'company_id' => $company->id,
        ]);

        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'Supplier Show',
            'city' => 'جدة',
            'country' => 'المملكة العربية السعودية',
            'is_active' => true,
            'credit_limit' => 1500,
        ]);

        $request = Request::create('/suppliers/' . $supplier->id, 'GET');
        $request->setUserResolver(fn () => $user);
        $this->be($user);
        view()->share('errors', new ViewErrorBag());

        $view = app(AccountingPageController::class)->showSupplier($request, $supplier);
        $rendered = $view->render();

        $this->assertSame('supplier_show', $view->name());
        $this->assertStringContainsString('المدينة: جدة', $rendered);
        $this->assertStringContainsString('الدولة: المملكة العربية السعودية', $rendered);
    }

    public function test_purchase_print_renders_unified_company_and_supplier_location_labels(): void
    {
        $company = Company::create([
            'name' => 'Supplier Print Co',
            'country_code' => 'SA',
            'city' => 'الرياض',
            'currency' => 'SAR',
        ]);

        $user = User::create([
            'name' => 'Owner User',
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'supplier-print@example.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'language' => 'ar',
            'is_active' => true,
            'must_change_password' => false,
            'company_id' => $company->id,
        ]);

        $supplier = Supplier::create([
            'company_id' => $company->id,
            'name' => 'Supplier Print',
            'city' => 'جدة',
            'country' => 'المملكة العربية السعودية',
            'is_active' => true,
        ]);

        $purchase = Purchase::create([
            'purchase_number' => 'PO-1001',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-03-27',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 0,
            'balance_due' => 115,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $request = Request::create('/purchases/' . $purchase->id . '/print', 'GET');
        $request->setUserResolver(fn () => $user);

        $view = app(AccountingPageController::class)->purchasePrint($request, $purchase);
        $rendered = $view->render();

        $this->assertSame('purchase_print', $view->name());
        $this->assertStringContainsString('المدينة: الرياض', $rendered);
        $this->assertStringContainsString('الدولة: المملكة العربية السعودية', $rendered);
        $this->assertStringContainsString('المدينة: جدة', $rendered);
    }
}
