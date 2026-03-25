@extends('layouts.app')

@section('title', 'شجرة الحسابات')

@php
    $canManageAccounts = auth()->user()->hasPermission('manage_accounts');
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
    @foreach (['asset' => 'أصول', 'liability' => 'خصوم', 'equity' => 'ملكية', 'revenue' => 'إيرادات', 'expense' => 'مصروفات', 'cogs' => 'تكلفة'] as $type => $label)
        <div class="col-md-2 mb-3 mb-md-0">
            <div class="text-center p-3 bg-white rounded">
                <div class="account-type-badge {{ $type }} mb-2">{{ $label }}</div>
                <h4>{{ $accounts->where('account_type', $type)->count() }}</h4>
            </div>
        </div>
    @endforeach
</div>

<div class="account-tree">
    <h5 class="mb-3"><i class="fas fa-sitemap ms-2 text-primary"></i> هيكل الحسابات</h5>
    @forelse ($accounts->whereNull('parent_id') as $account)
        @include('partials.account_node', ['account' => $account, 'company' => $company, 'level' => 0, 'canManageAccounts' => $canManageAccounts])
    @empty
        <div class="text-center py-5 text-muted">لا توجد حسابات بعد</div>
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
                <div class="modal-body">
                    <div class="alert alert-info">تم نقل تصميم نافذة إضافة الحساب. ربطها بالحفظ الفعلي سيأتي لاحقًا.</div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">الكود</label><input type="text" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">الاسم</label><input type="text" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">الاسم بالعربي</label><input type="text" class="form-control"></div>
                        <div class="col-md-6">
                            <label class="form-label">نوع الحساب</label>
                            <select class="form-select">
                                <option>أصل</option><option>خصم</option><option>حق ملكية</option><option>إيراد</option><option>مصروف</option><option>تكلفة مباعة</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" disabled>إضافة حساب</button>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
