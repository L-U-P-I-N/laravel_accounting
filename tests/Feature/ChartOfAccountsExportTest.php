<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ChartOfAccountsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_of_accounts_print_view_and_export_use_requested_columns(): void
    {
        $company = Company::create([
            'name' => 'Print Co',
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

        $parent = Account::create([
            'company_id' => $company->id,
            'code' => '11',
            'name' => 'أصول متداولة',
            'name_ar' => 'أصول متداولة',
            'account_type' => 'asset',
            'display_account_type' => 'الأصول المتداولة',
            'is_active' => true,
            'is_system' => true,
            'balance' => 0,
        ]);

        Account::create([
            'company_id' => $company->id,
            'code' => '110101',
            'name' => 'النقدية في الخزينة',
            'name_ar' => 'النقدية في الخزينة',
            'account_type' => 'asset',
            'display_account_type' => 'النقدية ومافي حكمها',
            'parent_id' => $parent->id,
            'allows_direct_transactions' => true,
            'description' => 'النقدية في الخزينة',
            'is_active' => true,
            'is_system' => true,
            'balance' => 90,
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        $printRequest = Request::create('/chart-of-accounts/print', 'GET');
        $printRequest->setUserResolver(fn () => $user);
        $printView = app(AccountingPageController::class)->printChartOfAccounts($printRequest);
        $printContent = $printView->render();

        $this->assertStringContainsString('رقم تعريفي للحساب الأصلي', $printContent);
        $this->assertStringContainsString('110101', $printContent);
        $this->assertStringContainsString('11 - أصول متداولة', $printContent);
        $this->assertStringContainsString('يمكن الدفع والتحصيل بهذا الحساب', $printContent);

        $exportRequest = Request::create('/chart-of-accounts/export', 'GET');
        $exportRequest->setUserResolver(fn () => $user);
        $response = app(AccountingPageController::class)->exportChartOfAccounts($exportRequest);

        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('الرمز,"اسم الحساب",النوع,الوصف,"رقم تعريفي للحساب الأصلي"', $csv);
        $this->assertStringContainsString('110101', $csv);
        $this->assertStringContainsString('النقدية في الخزينة', $csv);
        $this->assertStringContainsString('11 - أصول متداولة', $csv);
    }
}
