<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Account;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ReferenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReferenceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 3, 26, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_generates_references_from_identifiers(): void
    {
        $generator = app(ReferenceGenerator::class);

        $this->assertSame('REF-EXP-0001', $generator->fromIdentifier('EXP-2026-0001'));
    }

    public function test_it_generates_next_references_based_on_existing_records(): void
    {
        $company = Company::create([
            'name' => 'Test Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
        ]);

        $user = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'name' => 'Test Owner',
            'role' => 'owner',
            'company_id' => $company->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $expenseAccount = Account::create([
            'company_id' => $company->id,
            'code' => '6900',
            'name' => 'Misc Expense',
            'name_ar' => 'Misc Expense',
            'account_type' => 'expense',
            'is_active' => true,
            'is_system' => false,
            'balance' => 0,
        ]);

        $paymentAccount = Account::create([
            'company_id' => $company->id,
            'code' => '110201',
            'name' => 'Bank Account',
            'name_ar' => 'Bank Account',
            'account_type' => 'asset',
            'allows_direct_transactions' => true,
            'is_active' => true,
            'is_system' => false,
            'balance' => 0,
        ]);

        Expense::create([
            'expense_number' => 'EXP-2026-0001',
            'company_id' => $company->id,
            'expense_account_id' => $expenseAccount->id,
            'payment_account_id' => $paymentAccount->id,
            'created_by' => $user->id,
            'expense_date' => '2026-03-26',
            'name' => 'Existing Expense',
            'reference' => 'REF-EXP-0001',
            'amount' => 10,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'total' => 10,
            'status' => 'posted',
        ]);

        JournalEntry::create([
            'entry_number' => 'JRN-2026-00001',
            'entry_date' => '2026-03-26',
            'description' => 'Existing Entry',
            'reference' => 'REF-JRN-00001',
            'entry_type' => 'manual',
            'entry_origin' => 'manual',
            'status' => 'posted',
            'total_debit' => 10,
            'total_credit' => 10,
            'company_id' => $company->id,
            'created_by' => $user->id,
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);

        JournalEntry::create([
            'entry_number' => 'JRN-2026-00002',
            'entry_date' => '2026-03-26',
            'description' => 'Existing Supplier Payment',
            'reference' => 'REF-SUP-PMT-00001',
            'source_type' => Supplier::class . ':payment',
            'source_id' => 1,
            'entry_type' => 'payment',
            'entry_origin' => 'automatic',
            'status' => 'posted',
            'total_debit' => 50,
            'total_credit' => 50,
            'company_id' => $company->id,
            'created_by' => $user->id,
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);

        $generator = app(ReferenceGenerator::class);

        $this->assertSame('REF-EXP-0002', $generator->nextExpenseReference($company->id));
        $this->assertSame('REF-JRN-00003', $generator->nextJournalReference($company->id));
        $this->assertSame('REF-SUP-PMT-00002', $generator->nextSupplierPaymentReference($company->id));
    }
}
