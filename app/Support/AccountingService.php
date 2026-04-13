<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;

class AccountingService
{
    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
        private readonly ChartOfAccountsSynchronizer $chartOfAccountsSynchronizer,
    ) {
    }

    public function syncInvoiceEntry(Invoice $invoice, User $user): JournalEntry
    {
        $invoice->loadMissing(['items.product', 'customer.account', 'paymentAccount']);

        $lines = collect();
        $paidAmount = $this->normalizeAmount(min((float) $invoice->paid_amount, (float) $invoice->total));
        $receivableAmount = $this->normalizeAmount(max((float) $invoice->total - $paidAmount, 0));

        if ($paidAmount > 0) {
            $this->pushLine(
                $lines,
                $this->settlementAccountForInvoice($invoice),
                'إثبات المقبوض من الفاتورة ' . $invoice->invoice_number,
                $paidAmount,
                0,
            );
        }

        if ($receivableAmount > 0) {
            $this->pushLine(
                $lines,
                $this->customerReceivableAccount($invoice),
                'إثبات ذمة العميل للفاتورة ' . $invoice->invoice_number,
                $receivableAmount,
                0,
            );
        }

        $inventoryCreditTotal = 0.0;
        $cogsDebitTotal = 0.0;

        foreach ($invoice->items as $item) {
            // حساب سعر البيع بدون ضريبة = الكمية × سعر الوحدة
            $sellingPrice = $this->normalizeAmount((float) $item->quantity * (float) $item->unit_price);

            if ($sellingPrice > 0) {
                $this->pushLine(
                    $lines,
                    $this->revenueAccountForProduct($item->product, (int) $invoice->company_id),
                    'إثبات الإيراد للفاتورة ' . $invoice->invoice_number . ' - ' . ($item->product?->name ?? ''),
                    0,
                    $sellingPrice,
                );
            }

            if ($item->product?->type === 'product') {
                $lineCost = $this->normalizeAmount((float) $item->quantity * (float) $item->product->cost_price);

                if ($lineCost > 0) {
                    $this->pushLine(
                        $lines,
                        $this->cogsAccountForProduct($item->product, (int) $invoice->company_id),
                        'إثبات تكلفة البضاعة المباعة للفاتورة ' . $invoice->invoice_number,
                        $lineCost,
                        0,
                    );

                    $this->pushLine(
                        $lines,
                        $this->inventoryAccountForProduct($item->product, (int) $invoice->company_id),
                        'تخفيض المخزون للفاتورة ' . $invoice->invoice_number,
                        0,
                        $lineCost,
                    );

                    $inventoryCreditTotal += $lineCost;
                    $cogsDebitTotal += $lineCost;
                }
            }
        }

        if ($lines->where('credit', '>', 0)->isEmpty() && (float) $invoice->subtotal > 0) {
            $this->pushLine(
                $lines,
                $this->salesRevenueAccount((int) $invoice->company_id),
                'إثبات إيراد مبيعات الفاتورة ' . $invoice->invoice_number,
                0,
                $this->normalizeAmount((float) $invoice->subtotal),
            );
        }

        if ((float) $invoice->tax_amount > 0) {
            $this->pushLine(
                $lines,
                $this->outputVatAccount((int) $invoice->company_id),
                'ضريبة المخرجات للفاتورة ' . $invoice->invoice_number,
                0,
                $this->normalizeAmount((float) $invoice->tax_amount),
            );
        }

        return $this->syncJournalEntry(
            companyId: (int) $invoice->company_id,
            user: $user,
            source: $invoice,
            entryType: 'invoice',
            description: 'قيد آلي للفاتورة ' . $invoice->invoice_number,
            reference: $invoice->invoice_number,
            entryDate: $invoice->invoice_date,
            lines: $lines
        );
    }

    public function syncPurchaseEntry(Purchase $purchase, User $user): JournalEntry
    {
        $purchase->loadMissing(['items.product', 'supplier.account', 'paymentAccount']);

        $lines = collect();
        $totalDebit = 0.0;

        // 1. Debit Side: Inventory or Expenses
        foreach ($purchase->items as $item) {
            $lineNet = $this->normalizeAmount((float) $item->total - (float) $item->tax_amount);

            if ($lineNet <= 0) {
                continue;
            }

            if ($item->product?->type === 'product') {
                $this->pushLine(
                    $lines,
                    $this->inventoryAccountForProduct($item->product, (int) $purchase->company_id),
                    'إثبات قيمة المخزون لطلب الشراء ' . $purchase->purchase_number . ' - ' . $item->product->name,
                    $lineNet,
                    0,
                );
            } else {
                $this->pushLine(
                    $lines,
                    $this->miscExpenseAccount((int) $purchase->company_id),
                    'إثبات قيمة الخدمات أو المصروفات في طلب الشراء ' . $purchase->purchase_number,
                    $lineNet,
                    0,
                );
            }
            $totalDebit += $lineNet;
        }

        // 2. Debit Side: Tax
        $taxAmount = $this->normalizeAmount((float) $purchase->tax_amount);
        if ($taxAmount > 0) {
            $this->pushLine(
                $lines,
                $this->inputVatAccount((int) $purchase->company_id),
                'ضريبة المدخلات لطلب الشراء ' . $purchase->purchase_number,
                $taxAmount,
                0,
            );
            $totalDebit += $taxAmount;
        }

        // 3. Credit Side: Settlement Account (Bank/Cash or Supplier)
        // We use the calculated totalDebit to ensure the entry is always balanced
        if ($totalDebit > 0) {
            // استخدام حساب المورد الفرعي (2101-S*) إذا كان هناك مورد ولم يتم الدفع نقداً
            if ($purchase->supplier && ! $purchase->paymentAccount) {
                $creditAccount = $purchase->supplier->account;
            } else {
                $creditAccount = $this->settlementAccountForPurchase($purchase);
            }
            
            $this->pushLine(
                $lines,
                $creditAccount,
                'إثبات استحقاق/سداد طلب الشراء ' . $purchase->purchase_number,
                0,
                $totalDebit,
            );
        }

        return $this->syncJournalEntry(
            companyId: (int) $purchase->company_id,
            user: $user,
            source: $purchase,
            entryType: 'purchase',
            description: 'قيد آلي لطلب الشراء ' . $purchase->purchase_number,
            reference: $purchase->purchase_number,
            entryDate: $purchase->purchase_date,
            lines: $lines
        );
    }

    public function syncExpenseEntry(Expense $expense, User $user): JournalEntry
    {
        $expense->loadMissing(['expenseAccount', 'paymentAccount']);

        $lines = collect([
            [
                'account' => $expense->expenseAccount,
                'description' => 'إثبات المصروف ' . $expense->name,
                'debit' => round((float) $expense->amount, 2),
                'credit' => 0,
            ],
        ]);

        if ((float) $expense->tax_amount > 0) {
            $lines->push([
                'account' => $this->inputVatAccount((int) $expense->company_id),
                'description' => 'ضريبة مصروف ' . $expense->name,
                'debit' => round((float) $expense->tax_amount, 2),
                'credit' => 0,
            ]);
        }

        $lines->push([
            'account' => $expense->paymentAccount,
            'description' => 'سداد المصروف ' . $expense->name,
            'debit' => 0,
            'credit' => round((float) $expense->total, 2),
        ]);

        return $this->syncJournalEntry(
            companyId: (int) $expense->company_id,
            user: $user,
            source: $expense,
            entryType: 'expense',
            description: 'قيد آلي للمصروف ' . $expense->expense_number,
            reference: $expense->expense_number,
            entryDate: $expense->expense_date,
            lines: $lines
        );
    }

    public function createSupplierPaymentEntry(Supplier $supplier, float $paymentAmount, User $user, string $reference, ?Account $settlementAccount = null, ?string $entryDate = null): JournalEntry
    {
        $supplier->loadMissing(['company', 'account']);

        $lines = collect([
            [
                'account' => $this->supplierAccount($supplier),
                'description' => 'تخفيض ذمم المورد ' . $supplier->name,
                'debit' => round($paymentAmount, 2),
                'credit' => 0,
            ],
            [
                'account' => $settlementAccount ?? $this->defaultSettlementAccount((int) $supplier->company_id),
                'description' => 'سداد دفعة للمورد ' . $supplier->name,
                'debit' => 0,
                'credit' => round($paymentAmount, 2),
            ],
        ]);

        return $this->createJournalEntry(
            companyId: (int) $supplier->company_id,
            user: $user,
            sourceType: Supplier::class . ':payment',
            sourceId: (int) $supplier->id,
            entryType: 'payment',
            description: 'قيد آلي لسداد المورد ' . $supplier->name,
            reference: $reference,
            entryDate: $entryDate ?? now()->toDateString(),
            lines: $lines
        );
    }

    public function createManualJournalEntry(int $companyId, User $user, array $payload): JournalEntry
    {
        $lines = collect($payload['lines'] ?? [])->map(function (array $line) use ($companyId) {
            return [
                'account' => $this->accountForCompany($companyId, (int) $line['account_id']),
                'description' => $line['description'] ?? null,
                'debit' => round((float) ($line['debit'] ?? 0), 2),
                'credit' => round((float) ($line['credit'] ?? 0), 2),
            ];
        });

        $totals = $this->validateBalancedLines($lines);

        $entry = new JournalEntry();
        $entry->fill([
            'entry_number' => $this->documentNumberGenerator->nextJournalEntryNumber($companyId),
            'entry_date' => $payload['entry_date'],
            'description' => $payload['description'],
            'reference' => $payload['reference'] ?? null,
            'source_type' => null,
            'source_id' => null,
            'entry_type' => 'manual',
            'entry_origin' => 'manual',
            'status' => 'posted',
            'total_debit' => $totals['debit'],
            'total_credit' => $totals['credit'],
            'company_id' => $companyId,
            'created_by' => $user->id,
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);
        $entry->save();

        foreach ($lines as $line) {
            $entry->lines()->create([
                'account_id' => $line['account']->id,
                'description' => $line['description'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
            ]);

            $line['account']->updateBalance((float) $line['debit'], (float) $line['credit']);
        }

        return $entry->fresh(['lines.account']);
    }

    public function deleteAutomaticEntriesForSource(Model $source): void
    {
        $entries = JournalEntry::with('lines.account')
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->get();

        foreach ($entries as $entry) {
            $this->reverseEntryBalances($entry);
            $entry->delete();
        }
    }

    public function nextJournalEntryNumber(int $companyId): string
    {
        return $this->documentNumberGenerator->nextJournalEntryNumber($companyId);
    }

    public function resyncCompanyTransactions(\App\Models\Company $company, User $user): array
    {
        $summary = [
            'invoices' => 0,
            'purchases' => 0,
            'expenses' => 0,
        ];

        Invoice::with(['items.product', 'customer.account'])
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->get()
            ->each(function (Invoice $invoice) use ($user, &$summary) {
                $this->syncInvoiceEntry($invoice, $user);
                $summary['invoices']++;
            });

        Purchase::with(['items.product', 'supplier.account'])
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->get()
            ->each(function (Purchase $purchase) use ($user, &$summary) {
                $this->syncPurchaseEntry($purchase, $user);
                $summary['purchases']++;
            });

        Expense::with(['expenseAccount', 'paymentAccount'])
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->get()
            ->each(function (Expense $expense) use ($user, &$summary) {
                $this->syncExpenseEntry($expense, $user);
                $summary['expenses']++;
            });

        return $summary;
    }

    private function syncJournalEntry(int $companyId, User $user, Model $source, string $entryType, string $description, ?string $reference, mixed $entryDate, Collection $lines): JournalEntry
    {
        $entry = JournalEntry::with('lines.account')
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->first();

        if ($entry) {
            $this->reverseEntryBalances($entry);
            $entry->lines()->delete();

            return $this->persistEntry(
                entry: $entry,
                companyId: $companyId,
                user: $user,
                sourceType: $source::class,
                sourceId: (int) $source->getKey(),
                entryType: $entryType,
                description: $description,
                reference: $reference,
                entryDate: $entryDate,
                lines: $lines,
                isNew: false,
            );
        }

        return $this->createJournalEntry(
            companyId: $companyId,
            user: $user,
            sourceType: $source::class,
            sourceId: (int) $source->getKey(),
            entryType: $entryType,
            description: $description,
            reference: $reference,
            entryDate: $entryDate,
            lines: $lines
        );
    }

    private function createJournalEntry(int $companyId, User $user, string $sourceType, int $sourceId, string $entryType, string $description, ?string $reference, mixed $entryDate, Collection $lines): JournalEntry
    {
        return $this->persistEntry(
            entry: new JournalEntry(),
            companyId: $companyId,
            user: $user,
            sourceType: $sourceType,
            sourceId: $sourceId,
            entryType: $entryType,
            description: $description,
            reference: $reference,
            entryDate: $entryDate,
            lines: $lines,
            isNew: true,
        );
    }

    private function persistEntry(JournalEntry $entry, int $companyId, User $user, string $sourceType, int $sourceId, string $entryType, string $description, ?string $reference, mixed $entryDate, Collection $lines, bool $isNew): JournalEntry
    {
        $totals = $this->validateBalancedLines($lines);

        $entry->fill([
            'entry_number' => $isNew ? $this->documentNumberGenerator->nextJournalEntryNumber($companyId) : $entry->entry_number,
            'entry_date' => $entryDate,
            'description' => $description,
            'reference' => $reference,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'entry_type' => $entryType,
            'entry_origin' => 'automatic',
            'status' => 'posted',
            'total_debit' => $totals['debit'],
            'total_credit' => $totals['credit'],
            'company_id' => $companyId,
            'created_by' => $isNew ? $user->id : $entry->created_by,
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);

        $entry->save();

        foreach ($lines as $line) {
            $entry->lines()->create([
                'account_id' => $line['account']->id,
                'description' => $line['description'] ?? null,
                'debit' => $line['debit'],
                'credit' => $line['credit'],
            ]);

            $line['account']->updateBalance((float) $line['debit'], (float) $line['credit']);
        }

        return $entry->fresh(['lines.account']);
    }

    private function reverseEntryBalances(JournalEntry $entry): void
    {
        foreach ($entry->lines as $line) {
            if ($line->account) {
                $line->account->updateBalance((float) $line->credit, (float) $line->debit);
            }
        }
    }

    private function validateBalancedLines(Collection $lines): array
    {
        $debit = round((float) $lines->sum('debit'), 2);
        $credit = round((float) $lines->sum('credit'), 2);

        if ($debit <= 0 || $credit <= 0 || abs($debit - $credit) > 0.009) {
            throw new RuntimeException('القيد المحاسبي غير متوازن.');
        }

        return ['debit' => $debit, 'credit' => $credit];
    }

    private function accountForCompany(int $companyId, int $accountId): Account
    {
        $account = Account::where('company_id', $companyId)->find($accountId);

        if (! $account) {
            throw new RuntimeException('الحساب المحدد غير موجود ضمن الشركة الحالية.');
        }

        return $account;
    }

    private function receivablesAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '1103', 'المدينون', 'asset', '11', ['المدينون', 'العملاء', 'ذمم مدينة', 'حسابات المدينين', 'Customers', 'Receivable', 'Debtor']);
    }

    private function cashAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '110101', 'النقدية في الخزينة', 'asset', '1101', ['النقدية في الخزينة', 'الصندوق', 'Cash']);
    }

    private function inventoryAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '1106', 'المخزون', 'asset', '11', ['المخزون', 'Inventory']);
    }

    private function payablesAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '2101', 'الدائنون', 'liability', '21', ['الدائنون', 'الموردين', 'ذمم دائنة', 'حسابات الدائنين', 'Suppliers', 'Accounts Payable', 'Payable']);
    }

    private function inputVatAccount(int $companyId): Account
    {
        $configuredAccount = $this->configuredTaxAccount($companyId, ['input_vat', 'vat_input']);

        if ($configuredAccount) {
            return $configuredAccount;
        }

        return $this->resolveConceptAccount($companyId, '2105', 'ضريبة القيمة المضافة المستحقة', 'liability', '21', ['ضريبة القيمة المضافة المستحقة', 'ضريبة المدخلات', 'Input VAT', 'VAT Payable']);
    }

    private function outputVatAccount(int $companyId): Account
    {
        $configuredAccount = $this->configuredTaxAccount($companyId, ['output_vat', 'vat']);

        if ($configuredAccount) {
            return $configuredAccount;
        }

        return $this->resolveConceptAccount($companyId, '2105', 'ضريبة القيمة المضافة المستحقة', 'liability', '21', ['ضريبة المخرجات', 'ضريبة القيمة المضافة المستحقة', 'Output VAT', 'VAT Payable']);
    }

    private function configuredTaxAccount(int $companyId, array $taxTypes): ?Account
    {
        $setting = TaxSetting::with('account')
            ->where('company_id', $companyId)
            ->whereIn('tax_type', $taxTypes)
            ->whereNotNull('account_id')
            ->orderByDesc('is_default')
            ->first();

        if (! $setting?->account) {
            return null;
        }

        return $setting->account;
    }

    private function salesRevenueAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '4101', 'إيرادات المبيعات/ الخدمات', 'revenue', '41', ['إيرادات المبيعات', 'إيرادات المبيعات/ الخدمات', 'مبيعات', 'Sales Revenue', 'Sales']);
    }

    private function serviceRevenueAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '4101', 'إيرادات المبيعات/ الخدمات', 'revenue', '41', ['إيرادات الخدمات', 'إيرادات المبيعات/ الخدمات', 'مبيعات الخدمات', 'مبيعات', 'Service Revenue', 'Sales']);
    }

    private function miscExpenseAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '5214', 'مصاريف أخرى', 'expense', '52', ['مصاريف أخرى', 'مصروفات متنوعة', 'مصاريف إدارية وعامة', 'Miscellaneous', 'General Expense']);
    }

    private function cogsAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '5101', 'تكلفة البضاعة المباعة', 'cogs', '51', ['تكلفة البضاعة المباعة', 'Cost of Goods Sold', 'COGS']);
    }

    private function bankAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '110201', 'حساب البنك الجاري - اسم البنك', 'asset', '1102', ['حساب البنك الجاري', 'النقدية في البنك', 'الحساب البنكي', 'الحسابات الجارية البنكية', 'Bank Account', 'Bank']);
    }

    private function customerReceivableAccount(Invoice $invoice): Account
    {
        if (! $invoice->customer) {
            return $this->receivablesAccount((int) $invoice->company_id);
        }

        $customer = $invoice->customer;

        if (! $customer->account_id) {
            return $this->chartOfAccountsSynchronizer->syncCustomerAccount($customer);
        }

        return $this->accountForCompany((int) $invoice->company_id, (int) $customer->account_id);
    }

    private function supplierPayableAccount(Purchase $purchase): Account
    {
        if (! $purchase->supplier) {
            return $this->payablesAccount((int) $purchase->company_id);
        }

        return $this->supplierAccount($purchase->supplier);
    }

    private function supplierAccount(Supplier $supplier): Account
    {
        if (! $supplier->account_id) {
            return $this->chartOfAccountsSynchronizer->syncSupplierAccount($supplier);
        }

        return $this->accountForCompany((int) $supplier->company_id, (int) $supplier->account_id);
    }

    private function revenueAccountForProduct(?Product $product, int $companyId): Account
    {
        if (! $product) {
            return $this->salesRevenueAccount($companyId);
        }

        if (! $product->revenue_account_id) {
            $product = $this->chartOfAccountsSynchronizer->syncProductAccounts($product);
        }

        return $product->revenue_account_id
            ? $this->accountForCompany($companyId, (int) $product->revenue_account_id)
            : ($product->type === 'service' ? $this->serviceRevenueAccount($companyId) : $this->salesRevenueAccount($companyId));
    }

    private function inventoryAccountForProduct(?Product $product, int $companyId): Account
    {
        if (! $product || $product->type !== 'product') {
            return $this->inventoryAccount($companyId);
        }

        if (! $product->inventory_account_id) {
            $product = $this->chartOfAccountsSynchronizer->syncProductAccounts($product);
        }

        return $product->inventory_account_id
            ? $this->accountForCompany($companyId, (int) $product->inventory_account_id)
            : $this->inventoryAccount($companyId);
    }

    private function cogsAccountForProduct(?Product $product, int $companyId): Account
    {
        if (! $product || $product->type !== 'product') {
            return $this->cogsAccount($companyId);
        }

        if (! $product->cogs_account_id) {
            $product = $this->chartOfAccountsSynchronizer->syncProductAccounts($product);
        }

        return $product->cogs_account_id
            ? $this->accountForCompany($companyId, (int) $product->cogs_account_id)
            : $this->cogsAccount($companyId);
    }

    private function defaultSettlementAccount(int $companyId): Account
    {
        return $this->bankAccount($companyId);
    }

    private function settlementAccountForInvoice(Invoice $invoice): Account
    {
        return $invoice->paymentAccount
            ?? $this->defaultSettlementAccount((int) $invoice->company_id);
    }

    private function settlementAccountForPurchase(Purchase $purchase): Account
    {
        return $purchase->paymentAccount
            ?? $this->defaultSettlementAccount((int) $purchase->company_id);
    }

    private function pushLine(Collection $lines, $account, string $description, float $debit, float $credit): void
    {
        $debit = $this->normalizeAmount($debit);
        $credit = $this->normalizeAmount($credit);

        if ($debit <= 0 && $credit <= 0) {
            return;
        }

        // Handle both Account model and array from existing lines
        $accountModel = $account instanceof Account ? $account : Account::find($account['id'] ?? $account->id);

        $lines->push([
            'account' => $accountModel,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
        ]);
    }

    private function normalizeAmount(float $amount): float
    {
        return round($amount, 2);
    }

    private function resolveConceptAccount(int $companyId, string $fallbackCode, string $nameAr, string $type, ?string $parentCode, array $nameFragments): Account
    {
        $account = $this->findAccountByNameFragments($companyId, $type, $nameFragments)
            ?? $this->findAccountByCode($companyId, $fallbackCode, $type);

        if ($account) {
            return $account;
        }

        return $this->accountByCode($companyId, $fallbackCode, $nameAr, $type, $parentCode);
    }

    private function findAccountByNameFragments(int $companyId, string $type, array $nameFragments): ?Account
    {
        foreach ($nameFragments as $fragment) {
            $account = Account::where('company_id', $companyId)
                ->where('account_type', $type)
                ->where(function ($query) use ($fragment) {
                    $query->where('name', 'like', '%' . $fragment . '%')
                        ->orWhere('name_ar', 'like', '%' . $fragment . '%');
                })
                ->first();

            if ($account) {
                return $account;
            }
        }

        return null;
    }

    private function findAccountByCode(int $companyId, string $code, string $type): ?Account
    {
        return Account::where('company_id', $companyId)
            ->where('account_type', $type)
            ->where('code', $code)
            ->first();
    }

    private function accountByCode(int $companyId, string $code, string $nameAr, string $type, ?string $parentCode = null): Account
    {
        $account = Account::where('company_id', $companyId)->where('code', $code)->first();

        if ($account) {
            return $account;
        }

        $parentId = null;

        if ($parentCode) {
            $parentId = Account::where('company_id', $companyId)->where('code', $parentCode)->value('id');
        }

        return Account::create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $nameAr,
            'name_ar' => $nameAr,
            'account_type' => $type,
            'parent_id' => $parentId,
            'is_active' => true,
            'is_system' => true,
        ]);
    }
}
