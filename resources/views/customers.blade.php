@extends('layouts.app')

@section('title', 'العملاء')

@php
    $canManageCustomers = auth()->user()->hasPermission('manage_customers');
@endphp

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-users"></i> العملاء</h2>
        <p class="text-muted mt-2 mb-0">إدارة قائمة العملاء</p>
    </div>
    @if ($canManageCustomers)
        <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
            <i class="fas fa-plus ms-1"></i> إضافة عميل جديد
        </button>
    @endif
</div>

<div class="search-box">
    <div class="row g-3">
        <div class="col-md-8 mb-3 mb-md-0">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" placeholder="البحث عن عميل..." id="searchInput">
            </div>
        </div>
        <div class="col-md-4">
            <select class="form-select">
                <option>جميع العملاء</option>
                <option>العملاء النشطين</option>
                <option>العملاء غير النشطين</option>
            </select>
        </div>
    </div>
</div>

@if ($customers->isNotEmpty())
    @foreach ($customers as $customer)
        <div class="list-card customer-card">
            <div class="row align-items-center g-3">
                <div class="col-md-1 mb-3 mb-md-0">
                    <div class="avatar-circle avatar-blue">
                        {{ mb_substr($customer->name ?? 'C', 0, 1) }}
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <h5 class="mb-1 fw-bold">{{ $customer->name }}</h5>
                    <small class="text-muted">{{ $customer->code }}</small>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="mb-1">
                        <i class="fas fa-envelope ms-2 text-muted"></i>
                        {{ $customer->email ?: 'لا يوجد بريد' }}
                    </div>
                    <div>
                        <i class="fas fa-phone ms-2 text-muted"></i>
                        {{ $customer->phone ?: 'لا يوجد هاتف' }}
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="mb-1">
                        <strong>الرصيد:</strong>
                        <span class="{{ $customer->balance >= 0 ? 'text-success' : 'text-danger' }} fw-bold">
                            {{ number_format((float) $customer->balance, 2) }} {{ $company->currency }}
                        </span>
                    </div>
                    <div>
                        <strong>حد الائتمان:</strong>
                        {{ number_format((float) $customer->credit_limit, 2) }} {{ $company->currency }}
                    </div>
                </div>
                <div class="col-md-2 text-start list-actions-col">
                    <span class="badge bg-{{ $customer->is_active ? 'success' : 'secondary' }} mb-2 d-inline-block">
                        {{ $customer->is_active ? 'نشط' : 'غير نشط' }}
                    </span>
                    @if ($canManageCustomers)
                        <div class="list-actions-group">
                            <button type="button" class="btn btn-sm btn-outline-primary shadow-sm">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger shadow-sm ms-1">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
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
                <div class="modal-header">
                    <h5 class="modal-title">إضافة عميل جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">الواجهة منقولة من Flask. عملية الحفظ ستُربط لاحقًا مع CRUD الخاص بـ Laravel.</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">الاسم</label>
                            <input type="text" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الاسم بالعربي</label>
                            <input type="text" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الهاتف</label>
                            <input type="text" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">المدينة</label>
                            <input type="text" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">البلد</label>
                            <input type="text" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">العنوان</label>
                            <textarea class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" disabled>إضافة عميل</button>
                </div>
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
</script>
@endpush
