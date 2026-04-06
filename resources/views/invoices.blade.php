@extends('layouts.app')

@section('title', 'المبيعات')

@php
    $canManageInvoices = auth()->user()->hasPermission('manage_invoices');
    $canViewReports = auth()->user()->hasPermission('view_reports');
    $invoicesReportUrl = route('reports', ['report_type' => 'receivables']);
@endphp

@section('content')
<div class="page-header">
    <div>

        <h2 class="page-title"><i class="fas fa-file-invoice"></i> المبيعات</h2>
        <p class="text-muted mt-2 mb-0">إدارة عمليات المبيعات</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if ($canViewReports)
            <a href="{{ $invoicesReportUrl }}" class="btn btn-outline-primary">
                <i class="fas fa-chart-bar ms-1"></i> مركز التقارير
            </a>
            
        @endif


        @if ($canManageInvoices)
            <a href="{{ route('invoices.create') }}" class="btn btn-gradient">
                <i class="fas fa-plus ms-1"></i> إضافة مبيعات
            </a>
        @endif
    </div>
</div>

<div class="filter-tabs">
    <ul class="nav nav-pills responsive-pills">
        @foreach ($tabs as $value => $tab)
            <li class="nav-item">
                <a class="nav-link {{ $statusFilter === $value ? 'active' : '' }}" href="{{ route('invoices', ['status' => $value, 'sort_direction' => $sortDirection]) }}">
                    <i class="fas {{ $tab['icon'] }} ms-2"></i>{{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</div>

<div class="list-card mb-4">
    <form method="GET" action="{{ route('invoices') }}" class="row g-3 align-items-end">
        <input type="hidden" name="status" value="{{ $statusFilter }}">
        <div class="col-lg-3 col-md-6">
            <label class="form-label">ترتيب التاريخ</label>
            <select name="sort_direction" class="form-select">
                <option value="desc" {{ $sortDirection === 'desc' ? 'selected' : '' }}>الأحدث أولاً</option>
                <option value="asc" {{ $sortDirection === 'asc' ? 'selected' : '' }}>الأقدم أولاً</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-6 d-grid">
            <button type="submit" class="btn btn-primary">تطبيق</button>
        </div>
        <div class="col-lg-2 col-md-6 d-grid">
            <a href="{{ route('invoices', ['status' => $statusFilter]) }}" class="btn btn-outline-secondary">إعادة تعيين</a>
        </div>
    </form>
</div>

@if ($invoices->isNotEmpty())
    @foreach ($invoices as $invoice)
        @php
            $effectiveStatus = match (true) {
                $invoice->status === 'draft' => 'draft',
                (float) $invoice->paid_amount >= (float) $invoice->total => 'paid',
                $invoice->due_date && $invoice->due_date->isPast() && (float) $invoice->paid_amount < (float) $invoice->total => 'overdue',
                (float) $invoice->paid_amount > 0 && (float) $invoice->paid_amount < (float) $invoice->total => 'partial',
                default => 'sent',
            };
            $statusClass = match ($effectiveStatus) {
                'paid' => 'success',
                'sent' => 'warning',
                'partial' => 'info',
                'overdue' => 'danger',
                default => 'secondary',
            };
            $statusText = match ($effectiveStatus) {
                'paid' => 'مدفوعة',
                'sent' => 'مرسلة',
                'partial' => 'مدفوعة جزئياً',
                'overdue' => 'متأخرة',
                default => 'مسودة',
            };
        @endphp
        <div class="list-card invoice-card">
            <div class="row align-items-center g-3">
                <div class="col-md-3 mb-3 mb-md-0">
                    <h5 class="mb-1 fw-bold">{{ $invoice->invoice_number }}</h5>
                    <small class="text-muted">{{ optional($invoice->invoice_date)->format('Y-m-d') ?: $invoice->invoice_date }}</small>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <strong class="text-dark">{{ $invoice->customer?->name ?? 'عميل غير محدد' }}</strong>
                    <br>
                    <small class="text-muted"><i class="fas fa-envelope ms-1"></i>{{ $invoice->customer?->email ?: 'لا يوجد بريد' }}</small>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <h5 class="mb-1 text-primary fw-bold">{{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency ?: $company->currency }}</h5>
                    <small class="text-muted">المبلغ الإجمالي</small>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <span class="status-badge bg-{{ $statusClass }}">{{ $statusText }}</span>
                </div>
                <div class="col-md-2 text-start list-actions-col">
                    <div class="btn-group list-actions-group">
                        <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary shadow-sm" title="عرض">
                            <i class="fas fa-eye"></i>
                        </a>
                        @if ($canManageInvoices)
                            <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-sm btn-outline-warning shadow-sm ms-1" title="تعديل">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" class="d-inline" onsubmit="return confirm('سيتم حذف الفاتورة وعكس المخزون المرتبط بها. هل تريد المتابعة؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm ms-1" title="حذف" {{ (float) $invoice->paid_amount > 0 ? 'disabled' : '' }}>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        @endif
                        @if ($canManageInvoices && $invoice->status === 'draft')
                            <form method="POST" action="{{ route('invoices.send', $invoice) }}" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-success shadow-sm ms-1" title="اعتماد">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@else
    <div class="text-center py-5">
        <i class="fas fa-file-invoice fa-4x text-muted mb-3"></i>
        <h4 class="text-muted">لا توجد مبيعات</h4>
        <p class="text-muted">ابدأ بإضافة مبيعات جديدة</p>
        @if ($canManageInvoices)
            <a href="{{ route('invoices.create') }}" class="btn btn-gradient">
                <i class="fas fa-plus ms-1"></i> إضافة مبيعات
            </a>
        @endif
    </div>
@endif
@endsection
