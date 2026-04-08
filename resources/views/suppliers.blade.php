@extends('layouts.app')

@section('title', 'الموردين')

@php
    $canManageSuppliers = auth()->user()->hasPermission('manage_suppliers');
    $canViewReports = auth()->user()->hasPermission('view_reports');
    $suppliersReportUrl = route('reports', ['report_type' => 'payables']);
    $companyCountryLabel = $companyCountry['name_ar'] ?? ($company->country_code ?? 'غير محدد');
@endphp

@section('content')
@php
    $activeSupplierModal = old('supplier_modal');
    $createSupplierModalHasErrors = $errors->any() && $activeSupplierModal === 'create';
@endphp

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-truck"></i> الموردين</h2>
        <p class="text-muted mt-2 mb-0">إدارة قائمة الموردين</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if ($canViewReports)
            <a href="{{ $suppliersReportUrl }}" class="btn btn-outline-primary">
                <i class="fas fa-chart-bar ms-1"></i> مركز التقارير
            </a>
        @endif
        @if ($canManageSuppliers)
            <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                <i class="fas fa-plus ms-1"></i> إضافة مورد جديد
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
    <div class="row">
        <div class="col-md-8 mb-3 mb-md-0">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" placeholder="البحث عن مورد..." id="searchInput">
            </div>
        </div>
        <div class="col-md-4">
            <select class="form-select">
                <option>جميع الموردين</option>
                <option>الموردين النشطين</option>
                <option>الموردين غير النشطين</option>
            </select>
        </div>
    </div>
</div>

