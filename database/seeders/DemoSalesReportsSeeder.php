<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Account;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Support\ChartOfAccountsSynchronizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DemoSalesReportsSeeder extends Seeder
{
    private const NOTE_MARKER = '[demo-sales-reports]';

    public function run(): void
    {
        $company = Company::query()
            ->where('email', 'info@advanced-tech.com')
            ->first()
            ?: Company::query()->first();

        if (! $company) {
            return;
        }

        app(ChartOfAccountsSynchronizer::class)->synchronizeCompany($company);

        $dimensions = $this->seedDimensions($company);
        $customers = $this->seedCustomers($company);
        $employees = $this->seedEmployees($company);
        $products = $this->seedProducts($company, $dimensions['categories']);

        $this->cleanupExistingDemoSales($company);

        $this->seedInvoices(
            $company,
            $customers,
            $employees,
            $products,
            $dimensions['branches'],
            $dimensions['channels'],
            $dimensions['paymentAccounts']
        );

        $this->refreshCustomerBalances($company);
    }

    private function seedDimensions(Company $company): array
    {
        $branches = collect([
            ['name' => 'فرع الرياض الرئيسي', 'code' => 'RUH-HQ', 'city' => 'الرياض', 'is_default' => true],
            ['name' => 'فرع جدة التجاري', 'code' => 'JED-COM', 'city' => 'جدة', 'is_default' => false],
            ['name' => 'فرع الخبر', 'code' => 'KBR-EAST', 'city' => 'الخبر', 'is_default' => false],
        ])->map(fn (array $branch) => Branch::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => $branch['code']],
            array_merge($branch, ['company_id' => $company->id])
        ))->values();

        $categories = collect([
            ['name' => 'الأجهزة المكتبية', 'slug' => 'office-hardware', 'is_default' => false],
            ['name' => 'البرمجيات السحابية', 'slug' => 'cloud-software', 'is_default' => false],
            ['name' => 'الشبكات والإكسسوارات', 'slug' => 'network-accessories', 'is_default' => false],
            ['name' => 'عقود الدعم', 'slug' => 'support-contracts', 'is_default' => false],
        ])->mapWithKeys(function (array $category) use ($company) {
            $model = Category::query()->updateOrCreate(
                ['company_id' => $company->id, 'name' => $category['name']],
                array_merge($category, ['company_id' => $company->id])
            );

            return [$category['name'] => $model];
        });

        $channels = collect([
            ['name' => 'المعرض الرئيسي', 'code' => 'SHOWROOM', 'is_default' => true],
            ['name' => 'المتجر الإلكتروني', 'code' => 'ECOM', 'is_default' => false],
            ['name' => 'واتساب الأعمال', 'code' => 'WHATSAPP', 'is_default' => false],
            ['name' => 'مندوب ميداني', 'code' => 'FIELD', 'is_default' => false],
        ])->map(fn (array $channel) => SalesChannel::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => $channel['code']],
            array_merge($channel, ['company_id' => $company->id])
        ))->values();

        $paymentAccounts = Account::query()
            ->where('company_id', $company->id)
            ->where('allows_direct_transactions', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->values();

        return [
            'branches' => $branches,
            'categories' => $categories,
            'channels' => $channels,
            'paymentAccounts' => $paymentAccounts,
        ];
    }

    private function seedCustomers(Company $company): Collection
    {
        return collect([
            ['name' => 'شركة المدار الرقمي', 'email' => 'procurement@madar-demo.test', 'city' => 'الرياض', 'sector' => 'تجزئة تقنية'],
            ['name' => 'مستودعات الصفوة', 'email' => 'finance@safwa-demo.test', 'city' => 'جدة', 'sector' => 'لوجستيات'],
            ['name' => 'مجموعة البناء الحديث', 'email' => 'erp@benaa-demo.test', 'city' => 'الخبر', 'sector' => 'مقاولات'],
            ['name' => 'عيادات النخبة', 'email' => 'it@eliteclinic-demo.test', 'city' => 'الرياض', 'sector' => 'رعاية صحية'],
            ['name' => 'أسواق البشائر', 'email' => 'stores@bashayer-demo.test', 'city' => 'مكة', 'sector' => 'تجزئة غذائية'],
            ['name' => 'أكاديمية القمم', 'email' => 'admin@qimam-demo.test', 'city' => 'المدينة', 'sector' => 'تعليم'],
            ['name' => 'فنادق المروج', 'email' => 'ops@morouj-demo.test', 'city' => 'جدة', 'sector' => 'ضيافة'],
            ['name' => 'مصنع الرؤية', 'email' => 'plant@visionfactory-demo.test', 'city' => 'الجبيل', 'sector' => 'تصنيع'],
        ])->map(function (array $customer, int $index) use ($company) {
            return Customer::query()->updateOrCreate(
                ['company_id' => $company->id, 'email' => $customer['email']],
                [
                    'company_id' => $company->id,
                    'code' => 'CUST-RPT-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'name' => $customer['name'],
                    'email' => $customer['email'],
                    'phone' => '+966550000' . str_pad((string) ($index + 11), 2, '0', STR_PAD_LEFT),
                    'mobile' => '+966510000' . str_pad((string) ($index + 11), 2, '0', STR_PAD_LEFT),
                    'address' => 'عميل تجريبي لعرض تقارير المبيعات - ' . $customer['sector'],
                    'city' => $customer['city'],
                    'country' => 'SA',
                    'credit_limit' => 50000 + ($index * 7000),
                    'balance' => 0,
                    'is_active' => true,
                ]
            );
        })->values();
    }

    private function seedEmployees(Company $company): Collection
    {
        return collect([
            ['employee_number' => 'SALE-1001', 'first_name' => 'سلمان', 'last_name' => 'العتيبي', 'position' => 'مدير مبيعات', 'department' => 'المبيعات'],
            ['employee_number' => 'SALE-1002', 'first_name' => 'ريم', 'last_name' => 'الحربي', 'position' => 'استشاري حلول', 'department' => 'المبيعات'],
            ['employee_number' => 'SALE-1003', 'first_name' => 'تركي', 'last_name' => 'الزهراني', 'position' => 'مندوب ميداني', 'department' => 'قنوات البيع'],
            ['employee_number' => 'SALE-1004', 'first_name' => 'نوف', 'last_name' => 'الشمري', 'position' => 'مديرة حسابات العملاء', 'department' => 'نجاح العملاء'],
        ])->map(function (array $employee, int $index) use ($company) {
            return Employee::query()->updateOrCreate(
                ['company_id' => $company->id, 'employee_number' => $employee['employee_number']],
                [
                    'company_id' => $company->id,
                    'first_name' => $employee['first_name'],
                    'last_name' => $employee['last_name'],
                    'email' => 'sales.demo.' . ($index + 1) . '@advanced-tech.test',
                    'phone' => '+966540100' . ($index + 1),
                    'address' => 'موظف تجريبي لقسم المبيعات',
                    'hire_date' => Carbon::now()->subMonths(18 + $index),
                    'position' => $employee['position'],
                    'department' => $employee['department'],
                    'salary' => 8000 + ($index * 1500),
                    'status' => 'active',
                    'employment_type' => 'full_time',
                    'notes' => 'مولد تلقائياً لتقارير المبيعات التجريبية.',
                ]
            );
        })->values();
    }

    private function seedProducts(Company $company, Collection $categories): Collection
    {
        $categoryMap = $categories->mapWithKeys(fn (Category $category, string $name) => [$name => $category]);

        return collect([
            ['code' => 'HW-WS-01', 'name' => 'محطة عمل مكتبية Pro', 'category' => 'الأجهزة المكتبية', 'type' => 'product', 'unit' => 'قطعة', 'cost_price' => 3150, 'sell_price' => 4690, 'stock_quantity' => 40, 'min_stock' => 8, 'tax_rate' => 15],
            ['code' => 'HW-LT-02', 'name' => 'حاسب محمول للأعمال Elite', 'category' => 'الأجهزة المكتبية', 'type' => 'product', 'unit' => 'قطعة', 'cost_price' => 2600, 'sell_price' => 3890, 'stock_quantity' => 55, 'min_stock' => 10, 'tax_rate' => 15],
            ['code' => 'HW-PR-03', 'name' => 'طابعة فواتير حرارية', 'category' => 'الأجهزة المكتبية', 'type' => 'product', 'unit' => 'قطعة', 'cost_price' => 480, 'sell_price' => 820, 'stock_quantity' => 75, 'min_stock' => 12, 'tax_rate' => 15],
            ['code' => 'NW-AP-04', 'name' => 'نقطة وصول شبكية AC', 'category' => 'الشبكات والإكسسوارات', 'type' => 'product', 'unit' => 'قطعة', 'cost_price' => 340, 'sell_price' => 590, 'stock_quantity' => 110, 'min_stock' => 20, 'tax_rate' => 15],
            ['code' => 'NW-BC-05', 'name' => 'قارئ باركود صناعي', 'category' => 'الشبكات والإكسسوارات', 'type' => 'product', 'unit' => 'قطعة', 'cost_price' => 210, 'sell_price' => 430, 'stock_quantity' => 95, 'min_stock' => 18, 'tax_rate' => 15],
            ['code' => 'NW-PB-06', 'name' => 'حزمة إكسسوارات نقاط البيع', 'category' => 'الشبكات والإكسسوارات', 'type' => 'product', 'unit' => 'حزمة', 'cost_price' => 95, 'sell_price' => 175, 'stock_quantity' => 180, 'min_stock' => 30, 'tax_rate' => 15],
            ['code' => 'SW-ERP-07', 'name' => 'اشتراك ERP سحابي', 'category' => 'البرمجيات السحابية', 'type' => 'service', 'unit' => 'ترخيص', 'cost_price' => 350, 'sell_price' => 980, 'stock_quantity' => 0, 'min_stock' => 0, 'tax_rate' => 15],
            ['code' => 'SW-ECM-08', 'name' => 'ربط متجر إلكتروني', 'category' => 'البرمجيات السحابية', 'type' => 'service', 'unit' => 'مشروع', 'cost_price' => 720, 'sell_price' => 1800, 'stock_quantity' => 0, 'min_stock' => 0, 'tax_rate' => 15],
            ['code' => 'SV-SUP-09', 'name' => 'عقد دعم سنوي', 'category' => 'عقود الدعم', 'type' => 'service', 'unit' => 'عقد', 'cost_price' => 980, 'sell_price' => 2650, 'stock_quantity' => 0, 'min_stock' => 0, 'tax_rate' => 15],
            ['code' => 'SV-INS-10', 'name' => 'زيارة تركيب وتشغيل', 'category' => 'عقود الدعم', 'type' => 'service', 'unit' => 'زيارة', 'cost_price' => 260, 'sell_price' => 690, 'stock_quantity' => 0, 'min_stock' => 0, 'tax_rate' => 15],
        ])->mapWithKeys(function (array $product) use ($company, $categoryMap) {
            $model = Product::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $product['code']],
                [
                    'company_id' => $company->id,
                    'category_id' => $categoryMap[$product['category']]->id,
                    'supplier_id' => null,
                    'name' => $product['name'],
                    'type' => $product['type'],
                    'unit' => $product['unit'],
                    'cost_price' => $product['cost_price'],
                    'sell_price' => $product['sell_price'],
                    'stock_quantity' => $product['stock_quantity'],
                    'min_stock' => $product['min_stock'],
                    'tax_rate' => $product['tax_rate'],
                    'description' => 'منتج تجريبي مخصص لملء تقارير المبيعات.',
                    'is_active' => true,
                ]
            );

            return [$product['code'] => $model];
        });
    }

    private function cleanupExistingDemoSales(Company $company): void
    {
        $invoiceIds = Invoice::query()
            ->where('company_id', $company->id)
            ->where('notes', 'like', self::NOTE_MARKER . '%')
            ->pluck('id');

        if ($invoiceIds->isEmpty()) {
            return;
        }

        Payment::query()->whereIn('invoice_id', $invoiceIds)->delete();
        InvoiceItem::query()->whereIn('invoice_id', $invoiceIds)->delete();
        Invoice::query()->whereIn('id', $invoiceIds)->delete();
    }

    private function seedInvoices(
        Company $company,
        Collection $customers,
        Collection $employees,
        Collection $products,
        Collection $branches,
        Collection $channels,
        Collection $paymentAccounts
    ): void {
        $productMixes = [
            ['HW-WS-01', 'NW-AP-04', 'SV-SUP-09'],
            ['HW-LT-02', 'NW-BC-05', 'SW-ERP-07'],
            ['HW-PR-03', 'NW-PB-06', 'SV-INS-10'],
            ['HW-LT-02', 'SW-ECM-08', 'SV-SUP-09'],
            ['HW-WS-01', 'HW-PR-03', 'NW-PB-06'],
            ['NW-AP-04', 'NW-BC-05', 'SW-ERP-07'],
        ];

        $paymentRatios = [1, 0.45, 0, 1, 0.60, 1, 0.35, 0, 1, 0.55, 1, 0];
        $priceAdjustments = [0.00, 0.04, -0.03, 0.06, 0.02, -0.01];
        $startDate = Carbon::now()->subDays(120)->startOfDay();

        foreach (range(1, 42) as $index) {
            $invoiceDate = $startDate->copy()->addDays((int) floor($index * 2.7));
            $customer = $customers->get(($index - 1) % $customers->count());
            $employee = $employees->get(($index + 1) % $employees->count());
            $branch = $branches->get(($index - 1) % $branches->count());
            $channel = $channels->get(($index + 2) % $channels->count());
            $paymentAccount = $paymentAccounts->get(($index + 1) % $paymentAccounts->count())
                ?? $paymentAccounts->first();
            $mix = $productMixes[($index - 1) % count($productMixes)];
            $adjustment = $priceAdjustments[($index - 1) % count($priceAdjustments)];
            $lines = [];
            $subtotal = 0;
            $taxAmount = 0;

            foreach ($mix as $lineIndex => $productCode) {
                /** @var \App\Models\Product $product */
                $product = $products->get($productCode);

                $quantity = $product->type === 'service'
                    ? (float) (1 + (($index + $lineIndex) % 2))
                    : (float) (1 + (($index + ($lineIndex * 2)) % 4));

                if ($productCode === 'NW-PB-06') {
                    $quantity += 2;
                }

                $unitPrice = round((float) $product->sell_price * (1 + $adjustment), 2);
                $lineSubtotal = round($quantity * $unitPrice, 2);
                $lineTax = round($lineSubtotal * (((float) $product->tax_rate) / 100), 2);

                $subtotal += $lineSubtotal;
                $taxAmount += $lineTax;

                $lines[] = [
                    'product' => $product,
                    'description' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => (float) $product->tax_rate,
                    'tax_amount' => $lineTax,
                    'total' => round($lineSubtotal + $lineTax, 2),
                ];
            }

            $subtotal = round($subtotal, 2);
            $taxAmount = round($taxAmount, 2);
            $total = round($subtotal + $taxAmount, 2);
            $paymentRatio = $paymentRatios[($index - 1) % count($paymentRatios)];
            $paidAmount = round($total * $paymentRatio, 2);
            $balanceDue = round(max($total - $paidAmount, 0), 2);

            if ($balanceDue <= 0) {
                $status = 'paid';
                $paymentStatus = 'paid';
            } elseif ($paidAmount > 0) {
                $status = 'partial';
                $paymentStatus = 'partial';
            } else {
                $status = 'sent';
                $paymentStatus = 'pending';
            }

            $invoice = Invoice::query()->create([
                'invoice_number' => 'RPT-SLS-' . $invoiceDate->format('ym') . '-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'employee_id' => $employee->id,
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'sales_channel_id' => $channel->id,
                'payment_account_id' => $paidAmount > 0 ? $paymentAccount?->id : null,
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $invoiceDate->copy()->addDays($status === 'sent' ? 21 : 14)->toDateString(),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'notes' => self::NOTE_MARKER . ' بيانات مبيعات واقعية لتجربة التقارير بصرياً.',
                'terms' => 'استحقاق خلال 14 يوماً من تاريخ الفاتورة.',
                'currency' => $company->currency,
                'exchange_rate' => 1,
            ]);

            foreach ($lines as $line) {
                $invoice->items()->create([
                    'product_id' => $line['product']->id,
                    'category_id' => $line['product']->category_id,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'total' => $line['total'],
                ]);
            }

            $this->seedPaymentsForInvoice($invoice, $paymentAccount?->id, $invoiceDate, $paidAmount);
        }
    }

    private function seedPaymentsForInvoice(Invoice $invoice, ?int $paymentAccountId, Carbon $invoiceDate, float $paidAmount): void
    {
        if ($paidAmount <= 0) {
            return;
        }

        $parts = ((int) $invoice->id % 3 === 0 && $paidAmount > 2500)
            ? [0.6, 0.4]
            : [1];

        $remaining = $paidAmount;

        foreach ($parts as $index => $ratio) {
            $amount = $index === array_key_last($parts)
                ? round($remaining, 2)
                : round($paidAmount * $ratio, 2);

            $remaining -= $amount;

            Payment::query()->create([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'payment_account_id' => $paymentAccountId,
                'amount' => $amount,
                'payment_date' => $invoiceDate->copy()->addDays(2 + ($index * 6))->toDateString(),
                'reference' => 'PAY-RPT-' . $invoice->invoice_number . '-' . ($index + 1),
                'notes' => self::NOTE_MARKER . ' دفعة مولدة تلقائياً لتغذية كشف الحساب وتقارير التحصيل.',
            ]);
        }
    }

    private function refreshCustomerBalances(Company $company): void
    {
        Customer::query()
            ->where('company_id', $company->id)
            ->get()
            ->each(function (Customer $customer) use ($company) {
                $balance = Invoice::query()
                    ->where('company_id', $company->id)
                    ->where('customer_id', $customer->id)
                    ->sum('balance_due');

                $customer->forceFill([
                    'balance' => round((float) $balance, 2),
                ])->save();
            });
    }
}
