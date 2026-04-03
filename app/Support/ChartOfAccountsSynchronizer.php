<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Collection;

class ChartOfAccountsSynchronizer
{
    private const BASE_DEFINITIONS = [
        [
            'code' => '1',
            'name' => 'Assets',
            'name_ar' => 'الأصول',
            'account_type' => 'asset',
            'match_codes' => ['1000'],
            'match_fragments' => ['الأصول', 'Assets'],
        ],
        [
            'code' => '1.10',
            'name' => 'Current Assets',
            'name_ar' => 'الأصول المتداولة',
            'account_type' => 'asset',
            'parent' => '1',
            'match_codes' => ['1010'],
            'match_fragments' => ['الأصول المتداولة', 'Current Assets'],
        ],
        [
            'code' => '1.1',
            'name' => 'Cash',
            'name_ar' => 'الصندوق',
            'account_type' => 'asset',
            'parent' => '1.10',
            'match_codes' => ['1100', '1101'],
            'match_fragments' => ['الصندوق', 'Cash'],
        ],
        [
            'code' => '1.2',
            'name' => 'Bank',
            'name_ar' => 'البنك',
            'account_type' => 'asset',
            'parent' => '1.10',
            'match_codes' => ['1102'],
            'match_fragments' => ['البنك', 'Bank'],
        ],
        [
            'code' => '1.3',
            'name' => 'Customers',
            'name_ar' => 'العملاء',
            'account_type' => 'asset',
            'parent' => '1.10',
            'match_codes' => ['1200'],
            'match_fragments' => ['العملاء', 'ذمم مدينة', 'Customers', 'Receivable'],
        ],
        [
            'code' => '1.4',
            'name' => 'Inventory',
            'name_ar' => 'المخزون',
            'account_type' => 'asset',
            'parent' => '1.10',
            'match_codes' => ['1300'],
            'match_fragments' => ['المخزون', 'Inventory'],
        ],
        [
            'code' => '1.5',
            'name' => 'Input VAT',
            'name_ar' => 'ضريبة المدخلات',
            'account_type' => 'asset',
            'parent' => '1.10',
            'match_codes' => ['1400', '2310'],
            'match_fragments' => ['ضريبة المدخلات', 'Input VAT'],
        ],
        [
            'code' => '1.6',
            'name' => 'Prepaid Expenses',
            'name_ar' => 'مصروفات مدفوعة مقدماً',
            'account_type' => 'asset',
            'parent' => '1.10',
            'match_codes' => ['1500'],
            'match_fragments' => ['مصروفات مدفوعة مقدماً', 'Prepaid Expenses', 'Prepayments'],
        ],
        [
            'code' => '1.20',
            'name' => 'Non-current Assets',
            'name_ar' => 'الأصول غير المتداولة',
            'account_type' => 'asset',
            'parent' => '1',
            'match_codes' => ['1020'],
            'match_fragments' => ['الأصول غير المتداولة', 'Non-current Assets', 'Long-term Assets'],
        ],
        [
            'code' => '1.7',
            'name' => 'Fixed Assets',
            'name_ar' => 'الأصول الثابتة',
            'account_type' => 'asset',
            'parent' => '1.20',
            'match_codes' => ['1600'],
            'match_fragments' => ['الأصول الثابتة', 'Fixed Assets', 'Property and Equipment'],
        ],
        [
            'code' => '2',
            'name' => 'Liabilities',
            'name_ar' => 'الخصوم',
            'account_type' => 'liability',
            'match_codes' => ['2000'],
            'match_fragments' => ['الخصوم', 'Liabilities'],
        ],
        [
            'code' => '2.10',
            'name' => 'Current Liabilities',
            'name_ar' => 'الخصوم المتداولة',
            'account_type' => 'liability',
            'parent' => '2',
            'match_codes' => ['2010'],
            'match_fragments' => ['الخصوم المتداولة', 'Current Liabilities'],
        ],
        [
            'code' => '2.1',
            'name' => 'Suppliers',
            'name_ar' => 'الموردين',
            'account_type' => 'liability',
            'parent' => '2.10',
            'match_codes' => ['2100'],
            'match_fragments' => ['الموردين', 'ذمم دائنة', 'Suppliers', 'Payable'],
        ],
        [
            'code' => '2.20',
            'name' => 'Long-term Liabilities',
            'name_ar' => 'الخصوم طويلة الأجل',
            'account_type' => 'liability',
            'parent' => '2',
            'match_codes' => ['2020'],
            'match_fragments' => ['الخصوم طويلة الأجل', 'Long-term Liabilities'],
        ],
        [
            'code' => '2.2',
            'name' => 'Loans',
            'name_ar' => 'القروض',
            'account_type' => 'liability',
            'parent' => '2.20',
            'match_codes' => ['2200'],
            'match_fragments' => ['القروض', 'Loans'],
        ],
        [
            'code' => '2.3',
            'name' => 'Output VAT',
            'name_ar' => 'ضريبة المخرجات',
            'account_type' => 'liability',
            'parent' => '2.10',
            'match_codes' => ['2300'],
            'match_fragments' => ['ضريبة المخرجات', 'Output VAT', 'VAT Payable'],
        ],
        [
            'code' => '2.4',
            'name' => 'Accrued Expenses',
            'name_ar' => 'مصروفات مستحقة',
            'account_type' => 'liability',
            'parent' => '2.10',
            'match_codes' => ['2400'],
            'match_fragments' => ['مصروفات مستحقة', 'Accrued Expenses', 'Accruals'],
        ],
        [
            'code' => '3',
            'name' => 'Revenue',
            'name_ar' => 'الإيرادات',
            'account_type' => 'revenue',
            'match_codes' => ['4000'],
            'match_fragments' => ['الإيرادات', 'Revenue'],
        ],
        [
            'code' => '3.10',
            'name' => 'Operating Revenue',
            'name_ar' => 'الإيرادات التشغيلية',
            'account_type' => 'revenue',
            'parent' => '3',
            'match_codes' => ['4010'],
            'match_fragments' => ['الإيرادات التشغيلية', 'Operating Revenue'],
        ],
        [
            'code' => '3.1',
            'name' => 'Sales',
            'name_ar' => 'مبيعات',
            'account_type' => 'revenue',
            'parent' => '3.10',
            'match_codes' => ['4100'],
            'match_fragments' => ['مبيعات', 'Sales'],
        ],
        [
            'code' => '3.2',
            'name' => 'Service Revenue',
            'name_ar' => 'إيرادات الخدمات',
            'account_type' => 'revenue',
            'parent' => '3.10',
            'match_codes' => ['4200'],
            'match_fragments' => ['إيرادات الخدمات', 'Service Revenue', 'Service Sales'],
        ],
        [
            'code' => '3.20',
            'name' => 'Non-operating Revenue',
            'name_ar' => 'الإيرادات غير التشغيلية',
            'account_type' => 'revenue',
            'parent' => '3',
            'match_codes' => ['4020'],
            'match_fragments' => ['الإيرادات غير التشغيلية', 'Non-operating Revenue'],
        ],
        [
            'code' => '3.3',
            'name' => 'Other Income',
            'name_ar' => 'إيرادات أخرى',
            'account_type' => 'revenue',
            'parent' => '3.20',
            'match_codes' => ['4300'],
            'match_fragments' => ['إيرادات أخرى', 'Other Income', 'Other Revenue'],
        ],
        [
            'code' => '4',
            'name' => 'Expenses',
            'name_ar' => 'المصروفات',
            'account_type' => 'expense',
            'match_codes' => ['6000'],
            'match_fragments' => ['المصروفات', 'Expenses'],
        ],
        [
            'code' => '4.10',
            'name' => 'Administrative Expenses',
            'name_ar' => 'المصروفات الإدارية',
            'account_type' => 'expense',
            'parent' => '4',
            'match_codes' => ['6010'],
            'match_fragments' => ['المصروفات الإدارية', 'Administrative Expenses'],
        ],
        [
            'code' => '4.1',
            'name' => 'Salaries',
            'name_ar' => 'رواتب',
            'account_type' => 'expense',
            'parent' => '4.10',
            'match_codes' => ['6100'],
            'match_fragments' => ['رواتب', 'Salaries'],
        ],
        [
            'code' => '4.2',
            'name' => 'Rent',
            'name_ar' => 'إيجار',
            'account_type' => 'expense',
            'parent' => '4.10',
            'match_codes' => ['6200'],
            'match_fragments' => ['إيجار', 'Rent'],
        ],
        [
            'code' => '4.3',
            'name' => 'Miscellaneous Expenses',
            'name_ar' => 'مصروفات متنوعة',
            'account_type' => 'expense',
            'parent' => '4.10',
            'match_codes' => ['6300'],
            'match_fragments' => ['مصروفات متنوعة', 'Miscellaneous', 'General Expense'],
        ],
        [
            'code' => '4.20',
            'name' => 'Selling Expenses',
            'name_ar' => 'المصروفات البيعية',
            'account_type' => 'expense',
            'parent' => '4',
            'match_codes' => ['6020'],
            'match_fragments' => ['المصروفات البيعية', 'Selling Expenses'],
        ],
        [
            'code' => '4.4',
            'name' => 'Cost of Goods Sold',
            'name_ar' => 'تكلفة البضاعة المباعة',
            'account_type' => 'cogs',
            'parent' => '4.40',
            'match_codes' => ['5000', '5100'],
            'match_fragments' => ['تكلفة البضاعة المباعة', 'Cost of Goods Sold', 'COGS'],
        ],
        [
            'code' => '4.5',
            'name' => 'Utilities',
            'name_ar' => 'مرافق وخدمات',
            'account_type' => 'expense',
            'parent' => '4.10',
            'match_codes' => ['6400'],
            'match_fragments' => ['مرافق', 'كهرباء', 'مياه', 'Utilities'],
        ],
        [
            'code' => '4.6',
            'name' => 'Marketing',
            'name_ar' => 'تسويق وإعلان',
            'account_type' => 'expense',
            'parent' => '4.20',
            'match_codes' => ['6500'],
            'match_fragments' => ['تسويق', 'إعلان', 'Marketing', 'Advertising'],
        ],
        [
            'code' => '4.30',
            'name' => 'Financing Expenses',
            'name_ar' => 'المصروفات التمويلية',
            'account_type' => 'expense',
            'parent' => '4',
            'match_codes' => ['6030'],
            'match_fragments' => ['المصروفات التمويلية', 'Financing Expenses'],
        ],
        [
            'code' => '4.31',
            'name' => 'Bank Charges',
            'name_ar' => 'رسوم بنكية',
            'account_type' => 'expense',
            'parent' => '4.30',
            'match_codes' => ['6600'],
            'match_fragments' => ['رسوم بنكية', 'Bank Charges', 'Finance Charges'],
        ],
        [
            'code' => '4.40',
            'name' => 'Cost of Sales',
            'name_ar' => 'تكلفة المبيعات',
            'account_type' => 'expense',
            'parent' => '4',
            'match_codes' => ['6040'],
            'match_fragments' => ['تكلفة المبيعات', 'Cost of Sales'],
        ],
        [
            'code' => '5',
            'name' => 'Equity',
            'name_ar' => 'حقوق الملكية',
            'account_type' => 'equity',
            'match_codes' => ['3000'],
            'match_fragments' => ['حقوق الملكية', 'Equity', 'Owner Equity'],
        ],
        [
            'code' => '5.1',
            'name' => 'Capital',
            'name_ar' => 'رأس المال',
            'account_type' => 'equity',
            'parent' => '5',
            'match_codes' => ['3100'],
            'match_fragments' => ['رأس المال', 'Capital'],
        ],
        [
            'code' => '5.2',
            'name' => 'Retained Earnings',
            'name_ar' => 'الأرباح المبقاة',
            'account_type' => 'equity',
            'parent' => '5',
            'match_codes' => ['3200'],
            'match_fragments' => ['الأرباح المبقاة', 'Retained Earnings'],
        ],
        [
            'code' => '5.3',
            'name' => 'Owner Drawings',
            'name_ar' => 'مسحوبات المالك',
            'account_type' => 'equity',
            'parent' => '5',
            'match_codes' => ['3300'],
            'match_fragments' => ['مسحوبات', 'مسحوبات المالك', 'Owner Drawings', 'Drawings'],
        ],
    ];

