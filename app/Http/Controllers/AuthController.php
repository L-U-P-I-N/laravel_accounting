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
use App\Support\ChartOfAccountsSynchronizer;
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

        app(ChartOfAccountsSynchronizer::class)->ensureBaseAccounts($company);

        // Create default tax settings
        if ($taxConfig['vat_rate'] > 0) {
            $vatOutputAccount = Account::where('code', '2105')
                ->where('company_id', $company->id)
                ->first();

            $vatInputAccount = Account::where('code', '2105')
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

}
