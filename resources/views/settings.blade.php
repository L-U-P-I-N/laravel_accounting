@extends('layouts.app')

@section('title', 'الإعدادات')

@php
    $outputTaxSetting = $taxSettings->firstWhere('tax_type', 'output_vat') ?: $taxSettings->firstWhere('tax_type', 'vat');
    $inputTaxSetting = $taxSettings->firstWhere('tax_type', 'input_vat');
    $defaultVatRate = old('vat_rate', $outputTaxSetting?->rate ?? $inputTaxSetting?->rate ?? 15);
    $selectedOutputTaxAccount = (int) old('output_tax_account_id', $outputTaxSetting?->account_id);
    $selectedInputTaxAccount = (int) old('input_tax_account_id', $inputTaxSetting?->account_id);
    $taxReportUrl = route('reports', ['report_type' => 'tax_summary']);
@endphp

@section('content')
<div class="settings-shell">
    @php
        $selectedCountryCode = old('country_code', $company->country_code);
        $selectedCountryConfig = $countries[$selectedCountryCode] ?? $companyCountry;
        $selectedCountryCities = collect($selectedCountryConfig['cities'] ?? []);
        $selectedCity = old('city', $company->city);
    @endphp

    <div class="page-header">
        <h2 class="page-title"><i class="fas fa-cog"></i> الإعدادات</h2>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-md-3 mb-4 mb-md-0">
            <div class="list-card settings-sidebar-card">
                <div class="card-body">
                    <nav class="nav flex-column nav-pills settings-nav responsive-pills">
                        <a class="nav-link active" data-bs-toggle="pill" href="#company-settings"><i class="fas fa-building ms-2"></i>معلومات الشركة</a>
                        <a class="nav-link" data-bs-toggle="pill" href="#user-settings"><i class="fas fa-user ms-2"></i>الملف الشخصي</a>
                        <a class="nav-link" data-bs-toggle="pill" href="#tax-settings"><i class="fas fa-calculator ms-2"></i>إعدادات الضرائب</a>
                        <a class="nav-link" data-bs-toggle="pill" href="#invoice-settings"><i class="fas fa-file-invoice ms-2"></i>إعدادات الفواتير</a>
                        <a class="nav-link" data-bs-toggle="pill" href="#backup-settings"><i class="fas fa-database ms-2"></i>النسخ الاحتياطي</a>
                        <a class="nav-link" data-bs-toggle="pill" href="#security-settings"><i class="fas fa-shield-alt ms-2"></i>الأمان</a>
                    </nav>
                </div>
            </div>
        </div>
        <div class="col-md-9 settings-content-col">
            <div class="tab-content settings-tab-content">
                <div class="tab-pane fade show active" id="company-settings">
                    <div class="list-card">
                        <div class="card-header"><h5 class="mb-0">معلومات الشركة</h5></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('settings.company.update') }}" id="companySettingsForm" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label">شعار الشركة</label>
                                        <div class="d-flex align-items-center gap-3">
                                            @if($company->logo_url)
                                                <img src="{{ asset('storage/' . $company->logo_url) }}" alt="Company Logo" style="height: 60px; max-width: 200px; object-fit: contain;">
                                            @else
                                                <div class="bg-light d-flex align-items-center justify-content-center" style="height: 60px; width: 60px; border-radius: 8px;">
                                                    <i class="bi bi-building text-muted fs-3"></i>
                                                </div>
                                            @endif
                                            <div>
                                                <input type="file" name="logo" class="form-control" accept="image/*">
                                                <small class="text-muted">الصيغ المسموحة: JPG, PNG, GIF. الحد الأقصى: 2MB</small>
                                            </div>
                                            @if($company->logo_url)
                                                <div class="form-check">
                                                    <input type="checkbox" name="remove_logo" class="form-check-input" id="removeLogo">
                                                    <label class="form-check-label" for="removeLogo">إزالة الشعار</label>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6"><label class="form-label">اسم الشركة</label><input type="text" name="name" class="form-control" value="{{ old('name', $company->name) }}" required></div>
                                    <div class="col-md-6"><label class="form-label">الرقم الضريبي</label><input type="text" name="tax_number" class="form-control" value="{{ old('tax_number', $company->tax_number) }}"></div>
                                    <div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input type="email" name="email" class="form-control" value="{{ old('email', $company->email) }}"></div>
                                    <div class="col-md-6"><label class="form-label">رقم الهاتف</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $company->phone) }}"></div>
                                    <div class="col-md-6"><label class="form-label">العنوان</label><input type="text" name="address" class="form-control" value="{{ old('address', $company->address) }}"></div>
                                    <div class="col-md-6">
                                        <label class="form-label">الدولة</label>
                                        <select class="form-select" name="country_code" data-country-select required>
                                            @foreach ($countries as $code => $config)
                                                <option value="{{ $code }}" data-currency="{{ $config['currency'] ?? '' }}" {{ $selectedCountryCode === $code ? 'selected' : '' }}>{{ $config['name_ar'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">المدينة</label>
                                        <select class="form-select" name="city" data-city-select>
                                            <option value="">اختر المدينة</option>
                                            @foreach ($selectedCountryCities as $city)
                                                <option value="{{ $city }}" {{ $selectedCity === $city ? 'selected' : '' }}>{{ $city }}</option>
                                            @endforeach
                                            @if ($selectedCity && ! $selectedCountryCities->contains($selectedCity))
                                                <option value="{{ $selectedCity }}" selected>{{ $selectedCity }}</option>
                                            @endif
                                        </select>
                                    </div>
                                    <div class="col-md-6"><label class="form-label">العملة</label><input type="text" name="currency" class="form-control" value="{{ old('currency', $selectedCountryConfig['currency'] ?? $company->currency) }}" data-currency-input readonly></div>
                                </div>
                                <div class="mt-3"><button type="submit" class="btn btn-primary">حفظ التغييرات</button></div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="user-settings">
                    <div class="list-card">
                        <div class="card-header"><h5 class="mb-0">الملف الشخصي</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">الاسم الأول</label><input type="text" class="form-control" value="{{ auth()->user()->first_name }}"></div>
                                <div class="col-md-6"><label class="form-label">الاسم الأخير</label><input type="text" class="form-control" value="{{ auth()->user()->last_name }}"></div>
                                <div class="col-md-12"><label class="form-label">البريد الإلكتروني</label><input type="email" class="form-control" value="{{ auth()->user()->email }}"></div>
                                <div class="col-md-4"><label class="form-label">كلمة المرور الحالية</label><input type="password" class="form-control"></div>
                                <div class="col-md-4"><label class="form-label">كلمة المرور الجديدة</label><input type="password" class="form-control"></div>
                                <div class="col-md-4"><label class="form-label">تأكيد كلمة المرور</label><input type="password" class="form-control"></div>
                            </div>
                            <div class="mt-3"><button type="button" class="btn btn-primary" disabled>حفظ التغييرات</button></div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tax-settings">
                    <div class="list-card">
                        <div class="card-header"><h5 class="mb-0">إعدادات الضرائب</h5></div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
                                <div>
                                    <p class="text-muted mb-0">اربط حساب ضريبة المخرجات وضريبة المدخلات المستخدمة في القيود الآلية والتقارير الضريبية.</p>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="{{ $taxReportUrl }}" class="btn btn-outline-primary">
                                        <i class="fas fa-chart-pie ms-1"></i> إنشاء تقرير الضرائب
                                    </a>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('settings.taxes.update') }}">
                                @csrf
                                @method('PUT')
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4"><label class="form-label">ضريبة القيمة المضافة (%)</label><input type="number" name="vat_rate" class="form-control" min="0" max="100" step="0.01" value="{{ $defaultVatRate }}" required lang="en" dir="ltr"></div>
                                    <div class="col-md-4"><label class="form-label">حساب ضريبة المخرجات</label><select name="output_tax_account_id" class="form-select" required>@foreach ($accounts->where('account_type', 'liability') as $account)<option value="{{ $account->id }}" {{ $selectedOutputTaxAccount === (int) $account->id ? 'selected' : '' }}>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></div>
                                    <div class="col-md-4"><label class="form-label">حساب ضريبة المدخلات</label><select name="input_tax_account_id" class="form-select" required>@foreach ($accounts->where('account_type', 'asset') as $account)<option value="{{ $account->id }}" {{ $selectedInputTaxAccount === (int) $account->id ? 'selected' : '' }}>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></div>
                                </div>
                                <div class="mt-3"><button type="submit" class="btn btn-primary">حفظ إعدادات الضرائب</button></div>
                            </form>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead><tr><th>الاسم</th><th>النوع</th><th>النسبة</th><th>الحساب المرتبط</th><th>افتراضي</th></tr></thead>
                                    <tbody>
                                        @forelse ($taxSettings as $taxSetting)
                                            <tr>
                                                <td>{{ $taxSetting->name ?? 'إعداد ضريبي' }}</td>
                                                <td>{{ $taxSetting->tax_type === 'input_vat' ? 'ضريبة مدخلات' : 'ضريبة مخرجات' }}</td>
                                                <td>{{ $taxSetting->rate ?? $taxSetting->vat_rate ?? 0 }}%</td>
                                                <td>{{ $taxSetting->account?->code ? $taxSetting->account->code . ' - ' . $taxSetting->account->name : 'غير مرتبط' }}</td>
                                                <td><span class="badge bg-{{ ($taxSetting->is_default ?? false) ? 'success' : 'secondary' }}">{{ ($taxSetting->is_default ?? false) ? 'نعم' : 'لا' }}</span></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="text-center">لا توجد إعدادات ضريبية محفوظة</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="invoice-settings">
                    <div class="list-card">
                        <div class="card-header"><h5 class="mb-0">إعدادات الفواتير</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">بداية التسلسل</label><input type="number" class="form-control" value="1" lang="en" dir="ltr"></div>
                                <div class="col-md-6"><label class="form-label">البادئة</label><input type="text" class="form-control" value="INV-"></div>
                                <div class="col-md-6"><label class="form-label">شروط الدفع بالأيام</label><input type="number" class="form-control" value="30" lang="en" dir="ltr"></div>
                                <div class="col-md-6"><label class="form-label">اللغة الافتراضية</label><input type="text" class="form-control" value="العربية"></div>
                                <div class="col-12"><label class="form-label">ملاحظات الفاتورة</label><textarea class="form-control" rows="3"></textarea></div>
                            </div>
                            <div class="mt-3"><button type="button" class="btn btn-primary" disabled>حفظ الإعدادات</button></div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="backup-settings">
                    <div class="list-card">
                        <div class="card-header"><h5 class="mb-0">النسخ الاحتياطي</h5></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-4 mb-md-0">
                                    <h6>نسخ احتياطي يدوي</h6>
                                    <p>قم بتنزيل نسخة احتياطية كاملة من بياناتك</p>
                                    <button type="button" class="btn btn-primary" disabled><i class="fas fa-download ms-2"></i>إنشاء نسخة احتياطية</button>
                                </div>
                                <div class="col-md-6">
                                    <h6>جدولة النسخ</h6>
                                    <p>يوميًا الساعة 02:00 ص</p>
                                    <button type="button" class="btn btn-outline-primary" disabled>تعديل الجدولة</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="security-settings">
                    <div class="list-card">
                        <div class="card-header"><h5 class="mb-0">الأمان</h5></div>
                        <div class="card-body">
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label">تفعيل الجلسات الآمنة</label></div>
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" disabled><label class="form-check-label">المصادقة الثنائية</label></div>
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label">تسجيل محاولات الدخول</label></div>
                            <button type="button" class="btn btn-primary" disabled>حفظ إعدادات الأمان</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const companyCountryConfigs = @json($countries, JSON_UNESCAPED_UNICODE);
const countrySelect = document.querySelector('[data-country-select]');
const citySelect = document.querySelector('[data-city-select]');
const currencyInput = document.querySelector('[data-currency-input]');

function syncCompanyLocationFields() {
    if (!countrySelect || !citySelect) {
        return;
    }

    const selectedCountry = countrySelect.value;
    const config = companyCountryConfigs[selectedCountry] || {};
    const cities = Array.isArray(config.cities) ? config.cities : [];
    const previousValue = citySelect.value;

    citySelect.innerHTML = '<option value="">اختر المدينة</option>';

    cities.forEach((city) => {
        const option = document.createElement('option');
        option.value = city;
        option.textContent = city;
        option.selected = previousValue === city;
        citySelect.appendChild(option);
    });

    if (currencyInput) {
        currencyInput.value = config.currency || '';
    }
}

countrySelect?.addEventListener('change', syncCompanyLocationFields);
</script>
@endpush
