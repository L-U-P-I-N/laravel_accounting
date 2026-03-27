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
use Tests\TestCase;

class JournalEntriesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_entries_page_filters_by_account_and_date_range(): void
    {
        $company = Company::create([
            'name' => 'Ledger Co',
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

        $cashAccount = Account::create([
            'company_id' => $company->id,
            'code' => '1101',
            'name' => 'Cash',
            'name_ar' => 'الصندوق',
            'account_type' => 'asset',
            'is_active' => true,
            'is_system' => false,
            'balance' => 0,
        ]);

        $expenseAccount = Account::create([
            'company_id' => $company->id,
            'code' => '6100',
            'name' => 'Expense',
            'name_ar' => 'مصروف',
            'account_type' => 'expense',
            'is_active' => true,
            'is_system' => false,
            'balance' => 0,
        ]);

        $matchingEntry = JournalEntry::create([
            'entry_number' => 'JRN-2026-0001',
            'entry_date' => '2026-03-15',
            'description' => 'Office expense',
            'reference' => 'REF-LEDGER-1',
            'entry_type' => 'expense',
            'entry_origin' => 'manual',
            'status' => 'posted',
            'total_debit' => 100,
            'total_credit' => 100,
            'company_id' => $company->id,
            'created_by' => $user->id,
        ]);

        JournalLine::create([
            'journal_entry_id' => $matchingEntry->id,
            'account_id' => $expenseAccount->id,
            'description' => 'Expense line',
            'debit' => 100,
            'credit' => 0,
        ]);

        JournalLine::create([
            'journal_entry_id' => $matchingEntry->id,
            'account_id' => $cashAccount->id,
            'description' => 'Cash line',
            'debit' => 0,
            'credit' => 100,
        ]);

        $nonMatchingEntry = JournalEntry::create([
            'entry_number' => 'JRN-2026-0002',
            'entry_date' => '2026-02-10',
            'description' => 'Older entry',
            'reference' => 'REF-LEDGER-2',
            'entry_type' => 'manual',
            'entry_origin' => 'manual',
            'status' => 'draft',
            'total_debit' => 75,
            'total_credit' => 75,
            'company_id' => $company->id,
            'created_by' => $user->id,
        ]);

        JournalLine::create([
            'journal_entry_id' => $nonMatchingEntry->id,
            'account_id' => $cashAccount->id,
            'description' => 'Cash adjustment',
            'debit' => 75,
            'credit' => 0,
        ]);

        $request = Request::create('/journal-entries', 'GET', [
            'account_id' => $expenseAccount->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'status' => 'posted',
        ]);
        $request->setUserResolver(fn () => $user);

        $view = app(AccountingPageController::class)->journalEntries($request);
        $data = $view->getData();

        $this->assertCount(1, $data['entries']);
        $this->assertSame('JRN-2026-0001', $data['entries']->first()->entry_number);
        $this->assertCount(2, $data['accounts']);
        $this->assertSame($expenseAccount->id, $data['filters']['account_id']);
    }
}
