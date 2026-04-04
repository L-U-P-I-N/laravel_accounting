<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AccountShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_show_page_displays_parent_children_and_recent_journal_lines(): void
    {
        $company = Company::create([
            'name' => 'Account Show Co',
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

        $root = Account::create([
            'company_id' => $company->id,
            'code' => '1',
            'name' => 'Assets',
            'name_ar' => 'الأصول',
            'account_type' => 'asset',
            'is_active' => true,
            'is_system' => true,
            'balance' => 0,
        ]);

        $parent = Account::create([
            'company_id' => $company->id,
            'code' => '11',
            'name' => 'أصول متداولة',
            'name_ar' => 'الأصول المتداولة',
            'account_type' => 'asset',
            'parent_id' => $root->id,
            'is_active' => true,
            'is_system' => true,
            'balance' => 0,
        ]);

        $account = Account::create([
            'company_id' => $company->id,
            'code' => '110101',
            'name' => 'النقدية في الخزينة',
            'name_ar' => 'النقدية في الخزينة',
            'account_type' => 'asset',
            'parent_id' => $parent->id,
            'allows_direct_transactions' => true,
            'is_active' => true,
            'is_system' => false,
            'balance' => 250,
        ]);

        $child = Account::create([
            'company_id' => $company->id,
            'code' => '110101-1',
            'name' => 'صندوق الفرع',
            'name_ar' => 'صندوق الفرع',
            'account_type' => 'asset',
            'parent_id' => $account->id,
            'is_active' => true,
            'is_system' => false,
            'balance' => 100,
        ]);

        $entry = JournalEntry::create([
            'entry_number' => 'JRN-2026-1001',
            'entry_date' => '2026-04-01',
            'description' => 'Cash receipt',
            'reference' => 'ACC-DETAIL-1',
            'entry_type' => 'manual',
            'entry_origin' => 'manual',
            'status' => 'posted',
            'total_debit' => 250,
            'total_credit' => 250,
            'company_id' => $company->id,
            'created_by' => $user->id,
        ]);

        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'description' => 'Cash line',
            'debit' => 250,
            'credit' => 0,
        ]);

        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag());

        $request = Request::create('/chart-of-accounts/' . $account->id, 'GET');
        $request->setUserResolver(fn () => $user);

        $view = app(AccountingPageController::class)->showAccount($request, $account);
        $content = $view->render();

        $this->assertStringContainsString('النقدية في الخزينة', $content);
        $this->assertStringContainsString('الأصول المتداولة', $content);
        $this->assertStringContainsString('صندوق الفرع', $content);
        $this->assertStringContainsString('قابل للدفع/التحصيل', $content);
        $this->assertStringContainsString('JRN-2026-1001', $content);
        $this->assertStringContainsString('حساب أب فرعي', $content);
    }
}
