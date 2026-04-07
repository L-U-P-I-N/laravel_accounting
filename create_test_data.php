<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Creating test data for accounting...\n\n";

try {
    // Get company and user
    $company = App\Models\Company::first();
    $user = App\Models\User::first();
    
    if (!$company || !$user) {
        echo "No company or user found!\n";
        exit;
    }
    
    echo "Company: " . $company->name . "\n";
    echo "User: " . $user->name . "\n\n";
    
    // Sync chart of accounts first
    $syncService = app(\App\Support\ChartOfAccountsSynchronizer::class);
    $syncService->synchronizeCompany($company);
    echo "Synced chart of accounts\n\n";
    
    // Create customer
    $customer = App\Models\Customer::create([
        'company_id' => $company->id,
        'name' => 'Test Customer',
        'name_ar' => 'عميل اختبار',
        'email' => 'test' . time() . '@example.com',
        'phone' => '123456789',
        'address' => 'Test Address',
        'is_active' => true,
    ]);
    echo "Created customer: " . $customer->name . "\n";
    
    // Create supplier
    $supplier = App\Models\Supplier::create([
        'company_id' => $company->id,
        'name' => 'Test Supplier',
        'name_ar' => 'مورد اختبار',
        'email' => 'supplier' . time() . '@example.com',
        'phone' => '987654321',
        'address' => 'Supplier Address',
        'is_active' => true,
    ]);
    echo "Created supplier: " . $supplier->name . "\n";
    
    // Create product
    $product = App\Models\Product::create([
        'company_id' => $company->id,
        'name' => 'Test Product',
        'name_ar' => 'منتج اختبار',
        'type' => 'product',
        'sku' => 'TEST-001',
        'cost_price' => 50,
        'unit_price' => 100,
        'stock_quantity' => 10,
        'is_active' => true,
    ]);
    echo "Created product: " . $product->name . " (Cost: " . $product->cost_price . ", Price: " . $product->unit_price . ")\n";
    
    // Sync product accounts
    $syncService->syncProductAccounts($product);
    echo "Synced product accounts\n\n";
    
    // Create purchase invoice
    $purchase = App\Models\Purchase::create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'purchase_number' => 'PUR-001',
        'purchase_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => 50,
        'tax_amount' => 7.5,
        'total' => 57.5,
        'status' => 'received',
        'paid_amount' => 0,
    ]);
    
    // Add item to purchase
    $purchase->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 50,
        'total' => 50,
        'tax_amount' => 7.5,
        'description' => 'Test Product Purchase',
    ]);
    
    echo "Created purchase: " . $purchase->purchase_number . "\n";
    
    // Create accounting entry for purchase
    $accountingService = app(\App\Support\AccountingService::class);
    $purchaseEntry = $accountingService->syncPurchaseEntry($purchase, $user);
    echo "Created purchase journal entry\n";
    
    // Create sales invoice
    $invoice = App\Models\Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-001',
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => 100,
        'tax_amount' => 15,
        'total' => 115,
        'status' => 'sent',
        'paid_amount' => 0,
    ]);
    
    // Add item to invoice
    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'total' => 100,
        'tax_amount' => 15,
        'description' => 'Test Product Sale',
    ]);
    
    echo "Created invoice: " . $invoice->invoice_number . "\n";
    
    // Create accounting entry for invoice
    $invoiceEntry = $accountingService->syncInvoiceEntry($invoice, $user);
    echo "Created invoice journal entry\n\n";
    
    // Check account balances
    echo "Account balances after transactions:\n";
    
    $inventoryAccount = $accountingService->inventoryAccountForProduct($product, $company->id);
    echo "Inventory Account (" . $inventoryAccount->code . "): " . $inventoryAccount->balance . "\n";
    
    $cogsAccount = $accountingService->cogsAccountForProduct($product, $company->id);
    echo "COGS Account (" . $cogsAccount->code . "): " . $cogsAccount->balance . "\n";
    
    $revenueAccount = $accountingService->revenueAccountForProduct($product, $company->id);
    echo "Revenue Account (" . $revenueAccount->code . "): " . $revenueAccount->balance . "\n";
    
    $bankAccount = $accountingService->bankAccount($company->id);
    echo "Bank Account (" . $bankAccount->code . "): " . $bankAccount->balance . "\n";
    
    $receivablesAccount = $accountingService->customerReceivableAccount($invoice);
    echo "Receivables Account (" . $receivablesAccount->code . "): " . $receivablesAccount->balance . "\n";
    
    $payablesAccount = $accountingService->supplierPayableAccount($purchase);
    echo "Payables Account (" . $payablesAccount->code . "): " . $payablesAccount->balance . "\n";
    
    echo "\nTest completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
