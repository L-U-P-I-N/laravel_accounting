<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking if accounting entries are being created...\n\n";

$entries = App\Models\JournalEntry::with('lines.account')->latest()->take(3)->get();

foreach ($entries as $entry) {
    echo "Entry: " . $entry->description . " (" . $entry->entry_date . ")\n";
    foreach ($entry->lines as $line) {
        echo "  Account: " . $line->account->code . " - " . $line->account->name . "\n";
        echo "  Debit: " . $line->debit . ", Credit: " . $line->credit . "\n";
        echo "  Account Balance: " . $line->account->balance . "\n";
        echo "  ---\n";
    }
    echo "\n";
}

echo "\nChecking inventory accounts:\n";
$inventoryAccounts = App\Models\Account::where('code', 'like', '1106%')->get();
foreach ($inventoryAccounts as $acc) {
    echo "Account: " . $acc->code . " - " . $acc->name . ", Balance: " . $acc->balance . "\n";
}

echo "\nChecking revenue accounts:\n";
$revenueAccounts = App\Models\Account::where('code', 'like', '4101%')->get();
foreach ($revenueAccounts as $acc) {
    echo "Account: " . $acc->code . " - " . $acc->name . ", Balance: " . $acc->balance . "\n";
}

echo "\nChecking bank accounts:\n";
$bankAccounts = App\Models\Account::where('code', 'like', '110201%')->get();
foreach ($bankAccounts as $acc) {
    echo "Account: " . $acc->code . " - " . $acc->name . ", Balance: " . $acc->balance . "\n";
}
