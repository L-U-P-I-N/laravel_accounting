<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ExpenseReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_expenses_report_filters_by_date_and_expense_account(): void
    {
        $company = Company::create([
            'name' => 'Expense Reports Co',
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

        $expenseAccount = Account::create([
            'company_id' => $company->id,
            'code' => '6100',
            'name' => 'Office Supplies',
            'name_ar' => 'مستلزمات مكتبية',
            'account_type' => 'expense',
            'is_active' => true,
            'is_system' => false,
            'balance' => 0,
        ]);

        $otherExpenseAccount = Account::create([
            'company_id' => $company->id,
            'code' => '6200',
            'name' => 'Travel Expense',
            'name_ar' => 'مصروف سفر',
            'account_type' => 'expense',
            'is_active' => true,
            'is_system' => false,
            'balance' => 0,
        ]);

        $paymentAccount = Account::create([
            'company_id' => $company->id,
            'code' => '110201',
            'name' => 'Bank Account',
            'name_ar' => 'حساب بنكي',
            'account_type' => 'asset',
            'is_active' => true,
            'is_system' => false,
            'allows_direct_transactions' => true,
            'balance' => 0,
        ]);

        Expense::create([
            'expense_number' => 'EXP-0001',
            'company_id' => $company->id,
            'expense_account_id' => $expenseAccount->id,
            'payment_account_id' => $paymentAccount->id,
            'created_by' => $user->id,
            'expense_date' => '2026-03-10',
            'name' => 'Printer Paper',
            'reference' => 'REF-1',
            'amount' => 100,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 100,
            'status' => 'posted',
        ]);

        Expense::create([
            'expense_number' => 'EXP-0002',
            'company_id' => $company->id,
            'expense_account_id' => $otherExpenseAccount->id,
            'payment_account_id' => $paymentAccount->id,
            'created_by' => $user->id,
            'expense_date' => '2026-02-10',
            'name' => 'Taxi',
            'reference' => 'REF-2',
            'amount' => 50,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 50,
            'status' => 'posted',
        ]);

        $request = Request::create('/expenses/report', 'GET', [
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'expense_account_id' => $expenseAccount->id,
        ]);
        $request->setUserResolver(fn () => $user);
        view()->share('errors', new ViewErrorBag());

        $view = app(AccountingPageController::class)->expenses($request);
        $data = $view->getData();

        $this->assertCount(1, $data['expenses']);
        $this->assertSame('Printer Paper', $data['expenses']->first()->name);
        $this->assertSame(100.0, (float) $data['expenses']->sum('total'));
    }
}
