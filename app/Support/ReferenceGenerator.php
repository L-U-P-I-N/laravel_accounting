<?php

namespace App\Support;

class ReferenceGenerator
{
    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
    ) {
    }

    public function fromIdentifier(string $identifier): string
    {
        return 'REF-' . preg_replace('/^(.+)-\d{4}-(\d+)$/', '$1-$2', $identifier);
    }

    public function nextExpenseReference(int $companyId): string
    {
        return $this->fromIdentifier($this->documentNumberGenerator->nextExpenseNumber($companyId));
    }

    public function nextJournalReference(int $companyId): string
    {
        return $this->fromIdentifier($this->documentNumberGenerator->nextJournalEntryNumber($companyId));
    }

    public function nextSupplierPaymentReference(int $companyId): string
    {
        return $this->fromIdentifier($this->documentNumberGenerator->nextSupplierPaymentNumber($companyId));
    }

    public function nextPurchasePaymentReference(int $companyId): string
    {
        return $this->fromIdentifier($this->documentNumberGenerator->nextPurchasePaymentNumber($companyId));
    }
}
