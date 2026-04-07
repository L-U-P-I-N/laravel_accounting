<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking database data...\n\n";

$companies = App\Models\Company::count();
echo "Companies: " . $companies . "\n";

$customers = App\Models\Customer::count();
echo "Customers: " . $customers . "\n";

$suppliers = App\Models\Supplier::count();
echo "Suppliers: " . $suppliers . "\n";

$products = App\Models\Product::count();
echo "Products: " . $products . "\n";

$productsByType = App\Models\Product::selectRaw('type, count(*) as count')->groupBy('type')->get();
echo "Products by type:\n";
foreach ($productsByType as $type) {
    echo "  " . $type->type . ": " . $type->count . "\n";
}

$invoices = App\Models\Invoice::count();
echo "Invoices: " . $invoices . "\n";

$purchases = App\Models\Purchase::count();
echo "Purchases: " . $purchases . "\n";

$accounts = App\Models\Account::count();
echo "Accounts: " . $accounts . "\n";

echo "\nFirst few accounts:\n";
$firstAccounts = App\Models\Account::take(10)->get();
foreach ($firstAccounts as $acc) {
    echo "  " . $acc->code . " - " . $acc->name . " (Balance: " . $acc->balance . ")\n";
}

echo "\nFirst few products:\n";
$firstProducts = App\Models\Product::take(5)->get();
foreach ($firstProducts as $product) {
    echo "  " . $product->name . " (Type: " . $product->type . ", Cost: " . $product->cost_price . ", Price: " . $product->unit_price . ")\n";
}

echo "\nFirst few customers:\n";
$firstCustomers = App\Models\Customer::take(3)->get();
foreach ($firstCustomers as $customer) {
    echo "  " . $customer->name . "\n";
}
