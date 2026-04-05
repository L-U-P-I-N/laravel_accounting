<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Supplier;

class PaymentSyncService
{
    public function syncInvoicePayment(Invoice $invoice, ?JournalEntry $entry = null): void
    {
        Payment::query()->where('invoice_id', $invoice->id)->delete();

        if ((float) $invoice->paid_amount <= 0) {
            return;
        }

        Payment::create([
            'company_id' => (int) $invoice->company_id,
            'invoice_id' => (int) $invoice->id,
            'purchase_id' => null,
            'customer_id' => $invoice->customer_id,
            'supplier_id' => null,
            'payment_account_id' => $invoice->payment_account_id,
            'journal_entry_id' => $entry?->id,
            'amount' => round((float) $invoice->paid_amount, 2),
            'payment_direction' => 'in',
            'payment_category' => 'invoice_receipt',
            'payment_date' => $invoice->invoice_date,
            'reference' => $invoice->invoice_number,
            'notes' => 'دفعة مرتبطة بفاتورة المبيعات ' . $invoice->invoice_number,
        ]);
    }

    public function syncPurchasePayment(Purchase $purchase, ?JournalEntry $entry = null): void
    {
        $expectedAmount = round((float) $purchase->paid_amount, 2);
        $purchasePayments = Payment::query()
            ->where('purchase_id', $purchase->id)
            ->where('payment_category', 'purchase_payment')
            ->orderBy('id')
            ->get();

        if ($expectedAmount <= 0) {
            $purchasePayments->each->delete();
            return;
        }

        if ($purchasePayments->count() > 1) {
            return;
        }

        Payment::query()->updateOrCreate(
            [
                'purchase_id' => (int) $purchase->id,
                'payment_category' => 'purchase_payment',
            ],
            [
                'company_id' => (int) $purchase->company_id,
                'invoice_id' => null,
                'customer_id' => null,
                'supplier_id' => $purchase->supplier_id,
                'payment_account_id' => $purchase->payment_account_id,
                'journal_entry_id' => $entry?->id,
                'amount' => $expectedAmount,
                'payment_direction' => 'out',
                'payment_date' => $purchase->payment_date ?: $purchase->purchase_date,
                'reference' => $purchase->purchase_number,
                'notes' => 'دفعة مرتبطة بطلب الشراء ' . $purchase->purchase_number,
            ]
        );
    }

    public function recordPurchasePayment(Purchase $purchase, float $amount, string $paymentDate, string $reference, ?JournalEntry $entry = null, ?int $paymentAccountId = null, ?string $notes = null): Payment
    {
        return Payment::query()->create([
            'company_id' => (int) $purchase->company_id,
            'invoice_id' => null,
            'purchase_id' => (int) $purchase->id,
            'customer_id' => null,
            'supplier_id' => $purchase->supplier_id,
            'payment_account_id' => $paymentAccountId,
            'journal_entry_id' => $entry?->id,
            'amount' => round($amount, 2),
            'payment_direction' => 'out',
            'payment_category' => 'purchase_payment',
            'payment_date' => $paymentDate,
            'reference' => $reference,
            'notes' => $notes ?: 'دفعة شراء على الطلب ' . $purchase->purchase_number,
        ]);
    }

    public function recordSupplierPayment(Supplier $supplier, float $amount, string $paymentDate, string $reference, ?JournalEntry $entry = null, ?int $paymentAccountId = null): Payment
    {
        return Payment::query()->updateOrCreate(
            ['journal_entry_id' => $entry?->id],
            [
                'company_id' => (int) $supplier->company_id,
                'invoice_id' => null,
                'purchase_id' => null,
                'customer_id' => null,
                'supplier_id' => (int) $supplier->id,
                'payment_account_id' => $paymentAccountId,
                'amount' => round($amount, 2),
                'payment_direction' => 'out',
                'payment_category' => 'supplier_payment',
                'payment_date' => $paymentDate,
                'reference' => $reference,
                'notes' => 'سداد مورد: ' . $supplier->name,
            ]
        );
    }

    public function deletePurchasePayments(Purchase $purchase): void
    {
        Payment::query()->where('purchase_id', $purchase->id)->delete();
    }

    public function deleteInvoicePayments(Invoice $invoice): void
    {
        Payment::query()->where('invoice_id', $invoice->id)->delete();
    }
}
