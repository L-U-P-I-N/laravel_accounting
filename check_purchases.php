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

echo "=== فحص طلبات الشراء ===\n\n";

$purchases = Purchase::where('company_id', $companyId)->with('items.product')->get();

foreach ($purchases as $purchase) {
    echo "طلب شراء #{$purchase->purchase_number}:\n";
    echo "   - الإجمالي: {$purchase->total}\n";
    echo "   - الضريبة: {$purchase->tax_amount}\n";
    echo "   - الصافي: {$purchase->subtotal}\n";
    echo "   البنود:\n";
    
    foreach ($purchase->items as $item) {
        $productName = $item->product ? $item->product->name : 'N/A';
        $productCost = $item->product ? $item->product->cost_price : 'N/A';
        echo "      * المنتج: {$productName} (ID: {$item->product_id})\n";
        echo "        الكمية: {$item->quantity}, سعر الوحدة: {$item->unit_price}, الإجمالي: {$item->total}\n";
        echo "        تكلفة المنتج الحالية: {$productCost}\n";
    }
    echo "\n";
}

// مقارنة الكميات
echo "=== مقارنة الكميات ===\n";
$products = Product::where('company_id', $companyId)->where('type', 'product')->get();
foreach ($products as $product) {
    $purchasedQty = PurchaseItem::where('product_id', $product->id)
        ->whereHas('purchase', function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })
        ->sum('quantity');
    
    echo "{$product->name}:\n";
    echo "   - الكمية في جدول المنتجات: {$product->stock_quantity}\n";
    echo "   - الكمية المشتراة (من فواتير الشراء): {$purchasedQty}\n";
    echo "   - التكلفة: {$product->cost_price}\n";
    echo "   - القيمة المتوقعة: " . ($product->stock_quantity * $product->cost_price) . "\n";
    echo "   - القيمة من المشتريات: " . ($purchasedQty * $product->cost_price) . "\n\n";
}
