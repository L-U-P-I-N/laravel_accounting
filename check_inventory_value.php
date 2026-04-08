<?php

use App\Models\Company;
use App\Models\Product;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$companyId = 1;

echo "=== فحص قيمة المخزون ===\n\n";

$products = Product::where('company_id', $companyId)
    ->where('type', 'product')
    ->get();

$totalValue = 0;

foreach ($products as $p) {
    $productValue = (float) $p->stock_quantity * (float) $p->cost_price;
    $totalValue += $productValue;
    
    echo "المنتج: {$p->name}\n";
    echo "   الكمية: {$p->stock_quantity}\n";
    echo "   التكلفة: {$p->cost_price} SAR\n";
    echo "   القيمة: {$productValue} SAR\n\n";
}

echo "========================================\n";
echo "📦 إجمالي قيمة المخزون: {$totalValue} SAR\n";
echo "========================================\n";

// التحقق من الاستعلام المستخدم في Controller
$inventoryValueFromQuery = \App\Models\Product::query()
    ->where('company_id', $companyId)
    ->where('type', 'product')
    ->selectRaw('SUM(stock_quantity * cost_price) as total_value')
    ->value('total_value') ?? 0;

echo "\nقيمة الاستعلام المباشر: {$inventoryValueFromQuery} SAR\n";
