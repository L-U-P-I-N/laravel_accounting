<?php

use App\Models\Account;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$companyId = 1;

echo "=== حذف حساب 110601 ===\n\n";

// البحث عن حساب 110601
$account = Account::where('company_id', $companyId)
    ->where('code', '110601')
    ->first();

if (!$account) {
    echo "حساب 110601 غير موجود\n";
    exit;
}

echo "تم العثور على الحساب:\n";
echo "   ID: {$account->id}\n";
echo "   الاسم: {$account->name}\n";
echo "   الكود: {$account->code}\n";
echo "   الرصيد: {$account->balance} SAR\n\n";

// حذف الحساب
$account->delete();

echo "✅ تم حذف الحساب بنجاح\n";
