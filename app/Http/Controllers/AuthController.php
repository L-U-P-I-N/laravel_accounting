<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Models\User;
use App\Models\Company;
use App\Models\Account;
use App\Models\TaxSetting;
use App\Support\AccessControl;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function showLanding(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('landing');
    }

    public function showRegister(): View
    {
        $taxConfigs = $this->getTaxConfigs();
        return view('register', ['countries' => $taxConfigs]);
    }

    public function register(Request $request): RedirectResponse
    {
        AccessControl::ensureSeeded();

        $validated = $request->validate([
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6|confirmed',
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'company_name' => 'required|string|max:200',
            'country_code' => 'required|string|max:5',
            'city' => 'required|string|max:100',
        ]);

        $taxConfigs = $this->getTaxConfigs();
        $taxConfig = $taxConfigs[$request->country_code] ?? $taxConfigs['SA'];
        $allowedCities = $taxConfig['cities'] ?? [];

        if ($allowedCities !== [] && ! in_array($request->city, $allowedCities, true)) {
            return back()
                ->withErrors(['city' => 'المدينة المحددة لا تتبع الدولة المختارة.'])
                ->withInput($request->except('password', 'password_confirmation'));
        }

        // Create company
        $company = Company::create([
            'name' => $request->company_name,
            'city' => $request->city,
            'country_code' => $request->country_code,
            'currency' => $taxConfig['currency'],
            'subscription_plan' => 'basic',
            'subscription_status' => 'trial',
            'subscription_start' => now(),
            'subscription_end' => now()->addDays(14),
        ]);

        // Create user
        $user = User::create([
            'name' => trim($request->first_name . ' ' . $request->last_name),
            'email' => $request->email,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'password' => Hash::make($request->password),
            'role' => 'owner',
            'must_change_password' => false,
            'company_id' => $company->id,
        ]);

        $ownerRole = \App\Models\Role::query()->where('name', AccessControl::ROLE_OWNER)->first();
        if ($ownerRole) {
            $user->roles()->sync([$ownerRole->id]);
        }

        // Create default chart of accounts
        $this->createDefaultAccounts($company->id, $request->country_code);

        // Create default tax settings
        if ($taxConfig['vat_rate'] > 0) {
            $vatOutputAccount = Account::where('code', '2300')
                ->where('company_id', $company->id)
                ->first();

            $vatInputAccount = Account::where('code', '2310')
                ->where('company_id', $company->id)
                ->first();

            TaxSetting::create([
                'tax_name' => 'VAT',
                'tax_name_ar' => 'ضريبة المخرجات',
                'tax_type' => 'output_vat',
                'rate' => $taxConfig['vat_rate'],
                'is_default' => true,
                'account_id' => $vatOutputAccount?->id,
                'company_id' => $company->id,
            ]);

            TaxSetting::create([
                'tax_name' => 'Input VAT',
                'tax_name_ar' => 'ضريبة المدخلات',
                'tax_type' => 'input_vat',
                'rate' => $taxConfig['vat_rate'],
                'is_default' => false,
                'account_id' => $vatInputAccount?->id,
                'company_id' => $company->id,
            ]);
        }

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', 'تم إنشاء الحساب بنجاح! لديك فترة تجريبية 14 يوم');
    }

    public function showLogin(): View
    {
        return view('login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if (!$request->user()->is_active) {
                Auth::logout();

                return back()
                    ->withErrors(['email' => 'هذا الحساب معطل حالياً'])
                    ->withInput($request->except('password'));
            }

            $request->user()->update(['last_login' => now()]);

            if ($request->user()->requiresPasswordChange()) {
                return redirect()->route('password.change')
                    ->with('warning', 'يجب تغيير كلمة المرور قبل متابعة استخدام النظام');
            }

            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withErrors(['email' => 'بيانات الدخول غير صحيحة'])
            ->withInput($request->except('password'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showPasswordChange(): View
    {
        return view('auth.force_password_change');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')->with('success', 'تم تحديث كلمة المرور بنجاح');
    }

    private function getTaxConfigs(): array
    {
        return [
            'SA' => [
                'name' => 'Saudi Arabia',
                'name_ar' => 'المملكة العربية السعودية',
                'currency' => 'SAR',
                'cities' => ['الرياض', 'جدة', 'مكة المكرمة', 'المدينة المنورة', 'الدمام', 'الخبر', 'الظهران', 'الطائف', 'أبها', 'تبوك'],
                'vat_rate' => 15.0,
                'tax_number_label' => 'الرقم الضريبي',
                'tax_number_format' => '/^\d{15}$/',
                'fiscal_year_start' => '01-01',
                'zatca_enabled' => true
            ],
            'AE' => [
                'name' => 'UAE',
                'name_ar' => 'الإمارات العربية المتحدة',
                'currency' => 'AED',
                'cities' => ['دبي', 'أبوظبي', 'الشارقة', 'عجمان', 'رأس الخيمة', 'الفجيرة', 'أم القيوين', 'العين'],
                'vat_rate' => 5.0,
                'tax_number_label' => 'TRN',
                'tax_number_format' => '/^\d{15}$/',
                'fiscal_year_start' => '01-01',
                'zatca_enabled' => false
            ],
            'US' => [
                'name' => 'United States',
                'name_ar' => 'الولايات المتحدة',
                'currency' => 'USD',
                'cities' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Miami', 'Dallas', 'Seattle', 'San Francisco'],
                'vat_rate' => 0.0,
                'sales_tax' => true,
                'tax_number_label' => 'EIN',
                'tax_number_format' => '/^\d{2}-\d{7}$/',
                'fiscal_year_start' => '01-01',
                'zatca_enabled' => false
            ],
            'EG' => [
                'name' => 'Egypt',
                'name_ar' => 'مصر',
                'currency' => 'EGP',
                'cities' => ['القاهرة', 'الجيزة', 'الإسكندرية', 'المنصورة', 'طنطا', 'أسيوط', 'الأقصر', 'أسوان'],
                'vat_rate' => 14.0,
                'tax_number_label' => 'الرقم الضريبي',
                'fiscal_year_start' => '01-01',
                'zatca_enabled' => false
            ],
            'JO' => [
                'name' => 'Jordan',
                'name_ar' => 'الأردن',
                'currency' => 'JOD',
                'cities' => ['عمّان', 'إربد', 'الزرقاء', 'العقبة', 'السلط', 'مادبا', 'جرش', 'الكرك'],
                'vat_rate' => 16.0,
                'tax_number_label' => 'الرقم الضريبي',
                'fiscal_year_start' => '01-01',
                'zatca_enabled' => false
            ]
        ];
    }

    private function createDefaultAccounts(int $companyId, string $countryCode = 'SA'): void
    {
        $accountsData = [
            // Assets
            ['code' => '1000', 'name' => 'Assets', 'name_ar' => 'الأصول', 'type' => 'asset', 'system' => true],
            ['code' => '1100', 'name' => 'Cash & Bank', 'name_ar' => 'النقد والبنوك', 'type' => 'asset', 'parent' => '1000'],
            ['code' => '1101', 'name' => 'Cash on Hand', 'name_ar' => 'الصندوق', 'type' => 'asset', 'parent' => '1100'],
            ['code' => '1102', 'name' => 'Bank Account', 'name_ar' => 'الحساب البنكي', 'type' => 'asset', 'parent' => '1100'],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'name_ar' => 'الذمم المدينة', 'type' => 'asset', 'parent' => '1000', 'system' => true],
            ['code' => '1300', 'name' => 'Inventory', 'name_ar' => 'المخزون', 'type' => 'asset', 'parent' => '1000'],
            ['code' => '1400', 'name' => 'Prepaid Expenses', 'name_ar' => 'مصروفات مدفوعة مقدماً', 'type' => 'asset', 'parent' => '1000'],
            ['code' => '1500', 'name' => 'Fixed Assets', 'name_ar' => 'الأصول الثابتة', 'type' => 'asset', 'parent' => '1000'],
            ['code' => '1510', 'name' => 'Furniture & Equipment', 'name_ar' => 'أثاث ومعدات', 'type' => 'asset', 'parent' => '1500'],
            ['code' => '1520', 'name' => 'Vehicles', 'name_ar' => 'سيارات', 'type' => 'asset', 'parent' => '1500'],
            ['code' => '1590', 'name' => 'Accumulated Depreciation', 'name_ar' => 'مجمع الإهلاك', 'type' => 'asset', 'parent' => '1500'],

            // Liabilities
            ['code' => '2000', 'name' => 'Liabilities', 'name_ar' => 'الخصوم', 'type' => 'liability', 'system' => true],
            ['code' => '2100', 'name' => 'Accounts Payable', 'name_ar' => 'الذمم الدائنة', 'type' => 'liability', 'parent' => '2000', 'system' => true],
            ['code' => '2200', 'name' => 'Accrued Expenses', 'name_ar' => 'مصروفات مستحقة', 'type' => 'liability', 'parent' => '2000'],
            ['code' => '2300', 'name' => 'VAT Payable', 'name_ar' => 'ضريبة القيمة المضافة المستحقة', 'type' => 'liability', 'parent' => '2000', 'system' => true],
            ['code' => '2310', 'name' => 'Input VAT', 'name_ar' => 'ضريبة المدخلات', 'type' => 'asset', 'parent' => '1000', 'system' => true],
            ['code' => '2400', 'name' => 'Salaries Payable', 'name_ar' => 'رواتب مستحقة', 'type' => 'liability', 'parent' => '2000'],
            ['code' => '2500', 'name' => 'GOSI Payable', 'name_ar' => 'التأمينات الاجتماعية المستحقة', 'type' => 'liability', 'parent' => '2000'],
            ['code' => '2600', 'name' => 'Loans', 'name_ar' => 'القروض', 'type' => 'liability', 'parent' => '2000'],
            ['code' => '2700', 'name' => 'End of Service', 'name_ar' => 'مكافأة نهاية الخدمة', 'type' => 'liability', 'parent' => '2000'],

            // Equity
            ['code' => '3000', 'name' => 'Equity', 'name_ar' => 'حقوق الملكية', 'type' => 'equity', 'system' => true],
            ['code' => '3100', 'name' => 'Capital', 'name_ar' => 'رأس المال', 'type' => 'equity', 'parent' => '3000'],
            ['code' => '3200', 'name' => 'Retained Earnings', 'name_ar' => 'الأرباح المبقاة', 'type' => 'equity', 'parent' => '3000'],
            ['code' => '3300', 'name' => 'Owner Drawing', 'name_ar' => 'المسحوبات الشخصية', 'type' => 'equity', 'parent' => '3000'],

            // Revenue
            ['code' => '4000', 'name' => 'Revenue', 'name_ar' => 'الإيرادات', 'type' => 'revenue', 'system' => true],
            ['code' => '4100', 'name' => 'Sales Revenue', 'name_ar' => 'إيرادات المبيعات', 'type' => 'revenue', 'parent' => '4000'],
            ['code' => '4200', 'name' => 'Service Revenue', 'name_ar' => 'إيرادات الخدمات', 'type' => 'revenue', 'parent' => '4000'],
            ['code' => '4300', 'name' => 'Other Income', 'name_ar' => 'إيرادات أخرى', 'type' => 'revenue', 'parent' => '4000'],
            ['code' => '4400', 'name' => 'Sales Returns', 'name_ar' => 'مردودات المبيعات', 'type' => 'revenue', 'parent' => '4000'],
            ['code' => '4500', 'name' => 'Sales Discounts', 'name_ar' => 'خصومات المبيعات', 'type' => 'revenue', 'parent' => '4000'],

            // Cost of Goods Sold
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'name_ar' => 'تكلفة البضاعة المباعة', 'type' => 'cogs', 'system' => true],
            ['code' => '5100', 'name' => 'Direct Materials', 'name_ar' => 'مواد مباشرة', 'type' => 'cogs', 'parent' => '5000'],
            ['code' => '5200', 'name' => 'Direct Labor', 'name_ar' => 'عمالة مباشرة', 'type' => 'cogs', 'parent' => '5000'],

            // Expenses
            ['code' => '6000', 'name' => 'Expenses', 'name_ar' => 'المصروفات', 'type' => 'expense', 'system' => true],
            ['code' => '6100', 'name' => 'Salaries & Wages', 'name_ar' => 'الرواتب والأجور', 'type' => 'expense', 'parent' => '6000'],
            ['code' => '6110', 'name' => 'Housing Allowance Expense', 'name_ar' => 'مصروف بدل السكن', 'type' => 'expense', 'parent' => '6000'],
            ['code' => '6120', 'name' => 'Transport Allowance Expense', 'name_ar' => 'مصروف بدل المواصلات', 'type' => 'expense', 'parent' => '6000'],
            ['code' => '6150', 'name' => 'GOSI Expense', 'name_ar' => 'مصروف التأمينات الاجتماعية', 'type' => 'expense', 'parent' => '6000'],
            ['code' => '6200', 'name' => 'Rent Expense', 'name_ar' => 'مصروف الإيجار', 'type' => 'expense', 'parent' => '6000'],
            ['code' => '6300', 'name' => 'Utilities', 'name_ar' => 'مصاريف الكهرباء والماء', 'type' => 'expense', 'parent' => '6000'],
            ['code' => '6400', 'name' => 'Office Supplies', 'name_ar' => 'مستلزمات مكتبية', 'type' => 'expense', 'parent' => '6000'],
            ['code' => '6500', 'name' => 'Marketing', 'name_ar' => 'مصاريف تسويق', 'type' => 'expense', 'parent' => '6000'],
            ['code' => '6600', 'name' => 'Depreciation', 'name_ar' => 'مصروف الإهلاك', 'type' => 'expense', 'parent' => '6000'],
            ['code' => '6700', 'name' => 'Insurance', 'name_ar' => 'مصروف التأمين', 'type' => 'expense', 'parent' => '6000'],
            ['code' => '6800', 'name' => 'Professional Fees', 'name_ar' => 'أتعاب مهنية', 'type' => 'expense', 'parent' => '6000'],
            ['code' => '6900', 'name' => 'Miscellaneous Expenses', 'name_ar' => 'مصروفات متنوعة', 'type' => 'expense', 'parent' => '6000'],
            ['code' => '6950', 'name' => 'Bank Charges', 'name_ar' => 'عمولات بنكية', 'type' => 'expense', 'parent' => '6000'],
        ];

        $accountMap = [];

        foreach ($accountsData as $accData) {
            $parentId = null;
            if (isset($accData['parent']) && isset($accountMap[$accData['parent']])) {
                $parentId = $accountMap[$accData['parent']];
            }

            $account = Account::create([
                'code' => $accData['code'],
                'name' => $accData['name'],
                'name_ar' => $accData['name_ar'],
                'account_type' => $accData['type'],
                'parent_id' => $parentId,
                'is_system' => $accData['system'] ?? false,
                'company_id' => $companyId,
            ]);

            $accountMap[$accData['code']] = $account->id;
        }
    }
}
