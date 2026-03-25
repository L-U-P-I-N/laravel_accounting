@extends('layouts.app')

@section('title', 'الإعدادات')

@section('content')
<div class="settings-shell">
    <div class="page-header">
        <h2 class="page-title"><i class="fas fa-cog"></i> الإعدادات</h2>
    </div>

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
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">اسم الشركة</label><input type="text" class="form-control" value="{{ $company->name }}"></div>
                                <div class="col-md-6"><label class="form-label">الرقم الضريبي</label><input type="text" class="form-control" value="{{ $company->tax_number }}"></div>
                                <div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input type="email" class="form-control" value="{{ $company->email }}"></div>
                                <div class="col-md-6"><label class="form-label">رقم الهاتف</label><input type="text" class="form-control" value="{{ $company->phone }}"></div>
                                <div class="col-md-6"><label class="form-label">العنوان</label><input type="text" class="form-control" value="{{ $company->address }}"></div>
                                <div class="col-md-6"><label class="form-label">المدينة</label><input type="text" class="form-control" value="{{ $company->city }}"></div>
                                <div class="col-md-6">
                                    <label class="form-label">الدولة</label>
                                    <select class="form-select">
                                        @foreach ($countries as $code => $config)
                                            <option value="{{ $code }}" {{ $company->country_code === $code ? 'selected' : '' }}>{{ $config['name_ar'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6"><label class="form-label">العملة</label><input type="text" class="form-control" value="{{ $company->currency }}"></div>
                            </div>
                            <div class="mt-3"><button type="button" class="btn btn-primary" disabled>حفظ التغييرات</button></div>
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
                            <div class="row g-3 mb-4">
                                <div class="col-md-4"><label class="form-label">ضريبة القيمة المضافة (%)</label><input type="number" class="form-control" value="15"></div>
                                <div class="col-md-4"><label class="form-label">حساب ضريبة المخرجات</label><select class="form-select">@foreach ($accounts->where('account_type', 'liability') as $account)<option>{{ $account->name }}</option>@endforeach</select></div>
                                <div class="col-md-4"><label class="form-label">حساب ضريبة المدخلات</label><select class="form-select">@foreach ($accounts->where('account_type', 'asset') as $account)<option>{{ $account->name }}</option>@endforeach</select></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead><tr><th>الاسم</th><th>النسبة</th><th>افتراضي</th></tr></thead>
                                    <tbody>
                                        @forelse ($taxSettings as $taxSetting)
                                            <tr>
                                                <td>{{ $taxSetting->name ?? 'إعداد ضريبي' }}</td>
                                                <td>{{ $taxSetting->rate ?? $taxSetting->vat_rate ?? 0 }}%</td>
                                                <td><span class="badge bg-{{ ($taxSetting->is_default ?? false) ? 'success' : 'secondary' }}">{{ ($taxSetting->is_default ?? false) ? 'نعم' : 'لا' }}</span></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3" class="text-center">لا توجد إعدادات ضريبية محفوظة</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3"><button type="button" class="btn btn-primary" disabled>حفظ الإعدادات</button></div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="invoice-settings">
                    <div class="list-card">
                        <div class="card-header"><h5 class="mb-0">إعدادات الفواتير</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">بداية التسلسل</label><input type="number" class="form-control" value="1"></div>
                                <div class="col-md-6"><label class="form-label">البادئة</label><input type="text" class="form-control" value="INV-"></div>
                                <div class="col-md-6"><label class="form-label">شروط الدفع بالأيام</label><input type="number" class="form-control" value="30"></div>
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
