<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\Account;
use App\Models\JournalLine;
use App\Models\JournalEntry;
use App\Models\InventoryMovement;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$companyId = 1;

echo "--- تحليل تفصيلي لعمليات المخزون والقيود المحاسبية ---\n";

// 1. جلب بيانات المنتجات
$products = Product::where('company_id', $companyId)->where('type', 'product')->get();
$totalProductValue = 0;
foreach ($products as $product) {
    $value = (float) $product->stock_quantity * (float) $product->cost_price;
    $totalProductValue += $value;
    echo "المنتج: {$product->name} (ID: {$product->id}) | الكمية: {$product->stock_quantity} | التكلفة الحالية: {$product->cost_price} | القيمة المحسوبة: " . number_format($value, 2) . "\n";
}
echo "إجمالي قيمة المنتجات (الكمية * التكلفة): " . number_format($totalProductValue, 2) . "\n\n";

// 2. فحص حركات المخزون (InventoryMovements)
$movements = InventoryMovement::where('company_id', $companyId)->get();
$totalMovementValue = 0;
echo "حركات المخزون المسجلة:\n";
foreach ($movements as $m) {
    $qty = (float) $m->quantity;
    $cost = (float) $m->unit_cost;
    $total = (float) $m->total_cost;
    $direction = $m->direction === 'in' ? '+' : '-';
    if ($m->direction === 'in') $totalMovementValue += $total;
    else $totalMovementValue -= $total;
    
    echo "ID: {$m->id} | {$m->movement_type} | {$direction}{$qty} | التكلفة: {$cost} | الإجمالي: {$total} | المصدر: {$m->source_type} #{$m->source_id}\n";
}
echo "إجمالي قيمة حركات المخزون (In - Out): " . number_format($totalMovementValue, 2) . "\n\n";

// 3. فحص القيود المحاسبية المرتبطة بحسابات المخزون
$inventoryAccounts = Account::where('company_id', $companyId)
    ->where(function($q) {
        $q->where('code', '1106')
          ->orWhere('code', 'like', '1106-%');
    })->get();

echo "أرصدة حسابات المخزون في الأستاذ العام:\n";
foreach ($inventoryAccounts as $acc) {
    $lines = JournalLine::where('account_id', $acc->id)
        ->whereHas('journalEntry', fn($q) => $q->where('status', 'posted'))
        ->get();
    
    $debit = $lines->sum('debit');
    $credit = $lines->sum('credit');
    $bal = $debit - $credit;
    echo "الحساب: {$acc->code} - {$acc->name} | مدين: {$debit} | دائن: {$credit} | الرصيد: " . number_format($bal, 2) . "\n";
    
    foreach ($lines as $line) {
        $entry = $line->journalEntry;
        echo "   - قيد #{$entry->entry_number} | نوع: {$entry->entry_type} | مدين: {$line->debit} | دائن: {$line->credit} | وصف: {$line->description}\n";
    }
}
