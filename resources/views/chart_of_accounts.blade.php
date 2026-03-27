@extends('layouts.app')

@section('title', 'شجرة الحسابات')

@php
    $canManageAccounts = auth()->user()->hasPermission('manage_accounts');
    $accountModalErrorFields = ['code', 'name', 'name_ar', 'account_type', 'parent_id', 'description'];
    $accountTypeOptions = [
        'asset' => 'أصل',
        'liability' => 'خصم',
        'equity' => 'حق ملكية',
        'revenue' => 'إيراد',
        'expense' => 'مصروف',
        'cogs' => 'تكلفة مباعة',
    ];
@endphp

@push('styles')
<style>
.account-tree {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

.account-item {
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}

.account-item:hover {
    background: #f8f9fa;
    transform: translateX(5px);
}

.account-item.parent {
    background: linear-gradient(45deg, #f8f9fa, #e9ecef);
    border-left: 4px solid #667eea;
}

.account-type-badge {
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.asset { background: #28a745; color: white; }
.liability { background: #dc3545; color: white; }
.equity { background: #007bff; color: white; }
.revenue { background: #20c997; color: white; }
.expense { background: #fd7e14; color: white; }
.cogs { background: #6f42c1; color: white; }

.filter-card {
    background: #fff;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-sitemap"></i> شجرة الحسابات</h2>
        <p class="text-muted mt-2 mb-0">إدارة الحسابات المحاسبية</p>
    </div>
    @if ($canManageAccounts)
        <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addAccountModal">
            <i class="fas fa-plus ms-1"></i> إضافة حساب جديد
        </button>
    @endif
</div>

<div class="row mb-4">
    <div class="col-12 mb-3">
        <div class="filter-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="mb-1">البحث والفلترة</h5>
                    <p class="text-muted mb-0">ابحث عن حساب بالاسم أو الكود، وفلتر حسب النوع أو نطاق الرصيد.</p>
                </div>
                @if ($hasAccountFilters)
                    <span class="badge text-bg-primary">{{ $matchingAccounts->count() }} نتيجة مطابقة</span>
                @endif
            </div>

            <form method="GET" action="{{ route('chart_of_accounts') }}" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">بحث عن حساب</label>
                    <input type="text" name="search" class="form-control" value="{{ $accountFilters['search'] ?? '' }}" placeholder="اسم الحساب أو الكود">
                </div>
                <div class="col-md-3">
                    <label class="form-label">نوع الحساب</label>
                    <select name="account_type" class="form-select">
                        <option value="">كل الأنواع</option>
                        @foreach ($accountTypeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(($accountFilters['account_type'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">أقل مبلغ</label>
                    <input type="number" step="0.01" name="min_balance" class="form-control" value="{{ $accountFilters['min_balance'] ?? '' }}" placeholder="0.00">
                </div>
                <div class="col-md-2">
                    <label class="form-label">أعلى مبلغ</label>
                    <input type="number" step="0.01" name="max_balance" class="form-control" value="{{ $accountFilters['max_balance'] ?? '' }}" placeholder="0.00">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">فلترة</button>
                </div>
                <div class="col-12 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="fas fa-search ms-1"></i>بحث
                    </button>
                    <a href="{{ route('chart_of_accounts') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-undo ms-1"></i>مسح الفلاتر
                    </a>
                </div>
            </form>
        </div>
    </div>

    @foreach (['asset' => 'أصول', 'liability' => 'خصوم', 'equity' => 'ملكية', 'revenue' => 'إيرادات', 'expense' => 'مصروفات', 'cogs' => 'تكلفة'] as $type => $label)
        <div class="col-md-2 mb-3 mb-md-0">
            <div class="text-center p-3 bg-white rounded">
                <div class="account-type-badge {{ $type }} mb-2">{{ $label }}</div>
                <h4>{{ $accountStats->where('account_type', $type)->count() }}</h4>
            </div>
        </div>
    @endforeach
</div>

<div class="account-tree">
    <h5 class="mb-3"><i class="fas fa-sitemap ms-2 text-primary"></i> هيكل الحسابات</h5>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($hasAccountFilters)
        <div class="alert alert-info">
            تم تطبيق الفلاتر على الشجرة. يتم عرض الحسابات المطابقة مع حساباتها الأب لإبقاء الهيكل واضحًا.
        </div>
    @endif
    @forelse ($accounts->whereNull('parent_id') as $account)
        @include('partials.account_node', ['account' => $account, 'company' => $company, 'level' => 0, 'canManageAccounts' => $canManageAccounts])
    @empty
        <div class="text-center py-5 text-muted">{{ $hasAccountFilters ? 'لا توجد حسابات مطابقة للفلاتر المحددة' : 'لا توجد حسابات بعد' }}</div>
    @endforelse
</div>

@if ($canManageAccounts)
    <div class="modal fade" id="addAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة حساب جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('chart_of_accounts.store') }}" id="addAccountForm">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info">
                            سيتم اقتراح الحساب الأب تلقائيًا حسب نوع الحساب، ويمكنك تغييره يدويًا قبل الحفظ.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">الكود</label>
                                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" required>
                                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الاسم</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الاسم بالعربي</label>
                                <input type="text" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar') }}">
                                @error('name_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نوع الحساب</label>
                                <select name="account_type" id="accountTypeSelect" class="form-select @error('account_type') is-invalid @enderror" required>
                                    @foreach ($accountTypeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('account_type', 'asset') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('account_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">الحساب الأب</label>
                                <select name="parent_id" id="parentAccountSelect" class="form-select @error('parent_id') is-invalid @enderror">
                                    <option value="">بدون أب</option>
                                    @foreach ($parentOptions as $option)
                                        <option value="{{ $option['id'] }}" data-type="{{ $option['type'] }}" @selected((string) old('parent_id') === (string) $option['id'])>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">سيتم اختيار الأب المقترح تلقائيًا حسب النوع، ويمكن تغييره هنا.</div>
                                @error('parent_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">الوصف</label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="accountIsActive" @checked(old('is_active', '1') == '1')>
                                    <label class="form-check-label" for="accountIsActive">حساب نشط</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إضافة حساب</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const accountTypeSelect = document.getElementById('accountTypeSelect');
    const parentAccountSelect = document.getElementById('parentAccountSelect');
    const suggestedParents = @json($suggestedParentIds ?? []);
    const hasOldParent = @json(old('parent_id'));
    const shouldOpenModal = @json($errors->hasAny($accountModalErrorFields));

    function applySuggestedParent() {
        if (!accountTypeSelect || !parentAccountSelect) {
            return;
        }

        const suggestedParentId = suggestedParents[accountTypeSelect.value] ?? '';
        parentAccountSelect.value = suggestedParentId ? String(suggestedParentId) : '';
    }

    if (accountTypeSelect && parentAccountSelect) {
        if (!hasOldParent) {
            applySuggestedParent();
        }

        accountTypeSelect.addEventListener('change', applySuggestedParent);
    }

    if (shouldOpenModal) {
        const modalElement = document.getElementById('addAccountModal');
        if (modalElement && window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
    }
});
</script>
@endpush
