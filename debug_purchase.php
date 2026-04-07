<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Debugging purchase entry creation...\n\n";

$purchase = App\Models\Purchase::with(['items.product', 'supplier'])->first();
$user = App\Models\User::first();

echo "Purchase: " . $purchase->purchase_number . "\n";
echo "Total: " . $purchase->total . "\n";
echo "Tax: " . $purchase->tax_amount . "\n";
echo "Subtotal: " . $purchase->subtotal . "\n";
echo "Paid: " . $purchase->paid_amount . "\n\n";

echo "Items:\n";
foreach ($purchase->items as $item) {
    echo "  Product: " . $item->product->name . "\n";
    echo "  Quantity: " . $item->quantity . "\n";
    echo "  Unit Price: " . $item->unit_price . "\n";
    echo "  Total: " . $item->total . "\n";
    echo "  Tax: " . $item->tax_amount . "\n";
    echo "  Net: " . ($item->total - $item->tax_amount) . "\n\n";
}

// Simple test - create a basic purchase entry manually
echo "Creating manual balanced entry...\n";

$accountingService = app(\App\Support\AccountingService::class);
$lines = collect();

// Get inventory account
$inventoryAccount = $accountingService->inventoryAccount((int) $purchase->company_id);
echo "Using inventory account: " . $inventoryAccount->code . " - " . $inventoryAccount->name . "\n";

// Add inventory debit
$lines->push([
    'account' => $inventoryAccount,
    'description' => 'Test inventory purchase',
    'debit' => (float) $purchase->total,
    'credit' => 0,
]);

// Add payables credit
$payablesAccount = $accountingService->payablesAccount((int) $purchase->company_id);
echo "Using payables account: " . $payablesAccount->code . " - " . $payablesAccount->name . "\n";

$lines->push([
    'account' => $payablesAccount,
    'description' => 'Test payables for purchase',
    'debit' => 0,
    'credit' => (float) $purchase->total,
]);

echo "\nEntry lines:\n";
$totalDebit = 0;
$totalCredit = 0;
foreach ($lines as $line) {
    echo "  " . $line['account']->code . ": Debit " . $line['debit'] . ", Credit " . $line['credit'] . "\n";
    $totalDebit += $line['debit'];
    $totalCredit += $line['credit'];
}

echo "\nSummary:\n";
echo "  Total Debit: " . $totalDebit . "\n";
echo "  Total Credit: " . $totalCredit . "\n";
echo "  Balanced: " . ($totalDebit == $totalCredit ? "YES" : "NO") . "\n";

if ($totalDebit == $totalCredit) {
    echo "\nCreating journal entry...\n";
    try {
        $entry = $accountingService->syncJournalEntry(
            companyId: (int) $purchase->company_id,
            user: $user,
            source: $purchase,
            entryType: 'purchase',
            description: 'Test purchase entry',
            reference: $purchase->purchase_number,
            entryDate: $purchase->purchase_date,
            lines: $lines
        );
        echo "Success! Entry ID: " . $entry->id . "\n";
        
        echo "\nUpdated account balances:\n";
        echo "  Inventory (" . $inventoryAccount->code . "): " . $inventoryAccount->fresh()->balance . "\n";
        echo "  Payables (" . $payablesAccount->code . "): " . $payablesAccount->fresh()->balance . "\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