    public function ensureBaseAccounts(int|Company $company): Collection
    {
        $companyId = $company instanceof Company ? (int) $company->id : (int) $company;
        $existingAccounts = Account::where('company_id', $companyId)->get();
        $accountsByCode = $existingAccounts->keyBy('code');
        $synced = collect();

        foreach (self::BASE_DEFINITIONS as $definition) {
            $parentId = null;

            if (isset($definition['parent'])) {
                $parentId = $synced->get($definition['parent'])?->id
                    ?? $accountsByCode->get($definition['parent'])?->id;
            }

            $account = $accountsByCode->get($definition['code'])
                ?? $this->findLegacyAccount($companyId, $definition, $existingAccounts);

            if ($account) {
                $account->fill([
                    'code' => $definition['code'],
                    'name' => $definition['name'],
                    'name_ar' => $definition['name_ar'],
                    'account_type' => $definition['account_type'],
                    'parent_id' => $parentId,
                    'is_active' => true,
                    'is_system' => true,
                ]);
                $account->save();
            } else {
                $account = Account::create([
                    'company_id' => $companyId,
                    'code' => $definition['code'],
                    'name' => $definition['name'],
                    'name_ar' => $definition['name_ar'],
                    'account_type' => $definition['account_type'],
                    'parent_id' => $parentId,
                    'is_active' => true,
                    'is_system' => true,
                ]);
                $existingAccounts->push($account);
            }

            $accountsByCode->put($definition['code'], $account);
            $synced->put($definition['code'], $account);
        }

        return $synced;
    }

