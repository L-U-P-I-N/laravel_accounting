<?php

namespace Tests\Feature;

use App\Http\Controllers\AccountingPageController;
use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AutomaticReferenceGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 3, 26, 12, 0, 0));
        config(['app.url' => 'http://localhost']);
        $this->withoutMiddleware();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_expense_form_displays_and_saves_a_generated_reference(): void
    {
        [$company, $user] = $this->createCompanyAndOwner();
        $expenseAccount = $this->createAccount($company, '6900', 'expense', 'Misc Expense');
        $paymentAccount = $this->createAccount($company, '110201', 'asset', 'Bank Account', null, true);

        $request = Request::create('/expenses', 'POST', [
            'name' => 'Office Snacks',
            'expense_date' => '2026-03-26',
            'expense_account_id' => $expenseAccount->id,
            'payment_account_id' => $paymentAccount->id,
            'amount' => '25.00',
            'tax_rate' => '0',
            'reference' => '',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = app(AccountingPageController::class)->storeExpense($request);

        $this->assertSame(302, $response->getStatusCode());

        $this->assertDatabaseHas('expenses', [
            'company_id' => $company->id,
            'name' => 'Office Snacks',
            'reference' => 'REF-EXP-0001',
        ]);
    }

    public function test_journal_entry_form_displays_and_saves_a_generated_reference(): void
    {
        [$company, $user] = $this->createCompanyAndOwner();
        $debitAccount = $this->createAccount($company, '1000', 'asset', 'Assets');
        $creditAccount = $this->createAccount($company, '2000', 'liability', 'Liabilities');

        $request = Request::create('/journal-entries', 'POST', [
            'entry_date' => '2026-03-26',
            'description' => 'Manual adjustment',
            'reference' => '',
            'line_account' => [$debitAccount->id, $creditAccount->id],
            'line_description' => ['Debit line', 'Credit line'],
            'line_debit' => ['100', '0'],
            'line_credit' => ['0', '100'],
        ]);
        $request->setUserResolver(fn () => $user);

        $response = app(AccountingPageController::class)->storeJournalEntry($request);

        $this->assertSame(302, $response->getStatusCode());

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $company->id,
            'description' => 'Manual adjustment',
            'reference' => 'REF-JRN-00001',
            'entry_type' => 'manual',
        ]);
    }

    public function test_supplier_payment_form_displays_and_saves_a_generated_reference(): void
    {
        [$company, $user] = $this->createCompanyAndOwner();
        $this->createAccount($company, '2000', 'liability', 'Liabilities');
        $this->createAccount($company, '2100', 'liability', 'Accounts Payable');
        $this->createAccount($company, '1100', 'asset', 'Cash & Bank');
        $paymentAccount = $this->createAccount($company, '110201', 'asset', 'Bank Account', null, true);

        $supplier = Supplier::create([
            'company_id' => $company->id,
            'code' => 'SUP-0001',
            'name' => 'Supplier One',
            'credit_limit' => 0,
            'balance' => 100,
            'is_active' => true,
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

        $request = Request::create('/suppliers/' . $supplier->id . '/payments', 'POST', [
            'supplier_action' => 'payment',
            'payment_amount' => '50.00',
            'payment_account_id' => $paymentAccount->id,
            'payment_reference' => '',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = app(AccountingPageController::class)->storeSupplierPayment($request, $supplier);

        $this->assertSame(302, $response->getStatusCode());

        $this->assertDatabaseHas('journal_entries', [
            'company_id' => $company->id,
            'source_type' => Supplier::class . ':payment',
            'source_id' => $supplier->id,
            'reference' => 'REF-SUP-PMT-00001',
        ]);
    }

    private function createCompanyAndOwner(): array
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

        return [$company, $user];
    }

    private function createAccount(Company $company, string $code, string $type, string $name, ?Account $parent = null, bool $allowsDirectTransactions = false): Account
    {
        return Account::create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => $name,
            'name_ar' => $name,
            'account_type' => $type,
            'parent_id' => $parent?->id,
            'allows_direct_transactions' => $allowsDirectTransactions,
            'is_active' => true,
            'is_system' => false,
            'balance' => 0,
        ]);
    }
}
