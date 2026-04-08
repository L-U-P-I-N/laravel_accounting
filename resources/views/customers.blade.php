@extends('layouts.app')

@section('title', 'العملاء')

@php
    $currentUser = request()->user();
    $canManageCustomers = $currentUser?->hasPermission('manage_customers') ?? false;
    $canViewReports = $currentUser?->hasPermission('view_reports') ?? false;
    $customersReportUrl = route('reports', ['report_type' => 'receivables']);
    $companyCountryLabel = $companyCountry['name_ar'] ?? ($company->country_code ?? 'غير محدد');
    $selectedCustomerCityFilter = $customerFilters['city'] ?? '';
    $selectedCustomerStatusFilter = $customerFilters['status'] ?? '';
@endphp

@section('content')
@php
    $companyCountryLabel = $companyCountry['name_ar'] ?? ($company->country_code ?? 'غير محدد');
    $activeCustomerModal = old('customer_modal');
    $createCustomerModalHasErrors = $errors->any() && $activeCustomerModal === 'create';
@endphp

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-users"></i> العملاء</h2>
        <p class="text-muted mt-2 mb-0">إدارة قائمة العملاء بحسب دولة الشركة ومدنها المعتمدة.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if ($canViewReports)
            <a href="{{ $customersReportUrl }}" class="btn btn-outline-primary">
                <i class="fas fa-chart-bar ms-1"></i> مركز التقارير
            </a>
        @endif
        @if ($canManageCustomers)
            <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                <i class="fas fa-plus ms-1"></i> إضافة عميل جديد
            </button>
        @endif
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="search-box">
    <form method="GET" action="{{ route('customers') }}" class="row g-3 align-items-end">
        <div class="col-lg-4 col-md-6 mb-3 mb-md-0">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" placeholder="البحث عن عميل..." id="searchInput">
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">المدينة</label>
            <select name="city" class="form-select">
                <option value="">كل المدن</option>
                @foreach ($companyCities as $city)
                    <option value="{{ $city }}" {{ $selectedCustomerCityFilter === $city ? 'selected' : '' }}>{{ $city }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">الحالة</label>
            <select name="status" class="form-select">
                <option value="">كل الحالات</option>
                <option value="active" {{ $selectedCustomerStatusFilter === 'active' ? 'selected' : '' }}>نشط</option>
                <option value="inactive" {{ $selectedCustomerStatusFilter === 'inactive' ? 'selected' : '' }}>غير نشط</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-6 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-filter ms-1"></i>تصفية</button>
            <a href="{{ route('customers') }}" class="btn btn-outline-secondary flex-fill">إعادة ضبط</a>
        </div>
        <div class="col-12">
            <div class="form-control bg-light d-flex align-items-center">الدولة المعتمدة للعملاء: {{ $companyCountryLabel }}</div>
        </div>
        <div class="col-12 d-flex justify-content-between flex-wrap gap-2 text-muted small">
            <span>النتائج المعروضة: {{ $customerFilters['shown'] ?? $customers->count() }} من {{ $customerFilters['total'] ?? $customers->count() }}</span>
            @if ($selectedCustomerCityFilter || $selectedCustomerStatusFilter)
                <span>الفلاتر النشطة: {{ $selectedCustomerCityFilter ?: 'كل المدن' }}{{ $selectedCustomerStatusFilter ? ' / ' . ($selectedCustomerStatusFilter === 'active' ? 'نشط' : 'غير نشط') : '' }}</span>
            @endif
        </div>
    </form>
</div>

@if ($customers->isNotEmpty())
    @foreach ($customers as $customer)
        @php
            $editCustomerModalKey = 'edit-' . $customer->id;
            $editCustomerModalHasErrors = $errors->any() && $activeCustomerModal === $editCustomerModalKey;
            $selectedCustomerCity = $editCustomerModalHasErrors ? old('city') : $customer->city;
            $customerCountryLabel = $customer->country ?: $companyCountryLabel;
            $customerLocationLabel = 'المدينة: ' . ($customer->city ?: '-') . ' / الدولة: ' . $customerCountryLabel;
        @endphp
        <div class="list-card customer-card">
            <div class="row align-items-center g-3">
                <div class="col-md-1 mb-3 mb-md-0">
                    <div class="avatar-circle avatar-blue">{{ mb_substr($customer->name ?? 'C', 0, 1) }}</div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <h5 class="mb-1 fw-bold">{{ $customer->name }}</h5>
                    <small class="text-muted">{{ $customer->code }}</small>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="mb-1"><i class="fas fa-envelope ms-2 text-muted"></i>{{ $customer->email ?: 'لا يوجد بريد' }}</div>
                    <div class="mb-1"><i class="fas fa-phone ms-2 text-muted"></i>{{ $customer->phone ?: 'لا يوجد هاتف' }}</div>
                    <div><i class="fas fa-map-marker-alt ms-2 text-muted"></i>{{ $customerLocationLabel }}</div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="mb-1"><strong>الرصيد:</strong> <span class="{{ $customer->balance >= 0 ? 'text-success' : 'text-danger' }} fw-bold">{{ number_format((float) $customer->balance, 2) }} {{ $company->currency }}</span></div>
                    <div class="mb-1"><strong>حد الائتمان:</strong> {{ number_format((float) $customer->credit_limit, 2) }} {{ $company->currency }}</div>
                    <div><strong>الرقم الضريبي:</strong> {{ $customer->tax_number ?: 'غير محدد' }}</div>
                </div>
                <div class="col-md-2 text-start list-actions-col">
                    <span class="badge bg-{{ $customer->is_active ? 'success' : 'secondary' }} mb-2 d-inline-block">{{ $customer->is_active ? 'نشط' : 'غير نشط' }}</span>
                    @if ($canManageCustomers)
                        <div class="list-actions-group">
                            <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-outline-info shadow-sm" title="عرض بيانات العميل">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#editCustomerModal{{ $customer->id }}" title="تعديل العميل">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف العميل؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm" title="حذف العميل"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($canManageCustomers)
            <div class="modal fade" id="editCustomerModal{{ $customer->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-fullscreen-sm-down">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('customers.update', $customer) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="customer_modal" value="{{ $editCustomerModalKey }}">
                            <div class="modal-header">
                                <h5 class="modal-title">تعديل العميل {{ $customer->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                @if ($editCustomerModalHasErrors)
                                    <div class="alert alert-danger">
                                        <ul class="mb-0 ps-3">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label">الاسم</label><input type="text" name="name" class="form-control" value="{{ $editCustomerModalHasErrors ? old('name') : $customer->name }}" required></div>
                                    <div class="col-md-6"><label class="form-label">الاسم بالعربي</label><input type="text" name="name_ar" class="form-control" value="{{ $editCustomerModalHasErrors ? old('name_ar') : $customer->name_ar }}"></div>
                                    <div class="col-md-6"><label class="form-label">الكود</label><input type="text" name="code" class="form-control" value="{{ $editCustomerModalHasErrors ? old('code') : $customer->code }}"></div>
                                    <div class="col-md-6"><label class="form-label">الحالة</label><select name="is_active" class="form-select"><option value="1" {{ (string) ($editCustomerModalHasErrors ? old('is_active', $customer->is_active ? '1' : '0') : ($customer->is_active ? '1' : '0')) === '1' ? 'selected' : '' }}>نشط</option><option value="0" {{ (string) ($editCustomerModalHasErrors ? old('is_active', $customer->is_active ? '1' : '0') : ($customer->is_active ? '1' : '0')) === '0' ? 'selected' : '' }}>غير نشط</option></select></div>
                                    <div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input type="email" name="email" class="form-control" value="{{ $editCustomerModalHasErrors ? old('email') : $customer->email }}"></div>
                                    <div class="col-md-6"><label class="form-label">الهاتف</label><input type="text" name="phone" class="form-control" value="{{ $editCustomerModalHasErrors ? old('phone') : $customer->phone }}"></div>
                                    <div class="col-md-6"><label class="form-label">الجوال</label><input type="text" name="mobile" class="form-control" value="{{ $editCustomerModalHasErrors ? old('mobile') : $customer->mobile }}"></div>
                                    <div class="col-md-6">
                                        <label class="form-label">المدينة</label>
                                        <select name="city" class="form-select">
                                            <option value="">اختر المدينة</option>
                                            @foreach ($companyCities as $city)
                                                <option value="{{ $city }}" {{ $selectedCustomerCity === $city ? 'selected' : '' }}>{{ $city }}</option>
                                            @endforeach
                                            @if ($selectedCustomerCity && ! $companyCities->contains($selectedCustomerCity))
                                                <option value="{{ $selectedCustomerCity }}" selected>{{ $selectedCustomerCity }}</option>
                                            @endif
                                        </select>
                                        <div class="form-text">الدولة المعتمدة: {{ $companyCountryLabel }}</div>
                                    </div>
                                    <div class="col-md-6"><label class="form-label">الدولة</label><input type="text" class="form-control" value="{{ $companyCountryLabel }}" readonly></div>
                                    <div class="col-md-6"><label class="form-label">الرقم الضريبي</label><input type="text" name="tax_number" class="form-control" value="{{ $editCustomerModalHasErrors ? old('tax_number') : $customer->tax_number }}"></div>
                                    <div class="col-md-6"><label class="form-label">الحد الائتماني</label><input type="number" name="credit_limit" class="form-control" min="0" step="0.01" value="{{ $editCustomerModalHasErrors ? old('credit_limit') : $customer->credit_limit }}" lang="en" dir="ltr"></div>
                                    <div class="col-12"><label class="form-label">العنوان</label><textarea name="address" class="form-control" rows="2">{{ $editCustomerModalHasErrors ? old('address') : $customer->address }}</textarea></div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@else
    <div class="text-center py-5">
        <i class="fas fa-users fa-4x text-muted mb-3"></i>
        <h4 class="text-muted">لا يوجد عملاء</h4>
        <p class="text-muted">ابدأ بإضافة أول عميل</p>
        @if ($canManageCustomers)
            <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                <i class="fas fa-plus ms-1"></i> إضافة أول عميل
            </button>
        @endif
    </div>
@endif

@if ($canManageCustomers)
    <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down">
            <div class="modal-content">
                <form method="POST" action="{{ route('customers.store') }}">
                    @csrf
                    <input type="hidden" name="customer_modal" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">إضافة عميل جديد</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @if ($createCustomerModalHasErrors)
                            <div class="alert alert-danger">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">الاسم</label><input type="text" name="name" class="form-control" value="{{ old('name') }}" required></div>
                            <div class="col-md-6"><label class="form-label">الاسم بالعربي</label><input type="text" name="name_ar" class="form-control" value="{{ old('name_ar') }}"></div>
                            <div class="col-md-6"><label class="form-label">الكود</label><input type="text" name="code" class="form-control" value="{{ old('code') }}" placeholder="يُنشأ تلقائيًا إذا تُرك فارغًا"></div>
                            <div class="col-md-6"><label class="form-label">الحالة</label><select name="is_active" class="form-select"><option value="1" {{ old('is_active', '1') === '1' ? 'selected' : '' }}>نشط</option><option value="0" {{ old('is_active') === '0' ? 'selected' : '' }}>غير نشط</option></select></div>
                            <div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input type="email" name="email" class="form-control" value="{{ old('email') }}"></div>
                            <div class="col-md-6"><label class="form-label">الهاتف</label><input type="text" name="phone" class="form-control" value="{{ old('phone') }}"></div>
                            <div class="col-md-6"><label class="form-label">الجوال</label><input type="text" name="mobile" class="form-control" value="{{ old('mobile') }}"></div>
                            <div class="col-md-6">
                                <label class="form-label">المدينة</label>
                                <select name="city" class="form-select">
                                    <option value="">اختر المدينة</option>
                                    @foreach ($companyCities as $city)
                                        <option value="{{ $city }}" {{ old('city') === $city ? 'selected' : '' }}>{{ $city }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">الدولة المعتمدة: {{ $companyCountryLabel }}</div>
                            </div>
                            <div class="col-md-6"><label class="form-label">الدولة</label><input type="text" class="form-control" value="{{ $companyCountryLabel }}" readonly></div>
                            <div class="col-md-6"><label class="form-label">الرقم الضريبي</label><input type="text" name="tax_number" class="form-control" value="{{ old('tax_number') }}"></div>
                            <div class="col-md-6"><label class="form-label">الحد الائتماني</label><input type="number" name="credit_limit" class="form-control" min="0" step="0.01" value="{{ old('credit_limit', 0) }}" lang="en" dir="ltr"></div>
                            <div class="col-12"><label class="form-label">العنوان</label><textarea name="address" class="form-control" rows="2">{{ old('address') }}</textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إضافة العميل</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
document.getElementById('searchInput')?.addEventListener('keyup', function () {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('.customer-card').forEach((card) => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(searchTerm) ? 'block' : 'none';
    });
});

@if ($createCustomerModalHasErrors)
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('addCustomerModal');

    if (modalElement && window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
@elseif ($errors->any())
document.addEventListener('DOMContentLoaded', () => {
    const modalId = @json(str_starts_with((string) $activeCustomerModal, 'edit-') ? 'editCustomerModal' . substr((string) $activeCustomerModal, 5) : null);
    const modalElement = modalId ? document.getElementById(modalId) : null;

    if (modalElement && window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
@endif
</script>
@endpush