@if ($suppliers->isNotEmpty())
    @foreach ($suppliers as $supplier)
        @php
            $editSupplierModalKey = 'edit-' . $supplier->id;
            $editSupplierModalHasErrors = $errors->any() && $activeSupplierModal === $editSupplierModalKey;
            $supplierCountryLabel = $supplier->country ?: $companyCountryLabel;
            $supplierLocationLabel = 'المدينة: ' . ($supplier->city ?: '-') . ' / الدولة: ' . $supplierCountryLabel;
        @endphp
        <div class="list-card supplier-card">
            <div class="row align-items-center">
                <div class="col-md-1 mb-3 mb-md-0">
                    <div class="avatar-circle avatar-green">{{ mb_substr($supplier->name ?? 'S', 0, 1) }}</div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <h5 class="mb-1 fw-bold">{{ $supplier->name }}</h5>
                    <small class="text-muted">{{ $supplier->code }}</small>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="mb-1"><i class="fas fa-envelope ms-2 text-muted"></i>{{ $supplier->email ?: 'لا يوجد بريد' }}</div>
                    <div><i class="fas fa-phone ms-2 text-muted"></i>{{ $supplier->phone ?: 'لا يوجد هاتف' }}</div>
                    <div class="mt-1"><i class="fas fa-map-marker-alt ms-2 text-muted"></i>{{ $supplierLocationLabel }}</div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="mb-1">
                        <strong>الرصيد:</strong>
                        <span class="{{ $supplier->balance > 0 ? 'text-danger' : 'text-success' }} fw-bold">
                            {{ number_format((float) $supplier->balance, 2) }} {{ $company->currency }}
                        </span>
                    </div>
                    <div><strong>الرقم الضريبي:</strong> {{ $supplier->tax_number ?: 'غير محدد' }}</div>
                    <div><strong>عدد المنتجات:</strong> {{ $supplier->products_count }}</div>
                </div>
                <div class="col-md-2 text-start">
                    <span class="badge bg-{{ $supplier->is_active ? 'success' : 'secondary' }} mb-2 d-inline-block">
                        {{ $supplier->is_active ? 'نشط' : 'غير نشط' }}
                    </span>
                    <div class="list-actions-group justify-content-start">
                        <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-sm btn-outline-info shadow-sm" title="عرض بيانات المورد">
                            <i class="fas fa-eye"></i>
                        </a>
                        @if ($canManageSuppliers)
                            <button type="button" class="btn btn-sm btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#editSupplierModal{{ $supplier->id }}" title="تعديل المورد">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف المورد؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm" title="حذف المورد"><i class="fas fa-trash"></i></button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($canManageSuppliers)
            <div class="modal fade" id="editSupplierModal{{ $supplier->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('suppliers.update', $supplier) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="supplier_modal" value="{{ $editSupplierModalKey }}">
                            <div class="modal-header">
                                <h5 class="modal-title">تعديل المورد {{ $supplier->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                @if ($editSupplierModalHasErrors)
                                    <div class="alert alert-danger">
                                        <ul class="mb-0 ps-3">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label">الاسم</label><input type="text" name="name" class="form-control" value="{{ $editSupplierModalHasErrors ? old('name') : $supplier->name }}" required></div>
                                    <div class="col-md-6"><label class="form-label">الاسم بالعربي</label><input type="text" name="name_ar" class="form-control" value="{{ $editSupplierModalHasErrors ? old('name_ar') : $supplier->name_ar }}"></div>
                                    <div class="col-md-6"><label class="form-label">الكود</label><input type="text" name="code" class="form-control" value="{{ $editSupplierModalHasErrors ? old('code') : $supplier->code }}"></div>
                                    <div class="col-md-6"><label class="form-label">الحالة</label><select name="is_active" class="form-select"><option value="1" {{ (string) ($editSupplierModalHasErrors ? old('is_active', $supplier->is_active ? '1' : '0') : ($supplier->is_active ? '1' : '0')) === '1' ? 'selected' : '' }}>نشط</option><option value="0" {{ (string) ($editSupplierModalHasErrors ? old('is_active', $supplier->is_active ? '1' : '0') : ($supplier->is_active ? '1' : '0')) === '0' ? 'selected' : '' }}>غير نشط</option></select></div>
                                    <div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input type="email" name="email" class="form-control" value="{{ $editSupplierModalHasErrors ? old('email') : $supplier->email }}"></div>
                                    <div class="col-md-6"><label class="form-label">الهاتف</label><input type="text" name="phone" class="form-control" value="{{ $editSupplierModalHasErrors ? old('phone') : $supplier->phone }}"></div>
                                    <div class="col-md-6"><label class="form-label">الجوال</label><input type="text" name="mobile" class="form-control" value="{{ $editSupplierModalHasErrors ? old('mobile') : $supplier->mobile }}"></div>
                                    <div class="col-md-6">
                                        <label class="form-label">المدينة</label>
                                        @php
                                            $selectedCity = $editSupplierModalHasErrors ? old('city') : $supplier->city;
                                        @endphp
                                        <select name="city" class="form-select">
                                            <option value="">اختر المدينة</option>
                                            @foreach ($companyCities as $city)
                                                <option value="{{ $city }}" {{ $selectedCity === $city ? 'selected' : '' }}>{{ $city }}</option>
                                            @endforeach
                                            @if ($selectedCity && ! $companyCities->contains($selectedCity))
                                                <option value="{{ $selectedCity }}" selected>{{ $selectedCity }}</option>
                                            @endif
                                        </select>
                                        <div class="form-text">الدولة المعتمدة: {{ $companyCountryLabel }}</div>
                                    </div>
                                    <div class="col-md-6"><label class="form-label">الدولة</label><input type="text" class="form-control" value="{{ $companyCountryLabel }}" readonly></div>
                                    <div class="col-md-6"><label class="form-label">الرقم الضريبي</label><input type="text" name="tax_number" class="form-control" value="{{ $editSupplierModalHasErrors ? old('tax_number') : $supplier->tax_number }}"></div>
                                    <div class="col-md-6"><label class="form-label">الحد الائتماني</label><input type="number" name="credit_limit" class="form-control" min="0" step="0.01" value="{{ $editSupplierModalHasErrors ? old('credit_limit') : $supplier->credit_limit }}" lang="en" dir="ltr"></div>
                                    <div class="col-12"><label class="form-label">العنوان</label><textarea name="address" class="form-control" rows="3">{{ $editSupplierModalHasErrors ? old('address') : $supplier->address }}</textarea></div>
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
        <i class="fas fa-truck fa-4x text-muted mb-3"></i>
        <h4 class="text-muted">لا يوجد موردين</h4>
        <p class="text-muted">ابدأ بإضافة أول مورد</p>
        @if ($canManageSuppliers)
            <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                <i class="fas fa-plus ms-1"></i> إضافة أول مورد
            </button>
        @endif
    </div>
@endif

@if ($canManageSuppliers)
    <div class="modal fade" id="addSupplierModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('suppliers.store') }}">
                    @csrf
                    <input type="hidden" name="supplier_modal" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">إضافة مورد جديد</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @if ($createSupplierModalHasErrors)
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
                            <div class="col-12"><label class="form-label">العنوان</label><textarea name="address" class="form-control" rows="3">{{ old('address') }}</textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إضافة المورد</button>
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
    document.querySelectorAll('.supplier-card').forEach((card) => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(searchTerm) ? 'block' : 'none';
    });
});

@if ($createSupplierModalHasErrors)
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('addSupplierModal');

    if (modalElement && window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
@elseif ($errors->any())
document.addEventListener('DOMContentLoaded', () => {
    const modalId = @json(str_starts_with((string) $activeSupplierModal, 'edit-') ? 'editSupplierModal' . substr((string) $activeSupplierModal, 5) : null);
    const modalElement = modalId ? document.getElementById(modalId) : null;

    if (modalElement && window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
@endif
</script>
@endpush
