<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\Account;
use App\Models\JournalLine;
use App\Models\JournalEntry;
use App\Models\InventoryMovement;
use App\Models\Purchase;
use App\Models\Invoice;
use App\Support\AccountingService;
use App\Support\InventoryMovementService;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$companyId = 1;
$user = \App\Models\User::first();

echo "--- إعادة بناء أرصدة المخزون والقيود المحاسبية للمنتجات الحالية ---\n";

$products = Product::where('company_id', $companyId)->where('type', 'product')->get();
$accountingService = app(AccountingService::class);
$inventoryMovementService = app(InventoryMovementService::class);

foreach ($products as $product) {
    echo "جاري معالجة المنتج: {$product->name} (ID: {$product->id})\n";
    
    // 1. إعادة مزامنة حسابات المنتج (المخزون و COGS)
    app(\App\Support\ChartOfAccountsSynchronizer::class)->syncProductAccounts($product);
    $product->refresh();
    
    echo "   - حساب المخزون: {$product->inventoryAccount?->code}\n";
    echo "   - حساب COGS: {$product->cogsAccount?->code}\n";
}

// 2. إعادة معالجة جميع فواتير الشراء (المشتريات تزيد المخزون)
$purchases = Purchase::where('company_id', $companyId)->get();
foreach ($purchases as $purchase) {
    echo "إعادة معالجة طلب شراء #{$purchase->purchase_number}\n";
    $accountingService->syncPurchaseEntry($purchase, $user);
    $inventoryMovementService->syncPurchase($purchase);
}

// 3. إعادة معالجة جميع فواتير البيع (المبيعات تخفض المخزون بالتكلفة)
$invoices = Invoice::where('company_id', $companyId)->get();
foreach ($invoices as $invoice) {
    echo "إعادة معالجة فاتورة مبيعات #{$invoice->invoice_number}\n";
    $accountingService->syncInvoiceEntry($invoice, $user);
    $inventoryMovementService->syncInvoice($invoice);
}

echo "\n--- انتهت عملية إعادة المزامنة ---\n";
echo "يرجى تشغيل debug_inventory.php للتأكد من النتائج.\n";