    public function synchronizeCompany(Company|int $company): void
    {
        $companyModel = $company instanceof Company
            ? $company->loadMissing(['customers', 'suppliers', 'products'])
            : Company::with(['customers', 'suppliers', 'products'])->findOrFail($company);

        $this->ensureBaseAccounts($companyModel);

        foreach ($companyModel->customers as $customer) {
            $this->syncCustomerAccount($customer);
        }

        foreach ($companyModel->suppliers as $supplier) {
            $this->syncSupplierAccount($supplier);
        }

        foreach ($companyModel->products as $product) {
            $this->syncProductAccounts($product);
        }
    }

    public function syncCustomerAccount(Customer $customer): Account
    {
        $roots = $this->ensureBaseAccounts((int) $customer->company_id);
        $account = $this->upsertLinkedAccount(
            companyId: (int) $customer->company_id,
            existingAccountId: $customer->account_id,
            code: '1.3.C' . $customer->id,
            name: 'Customer Receivable - ' . $customer->name,
            nameAr: 'ذمة العميل - ' . ($customer->name_ar ?: $customer->name),
            type: 'asset',
            parentId: $roots->get('1.3')?->id,
        );

        if ((int) $customer->account_id !== (int) $account->id) {
            $customer->forceFill(['account_id' => $account->id])->save();
        }

        return $account;
    }

