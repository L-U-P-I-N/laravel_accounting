<?php

namespace App\Support;

use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Model;

class InventoryMovementService
{
    public function syncInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing(['items.product']);

        $this->deleteForSource($invoice);

        foreach ($invoice->items as $item) {
            if (! $item->product || $item->product->type !== 'product' || (float) $item->quantity <= 0) {
                continue;
            }

            $quantity = round((float) $item->quantity, 2);
            $unitCost = round((float) $item->product->cost_price, 2);

            InventoryMovement::create([
                'company_id' => (int) $invoice->company_id,
                'product_id' => (int) $item->product_id,
                'movement_type' => 'invoice_issue',
                'direction' => 'out',
                'source_type' => $invoice::class,
                'source_id' => (int) $invoice->id,
                'reference_number' => $invoice->invoice_number,
                'movement_date' => $invoice->invoice_date,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => round($quantity * $unitCost, 2),
                'notes' => 'حركة صرف مخزون مرتبطة بالفاتورة ' . $invoice->invoice_number,
            ]);
        }
    }

    public function syncPurchase(Purchase $purchase): void
    {
        $purchase->loadMissing(['items.product']);

        $this->deleteForSource($purchase);

        foreach ($purchase->items as $item) {
            if (! $item->product || $item->product->type !== 'product' || (float) $item->quantity <= 0) {
                continue;
            }

            $quantity = round((float) $item->quantity, 2);
            $unitCost = round((float) $item->unit_price, 2);

            InventoryMovement::create([
                'company_id' => (int) $purchase->company_id,
                'product_id' => (int) $item->product_id,
                'movement_type' => 'purchase_receipt',
                'direction' => 'in',
                'source_type' => $purchase::class,
                'source_id' => (int) $purchase->id,
                'reference_number' => $purchase->purchase_number,
                'movement_date' => $purchase->purchase_date,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => round($quantity * $unitCost, 2),
                'notes' => 'حركة إدخال مخزون مرتبطة بطلب الشراء ' . $purchase->purchase_number,
            ]);
        }
    }

    public function deleteForSource(Model $source): void
    {
        InventoryMovement::query()
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->delete();
    }

    public function hasMovementsForSource(Model $source): bool
    {
        return InventoryMovement::query()
            ->where('source_type', $source::class)
            ->where('source_id', $source->getKey())
            ->exists();
    }
}
