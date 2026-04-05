<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ChartOfAccountsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_of_accounts_filters_by_search_type_and_balance_range_with_parent_context(): void
    {
        $company = Company::create([
            'name' => 'Test Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
        ]);

        $user = User::factory()->create([
            'first_name' => 'Owner',
            'last_name' => 'User',
            'name' => 'Owner User',
            'role' => 'owner',
            'company_id' => $company->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $expenseRoot = Account::create([
            'company_id' => $company->id,
            'code' => '6000',
            'name' => 'Expenses',
            'name_ar' => 'المصروفات',
            'account_type' => 'expense',
            'is_active' => true,
            'is_system' => true,
            'balance' => 0,
        ]);

        Account::create([
            'company_id' => $company->id,
            'code' => '6100',
            'name' => 'Office Supplies',
            'name_ar' => 'مستلزمات مكتبية',
            'account_type' => 'expense',
            'parent_id' => $expenseRoot->id,
            'is_active' => true,
            'is_system' => false,
            'balance' => 150,
        ]);

        Account::create([
            'company_id' => $company->id,
            'code' => '6200',
            'name' => 'Travel Expense',
            'name_ar' => 'مصروف سفر',
            'account_type' => 'expense',
            'parent_id' => $expenseRoot->id,
            'is_active' => true,
            'is_system' => false,
            'balance' => 50,
        ]);

        Account::create([
            'company_id' => $company->id,
            'code' => '4000',
            'name' => 'Sales Revenue',
            'name_ar' => 'إيراد المبيعات',
            'account_type' => 'revenue',
            'is_active' => true,
            'is_system' => true,
            'balance' => 500,
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        $request = Request::create('/chart-of-accounts', 'GET', [
            'search' => 'office',
            'account_type' => 'expense',
            'min_balance' => '100',
            'max_balance' => '200',
        ]);
        $request->setUserResolver(fn () => $user);

        $view = app(AccountingPageController::class)->chartOfAccounts($request);
        $data = $view->getData();
        $accounts = $data['accounts'];
        $matchingAccounts = $data['matchingAccounts'];

        $this->assertCount(1, $accounts);
        $this->assertSame('Expenses', $accounts->first()->name);
        $this->assertCount(1, $accounts->first()->children);
        $this->assertSame('Office Supplies', $accounts->first()->children->first()->name);

        $this->assertCount(1, $matchingAccounts);
        $this->assertSame('Office Supplies', $matchingAccounts->first()->name);
    }

    public function test_chart_of_accounts_hides_dynamic_accounts_by_default_and_can_include_them(): void
    {
        $company = Company::create([
            'name' => 'Dynamic Chart Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
        ]);

        $user = User::factory()->create([
            'first_name' => 'Owner',
            'last_name' => 'User',
            'name' => 'Owner User',
            'role' => 'owner',
            'company_id' => $company->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $parent = Account::create([
            'company_id' => $company->id,
            'code' => '1103',
            'name' => 'المدينون',
            'name_ar' => 'المدينون',
            'account_type' => 'asset',
            'display_account_type' => 'المدينون',
            'is_active' => true,
            'is_system' => true,
            'balance' => 0,
        ]);

        Account::create([
            'company_id' => $company->id,
            'code' => '1103-C15',
            'name' => 'ذمة العميل - عميل',
            'name_ar' => 'ذمة العميل - عميل',
            'account_type' => 'asset',
            'display_account_type' => 'المدينون',
            'parent_id' => $parent->id,
            'is_active' => true,
            'is_system' => true,
            'balance' => 125,
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        $defaultRequest = Request::create('/chart-of-accounts', 'GET');
        $defaultRequest->setUserResolver(fn () => $user);
        $defaultData = app(AccountingPageController::class)->chartOfAccounts($defaultRequest)->getData();

        $this->assertFalse($defaultData['includeDynamicAccounts']);
        $this->assertCount(1, $defaultData['accounts']);
        $this->assertCount(0, $defaultData['accounts']->first()->children);

        $includeRequest = Request::create('/chart-of-accounts', 'GET', ['include_dynamic' => '1']);
        $includeRequest->setUserResolver(fn () => $user);
        $includeData = app(AccountingPageController::class)->chartOfAccounts($includeRequest)->getData();

        $this->assertTrue($includeData['includeDynamicAccounts']);
        $this->assertCount(1, $includeData['accounts']->first()->children);
        $this->assertSame('1103-C15', $includeData['accounts']->first()->children->first()->code);
    }
}