    public function syncSupplierAccount(Supplier $supplier): Account
    {
        $roots = $this->ensureBaseAccounts((int) $supplier->company_id);
        $account = $this->upsertLinkedAccount(
            companyId: (int) $supplier->company_id,
            existingAccountId: $supplier->account_id,
            code: '2.1.S' . $supplier->id,
            name: 'Supplier Payable - ' . $supplier->name,
            nameAr: 'ذمة المورد - ' . ($supplier->name_ar ?: $supplier->name),
            type: 'liability',
            parentId: $roots->get('2.1')?->id,
        );

        if ((int) $supplier->account_id !== (int) $account->id) {
            $supplier->forceFill(['account_id' => $account->id])->save();
        }

        return $account;
    }

    public function syncProductAccounts(Product $product): Product
    {
        $roots = $this->ensureBaseAccounts((int) $product->company_id);

        $revenueAccount = $this->upsertLinkedAccount(
            companyId: (int) $product->company_id,
            existingAccountId: $product->revenue_account_id,
            code: '3.1.P' . $product->id,
            name: 'Sales - ' . $product->name,
            nameAr: 'مبيعات - ' . ($product->name_ar ?: $product->name),
            type: 'revenue',
            parentId: $roots->get('3.1')?->id,
        );

        $updates = [
            'revenue_account_id' => $revenueAccount->id,
        ];

        if ($product->type === 'product') {
            $inventoryAccount = $this->upsertLinkedAccount(
                companyId: (int) $product->company_id,
                existingAccountId: $product->inventory_account_id,
                code: '1.4.P' . $product->id,
                name: 'Inventory - ' . $product->name,
                nameAr: 'مخزون - ' . ($product->name_ar ?: $product->name),
                type: 'asset',
                parentId: $roots->get('1.4')?->id,
            );

            $cogsAccount = $this->upsertLinkedAccount(
                companyId: (int) $product->company_id,
                existingAccountId: $product->cogs_account_id,
                code: '4.4.P' . $product->id,
                name: 'Cost of Goods Sold - ' . $product->name,
                nameAr: 'تكلفة البضاعة المباعة - ' . ($product->name_ar ?: $product->name),
                type: 'cogs',
                parentId: $roots->get('4.4')?->id,
            );

            $updates['inventory_account_id'] = $inventoryAccount->id;
            $updates['cogs_account_id'] = $cogsAccount->id;
        } else {
            $updates['inventory_account_id'] = null;
            $updates['cogs_account_id'] = null;
        }

        $product->forceFill($updates)->save();

        return $product->fresh();
    }

