<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\Account;
use App\Models\JournalLine;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$companyId = 1;

echo "=== تشخيص شامل لمشكلة رصيد المخزون ===\n\n";

// 1. قيمة المنتجات المتوقعة
$products = Product::where('company_id', $companyId)->where('type', 'product')->get();
$expectedInventoryValue = 0;
echo "1. بيانات المنتجات:\n";
foreach ($products as $p) {
    $val = (float)$p->stock_quantity * (float)$p->cost_price;
    $expectedInventoryValue += $val;
    echo "   - {$p->name}: qty={$p->stock_quantity}, cost={$p->cost_price}, value={$val}\n";
}
echo "   إجمالي القيمة المتوقعة: " . number_format($expectedInventoryValue, 2) . "\n\n";

// 2. فحص حسابات المخزون
$inventoryAccounts = Account::where('company_id', $companyId)
    ->where(function($q) {
        $q->where('code', '1106')
          ->orWhere('code', 'like', '1106-%');
    })
    ->get();

echo "2. حسابات المخزون في النظام:\n";
foreach ($inventoryAccounts as $acc) {
    echo "   - {$acc->code}: {$acc->name} (ID: {$acc->id}, Parent: {$acc->parent_id})\n";
}
echo "\n";

// 3. فحص القيود المحاسبية (Journal Entries) المرتبطة بحسابات المخزون
$accountIds = $inventoryAccounts->pluck('id')->toArray();

echo "3. القيود المحاسبية في جدول journal_lines:\n";
$lines = JournalLine::whereIn('account_id', $accountIds)
    ->whereHas('journalEntry', function($q) use ($companyId) {
        $q->where('company_id', $companyId)->where('status', 'posted');
    })
    ->with('journalEntry', 'account')
    ->get();

if ($lines->isEmpty()) {
    echo "   ⚠️ لا توجد قيود محاسبية مسجلة لحسابات المخزون!\n";
} else {
    foreach ($lines as $line) {
        echo "   - Entry #{$line->journalEntry->entry_number}: Account {$line->account->code}, Debit={$line->debit}, Credit={$line->credit}, Desc: {$line->description}\n";
    }
}
echo "\n";

// 4. حساب الأرصدة من القيود
$balancesFromJournals = [];
foreach ($accountIds as $accId) {
    $accLines = JournalLine::where('account_id', $accId)
        ->whereHas('journalEntry', function($q) use ($companyId) {
            $q->where('company_id', $companyId)->where('status', 'posted');
        })
        ->get();
    
    $debit = $accLines->sum('debit');
    $credit = $accLines->sum('credit');
    $balance = $debit - $credit;
    $balancesFromJournals[$accId] = $balance;
    
    $acc = $inventoryAccounts->firstWhere('id', $accId);
    echo "4. رصيد حساب {$acc->code} من القيود: " . number_format($balance, 2) . " (مدين: {$debit}, دائن: {$credit})\n";
}

// 5. حساب rolled_up_balance للحساب الرئيسي (1106)
$parentAccount = $inventoryAccounts->firstWhere('code', '1106');
if ($parentAccount) {
    echo "\n5. حساب rolled_up_balance للحساب الرئيسي (1106):\n";
    $childAccounts = $inventoryAccounts->where('parent_id', $parentAccount->id);
    $rolledUpBalance = $balancesFromJournals[$parentAccount->id] ?? 0;
    
    echo "   - رصيد الحساب الرئيسي نفسه: " . number_format($rolledUpBalance, 2) . "\n";
    
    foreach ($childAccounts as $child) {
        $childBalance = $balancesFromJournals[$child->id] ?? 0;
        echo "   - رصيد الحساب الفرعي {$child->code}: " . number_format($childBalance, 2) . "\n";
        $rolledUpBalance += $childBalance;
    }
    
    echo "   =====================================\n";
    echo "   rolled_up_balance الإجمالي: " . number_format($rolledUpBalance, 2) . "\n";
    echo "   القيمة المتوقعة من المنتجات: " . number_format($expectedInventoryValue, 2) . "\n";
    
    if (abs($rolledUpBalance - $expectedInventoryValue) < 0.01) {
        echo "   ✅ المطابقة: متطابق\n";
    } else {
        echo "   ❌ الفرق: " . number_format($expectedInventoryValue - $rolledUpBalance, 2) . "\n";
    }
}

echo "\n=== نهاية التشخيص ===\n";
