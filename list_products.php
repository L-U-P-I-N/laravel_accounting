<?php

use App\Models\Product;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$companyId = 1;

echo "=== قائمة المنتجات ===\n\n";

$products = Product::where('company_id', $companyId)
    ->where('type', 'product')
    ->get();

foreach ($products as $p) {
    echo "ID: {$p->id} | الاسم: {$p->name} | الكود: {$p->code}\n";
    echo "   الكمية: {$p->stock_quantity} | التكلفة: {$p->cost_price} | القيمة: " . ($p->stock_quantity * $p->cost_price) . " SAR\n\n";
}

echo "عدد المنتجات: " . $products->count() . "\n";