    private function findLegacyAccount(int $companyId, array $definition, Collection $accounts): ?Account
    {
        $matchCodes = $definition['match_codes'] ?? [];
        if ($matchCodes !== []) {
            $matchedByCode = $accounts
                ->first(fn (Account $account) => (int) $account->company_id === $companyId && in_array($account->code, $matchCodes, true));

            if ($matchedByCode) {
                return $matchedByCode;
            }
        }

        foreach ($definition['match_fragments'] ?? [] as $fragment) {
            $matchedByName = $accounts->first(function (Account $account) use ($companyId, $definition, $fragment) {
                if ((int) $account->company_id !== $companyId || $account->account_type !== $definition['account_type']) {
                    return false;
                }

                return str_contains(mb_strtolower($account->name), mb_strtolower($fragment))
                    || str_contains(mb_strtolower((string) $account->name_ar), mb_strtolower($fragment));
            });

            if ($matchedByName) {
                return $matchedByName;
            }
        }

        return null;
    }

    private function upsertLinkedAccount(int $companyId, ?int $existingAccountId, string $code, string $name, string $nameAr, string $type, ?int $parentId): Account
    {
        $account = null;

        if ($existingAccountId) {
            $account = Account::where('company_id', $companyId)->find($existingAccountId);
        }

        $account ??= Account::where('company_id', $companyId)->where('code', $code)->first();

        $account ??= Account::where('company_id', $companyId)
            ->where('parent_id', $parentId)
            ->where('account_type', $type)
            ->where(function ($query) use ($name, $nameAr) {
                $query->where('name', $name)
                    ->orWhere('name_ar', $nameAr);
            })
            ->first();

        if ($account) {
            $account->fill([
                'code' => $code,
                'name' => $name,
                'name_ar' => $nameAr,
                'account_type' => $type,
                'parent_id' => $parentId,
                'is_active' => true,
                'is_system' => true,
            ]);
            $account->save();

            return $account;
        }

        return Account::create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'name_ar' => $nameAr,
            'account_type' => $type,
            'parent_id' => $parentId,
            'is_active' => true,
            'is_system' => true,
        ]);
    }
}
