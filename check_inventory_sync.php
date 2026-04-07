<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\Account;
use App\Models\JournalLine;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$companyId = 1; // افترض أن معرف الشركة هو 1، قم بتغييره إذا لزم الأمر

echo "--- فحص مطابقة رصيد المخزون في شجرة الحسابات مع بيانات المنتجات ---\n";

$company = Company::find($companyId);
if (!$company) {
    echo "الشركة غير موجودة.\n";
    exit;
}

// 1. حساب القيمة الإجمالية من جدول المنتجات (الكمية * التكلفة)
$products = Product::where('company_id', $companyId)->where('type', 'product')->get();
$totalProductValue = 0;
foreach ($products as $product) {
    $value = (float) $product->stock_quantity * (float) $product->cost_price;
    $totalProductValue += $value;
    echo "المنتج: {$product->name} | الكمية: {$product->stock_quantity} | التكلفة: {$product->cost_price} | الإجمالي: {$value}\n";
}

echo "\nإجمالي قيمة المخزون من جدول المنتجات: " . number_format($totalProductValue, 2) . "\n";

// 2. جلب رصيد حساب المخزون الرئيسي (1106) من واقع القيود المحاسبية
$inventoryAccount = Account::where('company_id', $companyId)->where('code', '1106')->first();

if ($inventoryAccount) {
    $balanceData = JournalLine::query()
        ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
        ->where('journal_entries.company_id', $companyId)
        ->where('journal_entries.status', 'posted')
        ->where(function($query) use ($inventoryAccount) {
            $query->where('account_id', $inventoryAccount->id)
                  ->orWhereIn('account_id', Account::where('parent_id', $inventoryAccount->id)->pluck('id'));
        })
        ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
        ->first();

    $accountBalance = (float) $balanceData->total_debit - (float) $balanceData->total_credit;
    echo "رصيد حساب المخزون (1106) من واقع القيود: " . number_format($accountBalance, 2) . "\n";
    
    $diff = abs($totalProductValue - $accountBalance);
    if ($diff < 0.01) {
        echo "النتيجة: متطابق ✅\n";
    } else {
        echo "النتيجة: غير متطابق ❌ | الفرق: " . number_format($diff, 2) . "\n";
        echo "تنبيه: يجب التأكد من ترحيل جميع الفواتير ومن أن تكلفة المنتج في الحركات مطابقة لتكلفة المنتج الحالية.\n";
    }
} else {
    echo "حساب المخزون (1106) غير موجود.\n";
}
