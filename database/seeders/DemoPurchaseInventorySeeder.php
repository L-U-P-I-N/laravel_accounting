<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DemoPurchaseInventorySeeder extends Seeder
{
    private const NOTE_MARKER = '[demo-purchase-inventory]';

    public function run(): void
    {
        $this->call(DemoSalesReportsSeeder::class);

        $company = Company::query()
            ->where('email', 'info@advanced-tech.com')
            ->first()
            ?: Company::query()->first();

        if (! $company) {
            return;
        }

        $suppliers = $this->seedSuppliers($company);
        $products = Product::query()
            ->where('company_id', $company->id)
            ->where('type', 'product')
            ->orderBy('code')
            ->get()
            ->keyBy('code');

        if ($products->isEmpty()) {
            return;
        }

        $this->cleanupExistingDemoPurchases($company);
        $this->seedPurchases($company, $suppliers, $products);
        $this->applyInventorySnapshotTargets($products);
        $this->refreshSupplierBalances($company);
    }

    private function seedSuppliers(Company $company): Collection
    {
        return collect([
            ['code' => 'SUP-RPT-001', 'name' => 'شركة مسارات التوريد', 'email' => 'supply@masarat-demo.test', 'city' => 'الرياض'],
            ['code' => 'SUP-RPT-002', 'name' => 'بوابة الأجهزة الحديثة', 'email' => 'devices@gateway-demo.test', 'city' => 'جدة'],
            ['code' => 'SUP-RPT-003', 'name' => 'مصنع الربط الذكي', 'email' => 'ops@smartlink-demo.test', 'city' => 'الدمام'],
            ['code' => 'SUP-RPT-004', 'name' => 'مؤسسة الدعم الممتد', 'email' => 'services@extended-demo.test', 'city' => 'الخبر'],
        ])->map(function (array $supplier, int $index) use ($company) {
            return Supplier::query()->updateOrCreate(
                ['company_id' => $company->id, 'email' => $supplier['email']],
                [
                    'company_id' => $company->id,
                    'code' => $supplier['code'],
                    'name' => $supplier['name'],
                    'email' => $supplier['email'],
                    'phone' => '+966560300' . ($index + 1),
                    'mobile' => '+966590300' . ($index + 1),
                    'address' => 'مورد تجريبي لعرض تقارير المشتريات والمخزون.',
                    'city' => $supplier['city'],
                    'country' => 'SA',
                    'credit_limit' => 120000,
                    'balance' => 0,
                    'is_active' => true,
                ]
            );
        })->values();
    }

    private function cleanupExistingDemoPurchases(Company $company): void
    {
        $purchaseIds = Purchase::query()
            ->where('company_id', $company->id)
            ->where('notes', 'like', self::NOTE_MARKER . '%')
            ->pluck('id');

        if ($purchaseIds->isEmpty()) {
            return;
        }

        PurchaseItem::query()->whereIn('purchase_id', $purchaseIds)->delete();
        Purchase::query()->whereIn('id', $purchaseIds)->delete();
    }

    private function seedPurchases(Company $company, Collection $suppliers, Collection $products): void
    {
        $paymentAccounts = Account::query()
            ->where('company_id', $company->id)
            ->where('allows_direct_transactions', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->values();

        $productMixes = [
            ['HW-WS-01', 'NW-AP-04'],
            ['HW-LT-02', 'NW-BC-05'],
            ['HW-PR-03', 'NW-PB-06'],
            ['HW-WS-01', 'HW-PR-03', 'NW-PB-06'],
            ['HW-LT-02', 'NW-AP-04', 'NW-BC-05'],
            ['HW-WS-01', 'HW-LT-02'],
        ];

        $paymentRatios = [1, 0.65, 0.25, 0, 1, 0.4, 0.8, 0, 1, 0.5];
        $startDate = Carbon::now()->subDays(165)->startOfDay();

        foreach (range(1, 28) as $index) {
            $purchaseDate = $startDate->copy()->addDays((int) floor($index * 4.6));
            $supplier = $suppliers->get(($index - 1) % $suppliers->count());
            $paymentAccount = $paymentAccounts->get(($index - 1) % max($paymentAccounts->count(), 1))
                ?? $paymentAccounts->first();
            $mix = $productMixes[($index - 1) % count($productMixes)];
            $priceFactor = 1 + (((($index - 1) % 5) - 2) * 0.025);
            $subtotal = 0;
            $taxAmount = 0;
            $lines = [];

            foreach ($mix as $lineIndex => $productCode) {
                /** @var \App\Models\Product $product */
                $product = $products->get($productCode);

                if (! $product) {
                    continue;
                }

                $quantity = match ($productCode) {
                    'HW-WS-01', 'HW-LT-02' => (float) (4 + (($index + $lineIndex) % 5)),
                    'HW-PR-03' => (float) (8 + (($index + $lineIndex) % 6)),
                    'NW-AP-04', 'NW-BC-05' => (float) (12 + (($index + $lineIndex) % 8)),
                    default => (float) (18 + (($index + $lineIndex) % 10)),
                };

                $unitPrice = round((float) $product->cost_price * $priceFactor, 2);
                $lineSubtotal = round($quantity * $unitPrice, 2);
                $lineTax = round($lineSubtotal * (((float) $product->tax_rate) / 100), 2);

                $subtotal += $lineSubtotal;
                $taxAmount += $lineTax;

                $lines[] = [
                    'product' => $product,
                    'description' => 'توريد ' . $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => (float) $product->tax_rate,
                    'tax_amount' => $lineTax,
                    'total' => round($lineSubtotal + $lineTax, 2),
                ];
            }

            if ($lines === []) {
                continue;
            }

            $subtotal = round($subtotal, 2);
            $taxAmount = round($taxAmount, 2);
            $total = round($subtotal + $taxAmount, 2);
            $paymentRatio = $paymentRatios[($index - 1) % count($paymentRatios)];
            $paidAmount = round($total * $paymentRatio, 2);
            $balanceDue = round(max($total - $paidAmount, 0), 2);

            $paymentStatus = $paidAmount <= 0
                ? 'pending'
                : ($balanceDue <= 0 ? 'paid' : 'partial');

            $status = $balanceDue <= 0
                ? 'approved'
                : (($index % 4 === 0) ? 'pending' : 'approved');

            $purchase = Purchase::query()->create([
                'purchase_number' => 'RPT-PUR-' . $purchaseDate->format('ym') . '-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'supplier_invoice_number' => 'SUP-INV-' . $purchaseDate->format('ym') . '-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
                'purchase_date' => $purchaseDate->toDateString(),
                'due_date' => $purchaseDate->copy()->addDays(30)->toDateString(),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'payment_account_id' => $paidAmount > 0 ? $paymentAccount?->id : null,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'payment_date' => $paymentStatus === 'pending' ? null : $purchaseDate->copy()->addDays(6)->toDateString(),
                'notes' => self::NOTE_MARKER . ' مشتريات تجريبية لملء تقارير المخزون والوارد والذمم الدائنة.',
                'terms' => 'سداد خلال 30 يوماً من تاريخ أمر الشراء.',
                'currency' => $company->currency,
                'exchange_rate' => 1,
            ]);

            foreach ($lines as $line) {
                $purchase->items()->create([
                    'product_id' => $line['product']->id,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'total' => $line['total'],
                ]);
            }
        }
    }

    private function applyInventorySnapshotTargets(Collection $products): void
    {
        $targets = [
            'HW-WS-01' => ['stock_quantity' => 6, 'min_stock' => 8],
            'HW-LT-02' => ['stock_quantity' => 9, 'min_stock' => 10],
            'HW-PR-03' => ['stock_quantity' => 21, 'min_stock' => 12],
            'NW-AP-04' => ['stock_quantity' => 14, 'min_stock' => 20],
            'NW-BC-05' => ['stock_quantity' => 24, 'min_stock' => 18],
            'NW-PB-06' => ['stock_quantity' => 28, 'min_stock' => 30],
        ];

        foreach ($targets as $code => $values) {
            /** @var \App\Models\Product|null $product */
            $product = $products->get($code);

            if (! $product) {
                continue;
            }

            $product->update($values);
        }
    }

    private function refreshSupplierBalances(Company $company): void
    {
        Supplier::query()
            ->where('company_id', $company->id)
            ->get()
            ->each(function (Supplier $supplier) use ($company) {
                $balance = Purchase::query()
                    ->where('company_id', $company->id)
                    ->where('supplier_id', $supplier->id)
                    ->sum('balance_due');

                $supplier->forceFill([
                    'balance' => round((float) $balance, 2),
                ])->save();
            });
    }
}
