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
        return $this->numberFromExisting(
            prefix: 'INV',
            padding: 4,
            latestIdentifier: Invoice::query()
                ->where('company_id', $companyId)
                ->where('invoice_number', 'like', 'INV-' . now()->format('Y') . '-%')
                ->orderByDesc('invoice_number')
                ->value('invoice_number'),
        );
    }

    public function nextPurchaseNumber(int $companyId): string
    {
        return $this->numberFromExisting(
            prefix: 'PUR',
            padding: 4,
            latestIdentifier: Purchase::query()
                ->where('company_id', $companyId)
                ->where('purchase_number', 'like', 'PUR-' . now()->format('Y') . '-%')
                ->orderByDesc('purchase_number')
                ->value('purchase_number'),
        );
    }

    public function nextExpenseNumber(int $companyId): string
    {
        return $this->numberFromExisting(
            prefix: 'EXP',
            padding: 4,
            latestIdentifier: Expense::query()
                ->where('company_id', $companyId)
                ->where('expense_number', 'like', 'EXP-' . now()->format('Y') . '-%')
                ->orderByDesc('expense_number')
                ->value('expense_number'),
        );
    }

    public function nextJournalEntryNumber(int $companyId): string
    {
        return $this->numberFromExisting(
            prefix: 'JRN',
            padding: 5,
            latestIdentifier: JournalEntry::query()
                ->where('company_id', $companyId)
                ->where('entry_number', 'like', 'JRN-' . now()->format('Y') . '-%')
                ->orderByDesc('entry_number')
                ->value('entry_number'),
        );
    }

    public function nextSupplierPaymentNumber(int $companyId): string
    {
        $latestSequence = JournalEntry::query()
            ->where('company_id', $companyId)
            ->where('source_type', Supplier::class . ':payment')
            ->where(function ($query) {
                $query->where('reference', 'like', 'REF-SUP-PMT-%')
                    ->orWhere('reference', 'like', 'SUP-PMT-' . now()->format('Y') . '-%');
            })
            ->pluck('reference')
            ->map(fn (?string $reference) => $this->extractSequence($reference))
            ->max() ?? 0;

        return 'SUP-PMT-' . now()->format('Y') . '-' . str_pad((string) ($latestSequence + 1), 5, '0', STR_PAD_LEFT);
    }

    public function nextPurchasePaymentNumber(int $companyId): string
    {
        return $this->numberFromExisting(
            prefix: 'PUR-PMT',
            padding: 5,
            latestIdentifier: Payment::query()
                ->where('company_id', $companyId)
                ->where('payment_category', 'purchase_payment')
                ->where('reference', 'like', 'PUR-PMT-' . now()->format('Y') . '-%')
                ->orderByDesc('reference')
                ->value('reference'),
        );
    }

    private function numberFromExisting(string $prefix, int $padding, ?string $latestIdentifier): string
    {
        $nextSequence = $this->extractSequence($latestIdentifier) + 1;

        return $prefix . '-' . now()->format('Y') . '-' . str_pad((string) $nextSequence, $padding, '0', STR_PAD_LEFT);
    }

    private function extractSequence(?string $identifier): int
    {
        if (! $identifier) {
            return 0;
        }

        $parts = explode('-', $identifier);
        $sequence = end($parts);

        return ctype_digit((string) $sequence) ? (int) $sequence : 0;
    }
}
