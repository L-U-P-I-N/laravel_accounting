<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$companyId = 1;

echo "=== إجمالي المشتريات (SAR) ===\n\n";

// جلب جميع طلبات الشراء
$purchases = Purchase::where('company_id', $companyId)->with('items.product')->get();

$grandTotal = 0;
$totalProducts = 0;

foreach ($purchases as $purchase) {
    echo "طلب شراء #{$purchase->purchase_number}:\n";
    echo "   التاريخ: {$purchase->purchase_date}\n";
    echo "   المورد: " . ($purchase->supplier ? $purchase->supplier->name : 'غير محدد') . "\n";
    
    $purchaseTotal = 0;
    $purchaseItemsCount = 0;
    
    echo "   البنود:\n";
    foreach ($purchase->items as $item) {
        $productName = $item->product ? $item->product->name : 'منتج محذوف';
        $lineValue = (float) $item->quantity * (float) $item->unit_price;
        $purchaseTotal += $lineValue;
        $purchaseItemsCount += $item->quantity;
        
        echo "      - {$productName}\n";
        echo "        الكمية: {$item->quantity} × {$item->unit_price} = {$lineValue} SAR\n";
    }
    
    echo "   -----------------------------------\n";
    echo "   إجمالي الطلب: {$purchaseTotal} SAR\n";
    echo "   الضريبة: {$purchase->tax_amount} SAR\n";
    echo "   الإجمالي مع الضريبة: {$purchase->total} SAR\n\n";
    
    $grandTotal += $purchaseTotal;
    $totalProducts += $purchaseItemsCount;
}

echo "========================================\n";
echo "📊 ملخص المشتريات:\n";
echo "========================================\n";
echo "عدد طلبات الشراء: {$purchases->count()}\n";
echo "إجمالي المنتجات المشتراة: {$totalProducts} وحدة\n";
echo "إجمالي قيمة المشتريات (بدون ضريبة): {$grandTotal} SAR\n";

// حساب إجمالي المشتريات شامل الضريبة
$totalWithTax = $purchases->sum('total');
echo "إجمالي المشتريات (مع الضريبة): {$totalWithTax} SAR\n";
echo "========================================\n";

// مقارنة مع قيمة المخزون المتوقعة
$products = Product::where('company_id', $companyId)->where('type', 'product')->get();
$inventoryValue = 0;
foreach ($products as $p) {
    $inventoryValue += (float) $p->stock_quantity * (float) $p->cost_price;
}

echo "\n📦 قيمة المخزون الحالية (الكمية × التكلفة): {$inventoryValue} SAR\n";
echo "💡 الفرق: " . ($inventoryValue - $grandTotal) . " SAR\n";
