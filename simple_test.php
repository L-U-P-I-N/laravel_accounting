<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Simple accounting test...\n\n";

$company = App\Models\Company::first();
$user = App\Models\User::first();

// Get accounts directly from database
$inventoryAccount = App\Models\Account::where('company_id', $company->id)
    ->where('code', '1106')
    ->first();

$payablesAccount = App\Models\Account::where('company_id', $company->id)
    ->where('code', '2101')
    ->first();

if (!$inventoryAccount || !$payablesAccount) {
    echo "Accounts not found!\n";
    exit;
}

echo "Using accounts:\n";
echo "  Inventory: " . $inventoryAccount->code . " - " . $inventoryAccount->name . " (Balance: " . $inventoryAccount->balance . ")\n";
echo "  Payables: " . $payablesAccount->code . " - " . $payablesAccount->name . " (Balance: " . $payablesAccount->balance . ")\n\n";

// Create journal entry manually
$entry = App\Models\JournalEntry::create([
    'entry_number' => 'TEST-001',
    'entry_date' => now(),
    'description' => 'Test purchase entry',
    'reference' => 'TEST-001',
    'source_type' => 'test',
    'source_id' => 1,
    'entry_type' => 'purchase',
    'entry_origin' => 'automatic',
    'status' => 'posted',
    'total_debit' => 57.50,
    'total_credit' => 57.50,
    'company_id' => $company->id,
    'created_by' => $user->id,
    'posted_by' => $user->id,
    'posted_at' => now(),
]);

echo "Created journal entry: " . $entry->id . "\n";

// Create lines
$entry->lines()->create([
    'account_id' => $inventoryAccount->id,
    'description' => 'Test inventory purchase',
    'debit' => 57.50,
    'credit' => 0,
]);

$entry->lines()->create([
    'account_id' => $payablesAccount->id,
    'description' => 'Test payables for purchase',
    'debit' => 0,
    'credit' => 57.50,
]);

echo "Created journal lines\n";

// Update account balances
$inventoryAccount->updateBalance(57.50, 0);
$payablesAccount->updateBalance(0, 57.50);

echo "Updated account balances\n\n";

echo "New balances:\n";
echo "  Inventory: " . $inventoryAccount->fresh()->balance . "\n";
echo "  Payables: " . $payablesAccount->fresh()->balance . "\n";

echo "\nTest completed successfully!\n";
