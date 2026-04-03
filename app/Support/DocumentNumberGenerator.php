<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Supplier;

class DocumentNumberGenerator
{
    public function nextInvoiceNumber(int $companyId): string
    {
        return $this->numberFromCount(
            prefix: 'INV',
            count: Invoice::query()->where('company_id', $companyId)->count() + 1,
            padding: 4,
        );
    }

    public function nextPurchaseNumber(int $companyId): string
    {
        return $this->numberFromCount(
            prefix: 'PUR',
            count: Purchase::query()->where('company_id', $companyId)->count() + 1,
            padding: 4,
        );
    }

    public function nextExpenseNumber(int $companyId): string
    {
        return $this->numberFromCount(
            prefix: 'EXP',
            count: Expense::query()->where('company_id', $companyId)->count() + 1,
            padding: 4,
        );
    }

    public function nextJournalEntryNumber(int $companyId): string
    {
        return $this->numberFromCount(
            prefix: 'JRN',
            count: JournalEntry::query()->where('company_id', $companyId)->count() + 1,
            padding: 5,
        );
    }

    public function nextSupplierPaymentNumber(int $companyId): string
    {
        return $this->numberFromCount(
            prefix: 'SUP-PMT',
            count: JournalEntry::query()
                ->where('company_id', $companyId)
                ->where('source_type', Supplier::class . ':payment')
                ->count() + 1,
            padding: 5,
        );
    }

    public function nextPurchasePaymentNumber(int $companyId): string
    {
        return $this->numberFromCount(
            prefix: 'PUR-PMT',
            count: Payment::query()
                ->where('company_id', $companyId)
                ->where('payment_category', 'purchase_payment')
                ->count() + 1,
            padding: 5,
        );
    }

    private function numberFromCount(string $prefix, int $count, int $padding): string
    {
        return $prefix . '-' . now()->format('Y') . '-' . str_pad((string) $count, $padding, '0', STR_PAD_LEFT);
    }
}
