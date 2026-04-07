<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing accounting entry creation...\n\n";

// Get a test invoice
$invoice = App\Models\Invoice::with(['items.product', 'customer', 'paymentAccount'])->first();

if (!$invoice) {
    echo "No invoices found. Creating a test invoice...\n";
    
    $company = App\Models\Company::first();
    $customer = App\Models\Customer::first();
    $product = App\Models\Product::where('type', 'product')->first();
    
    if (!$company || !$customer || !$product) {
        echo "Missing required data (company, customer, or product)\n";
        exit;
    }
    
    // Create test invoice
    $invoice = App\Models\Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'TEST-001',
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => 100,
        'tax_amount' => 15,
        'total' => 115,
        'status' => 'sent',
        'paid_amount' => 0,
    ]);
    
    // Add item
    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'total' => 100,
        'tax_amount' => 15,
    ]);
    
    echo "Created test invoice: " . $invoice->invoice_number . "\n";
}

echo "Invoice: " . $invoice->invoice_number . " (Status: " . $invoice->status . ")\n";
echo "Total: " . $invoice->total . "\n\n";

// Get user
$user = App\Models\User::first();
if (!$user) {
    echo "No user found!\n";
    exit;
}

// Test accounting entry creation
try {
    $accountingService = app(\App\Support\AccountingService::class);
    $journalEntry = $accountingService->syncInvoiceEntry($invoice, $user);
    
    echo "Created journal entry successfully!\n";
    echo "Entry ID: " . $journalEntry->id . "\n";
    echo "Description: " . $journalEntry->description . "\n";
    echo "Total Debit: " . $journalEntry->total_debit . "\n";
    echo "Total Credit: " . $journalEntry->total_credit . "\n\n";
    
    echo "Entry lines:\n";
    foreach ($journalEntry->lines as $line) {
        echo "  Account: " . $line->account->code . " - " . $line->account->name . "\n";
        echo "  Debit: " . $line->debit . ", Credit: " . $line->credit . "\n";
        echo "  Account Balance: " . $line->account->balance . "\n";
        echo "  ---\n";
    }
    
} catch (Exception $e) {
    echo "Error creating journal entry: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\nChecking updated account balances:\n";
$accounts = App\Models\Account::whereIn('code', ['1106', '4101', '110201', '1103', '5101'])->get();
foreach ($accounts as $acc) {
    echo "Account: " . $acc->code . " - " . $acc->name . ", Balance: " . $acc->balance . "\n";
}
