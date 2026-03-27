<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;

class AccountingService
{
    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
    ) {
    }

    public function syncInvoiceEntry(Invoice $invoice, User $user): JournalEntry
    {
        $invoice->loadMissing(['items.product', 'customer']);

        $salesAmount = 0;
        $serviceAmount = 0;

        foreach ($invoice->items as $item) {
            $lineNet = round((float) $item->total - (float) $item->tax_amount, 2);
            $productType = $item->product?->type;

            if ($productType === 'service') {
                $serviceAmount += $lineNet;
            } else {
                $salesAmount += $lineNet;
            }
        }

        if ($salesAmount <= 0 && $serviceAmount <= 0 && (float) $invoice->subtotal > 0) {
            $salesAmount = round((float) $invoice->subtotal, 2);
        }

        $lines = collect([
            [
                'account' => $this->receivablesAccount((int) $invoice->company_id),
                'description' => 'إثبات ذمة العميل للفاتورة ' . $invoice->invoice_number,
                'debit' => round((float) $invoice->total, 2),
                'credit' => 0,
            ],
        ]);

        if ($salesAmount > 0) {
            $lines->push([
                'account' => $this->salesRevenueAccount((int) $invoice->company_id),
                'description' => 'إثبات إيراد مبيعات الفاتورة ' . $invoice->invoice_number,
                'debit' => 0,
                'credit' => round($salesAmount, 2),
            ]);
        }

        if ($serviceAmount > 0) {
            $lines->push([
                'account' => $this->serviceRevenueAccount((int) $invoice->company_id),
                'description' => 'إثبات إيراد خدمات الفاتورة ' . $invoice->invoice_number,
                'debit' => 0,
                'credit' => round($serviceAmount, 2),
            ]);
        }

        if ((float) $invoice->tax_amount > 0) {
            $lines->push([
                'account' => $this->outputVatAccount((int) $invoice->company_id),
                'description' => 'ضريبة المخرجات للفاتورة ' . $invoice->invoice_number,
                'debit' => 0,
                'credit' => round((float) $invoice->tax_amount, 2),
            ]);
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
        $purchase->loadMissing(['items.product', 'supplier']);

        $inventoryAmount = 0;
        $expenseAmount = 0;

        foreach ($purchase->items as $item) {
            $lineNet = round((float) $item->total - (float) $item->tax_amount, 2);
            $productType = $item->product?->type;

            if ($productType === 'service' || $item->product_id === null) {
                $expenseAmount += $lineNet;
            } else {
                $inventoryAmount += $lineNet;
            }
        }

        if ($inventoryAmount <= 0 && $expenseAmount <= 0 && (float) $purchase->subtotal > 0) {
            $inventoryAmount = round((float) $purchase->subtotal, 2);
        }

        $lines = collect();

        if ($inventoryAmount > 0) {
            $lines->push([
                'account' => $this->inventoryAccount((int) $purchase->company_id),
                'description' => 'إثبات قيمة المخزون لطلب الشراء ' . $purchase->purchase_number,
                'debit' => round($inventoryAmount, 2),
                'credit' => 0,
            ]);
        }

        if ($expenseAmount > 0) {
            $lines->push([
                'account' => $this->miscExpenseAccount((int) $purchase->company_id),
                'description' => 'إثبات قيمة الخدمات أو المصروفات في طلب الشراء ' . $purchase->purchase_number,
                'debit' => round($expenseAmount, 2),
                'credit' => 0,
            ]);
        }

        if ((float) $purchase->tax_amount > 0) {
            $lines->push([
                'account' => $this->inputVatAccount((int) $purchase->company_id),
                'description' => 'ضريبة المدخلات لطلب الشراء ' . $purchase->purchase_number,
                'debit' => round((float) $purchase->tax_amount, 2),
                'credit' => 0,
            ]);
        }

        $lines->push([
            'account' => $this->payablesAccount((int) $purchase->company_id),
            'description' => 'إثبات التزام المورد ' . ($purchase->supplier?->name ?? ''),
            'debit' => 0,
            'credit' => round((float) $purchase->total, 2),
        ]);

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

    public function createSupplierPaymentEntry(Supplier $supplier, float $paymentAmount, User $user, string $reference): JournalEntry
    {
        $supplier->loadMissing('company');

        $lines = collect([
            [
                'account' => $this->payablesAccount((int) $supplier->company_id),
                'description' => 'تخفيض ذمم المورد ' . $supplier->name,
                'debit' => round($paymentAmount, 2),
                'credit' => 0,
            ],
            [
                'account' => $this->bankAccount((int) $supplier->company_id),
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
            entryDate: now()->toDateString(),
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
        return $this->resolveConceptAccount($companyId, '1200', 'الذمم المدينة', 'asset', '1000', ['ذمم مدينة', 'حسابات المدينين', 'Receivable', 'Debtor']);
    }

    private function inventoryAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '1300', 'المخزون', 'asset', '1000', ['المخزون', 'Inventory']);
    }

    private function payablesAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '2100', 'الذمم الدائنة', 'liability', '2000', ['ذمم دائنة', 'حسابات الدائنين', 'Accounts Payable', 'Payable']);
    }

    private function inputVatAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '2310', 'ضريبة المدخلات', 'asset', '1000', ['ضريبة المدخلات', 'Input VAT']);
    }

    private function outputVatAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '2300', 'ضريبة القيمة المضافة المستحقة', 'liability', '2000', ['ضريبة القيمة المضافة المستحقة', 'VAT Payable']);
    }

    private function salesRevenueAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '4100', 'إيرادات المبيعات', 'revenue', '4000', ['إيرادات المبيعات', 'المبيعات', 'Sales Revenue', 'Sales']);
    }

    private function serviceRevenueAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '4200', 'إيرادات الخدمات', 'revenue', '4000', ['إيرادات الخدمات', 'مبيعات الخدمات', 'Service Revenue', 'Service']);
    }

    private function miscExpenseAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '6900', 'مصروفات متنوعة', 'expense', '6000', ['مصروفات متنوعة', 'مصاريف إدارية وعامة', 'Miscellaneous', 'General Expense']);
    }

    private function bankAccount(int $companyId): Account
    {
        return $this->resolveConceptAccount($companyId, '1102', 'الحساب البنكي', 'asset', '1100', ['الحساب البنكي', 'الحسابات الجارية البنكية', 'Bank Account', 'Bank']);
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
