<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Support\DocumentNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DocumentNumberGeneratorTest extends TestCase
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

    public function test_it_generates_next_document_numbers_per_company(): void
    {
        $company = Company::create([
            'name' => 'Main Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
        ]);

        $otherCompany = Company::create([
            'name' => 'Other Co',
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

        $customer = Customer::create([
            'company_id' => $company->id,
            'name' => 'Customer One',
            'credit_limit' => 0,
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'company_id' => $company->id,
            'code' => 'SUP-0001',
            'name' => 'Supplier One',
            'credit_limit' => 0,
            'balance' => 100,
            'is_active' => true,
        ]);

        Invoice::create([
            'invoice_number' => 'INV-2026-0001',
            'customer_id' => $customer->id,
            'company_id' => $company->id,
            'invoice_date' => '2026-03-26',
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'paid_amount' => 0,
            'balance_due' => 100,
            'status' => 'sent',
            'payment_status' => 'pending',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        Purchase::create([
            'purchase_number' => 'PUR-2026-0001',
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'purchase_date' => '2026-03-26',
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'paid_amount' => 0,
            'balance_due' => 100,
            'status' => 'approved',
            'payment_status' => 'pending',
            'currency' => 'SAR',
            'exchange_rate' => 1,
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
            'description' => 'Existing Manual Entry',
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
            'source_id' => $supplier->id,
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

        Invoice::create([
            'invoice_number' => 'INV-2026-0001',
            'customer_id' => Customer::create([
                'company_id' => $otherCompany->id,
                'name' => 'Other Customer',
                'credit_limit' => 0,
                'is_active' => true,
            ])->id,
            'company_id' => $otherCompany->id,
            'invoice_date' => '2026-03-26',
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'paid_amount' => 0,
            'balance_due' => 100,
            'status' => 'sent',
            'payment_status' => 'pending',
            'currency' => 'SAR',
            'exchange_rate' => 1,
        ]);

        $generator = app(DocumentNumberGenerator::class);

        $this->assertSame('INV-2026-0002', $generator->nextInvoiceNumber($company->id));
        $this->assertSame('PUR-2026-0002', $generator->nextPurchaseNumber($company->id));
        $this->assertSame('EXP-2026-0002', $generator->nextExpenseNumber($company->id));
        $this->assertSame('JRN-2026-00003', $generator->nextJournalEntryNumber($company->id));
        $this->assertSame('SUP-PMT-2026-00002', $generator->nextSupplierPaymentNumber($company->id));
    }

    public function test_it_uses_highest_existing_journal_number_instead_of_count(): void
    {
        $company = Company::create([
            'name' => 'Gap Co',
            'country_code' => 'SA',
            'currency' => 'SAR',
        ]);

        $user = User::factory()->create([
            'first_name' => 'Gap',
            'last_name' => 'Owner',
            'name' => 'Gap Owner',
            'role' => 'owner',
            'company_id' => $company->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        JournalEntry::create([
            'entry_number' => 'JRN-2026-00001',
            'entry_date' => '2026-03-26',
            'description' => 'First Entry',
            'reference' => 'REF-1',
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
            'entry_number' => 'JRN-2026-00008',
            'entry_date' => '2026-03-26',
            'description' => 'Latest Entry',
            'reference' => 'REF-8',
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

        JournalEntry::query()
            ->where('company_id', $company->id)
            ->where('entry_number', 'JRN-2026-00001')
            ->delete();

        $generator = app(DocumentNumberGenerator::class);

        $this->assertSame('JRN-2026-00009', $generator->nextJournalEntryNumber($company->id));
    }
}
