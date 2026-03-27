@extends('layouts.app')

@section('title', 'الفواتير')

@php
    $canManageInvoices = auth()->user()->hasPermission('manage_invoices');
@endphp

@section('content')
<div class="page-header">
    <div>

        <h2 class="page-title"><i class="fas fa-file-invoice"></i> الفواتير</h2>
        <p class="text-muted mt-2 mb-0">إدارة فواتير المبيعات</p>
    </div>
    @if ($canManageInvoices)
        <a href="{{ route('invoices.create') }}" class="btn btn-gradient">
            <i class="fas fa-plus ms-1"></i> إنشاء فاتورة جديدة
        </a>
    @endif
</div>

<div class="filter-tabs">
    <ul class="nav nav-pills responsive-pills">
        @foreach ($tabs as $value => $tab)
            <li class="nav-item">
                <a class="nav-link {{ $statusFilter === $value ? 'active' : '' }}" href="{{ route('invoices', ['status' => $value]) }}">
                    <i class="fas {{ $tab['icon'] }} ms-2"></i>{{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</div>

@if ($invoices->isNotEmpty())
    @foreach ($invoices as $invoice)
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
                    @php
                        $statusClass = match ($invoice->status) {
                            'paid' => 'success',
                            'sent', 'partial' => 'warning',
                            'overdue' => 'danger',
                            default => 'secondary',
                        };
                        $statusText = match ($invoice->status) {
                            'paid' => 'مدفوعة',
                            'sent' => 'مرسلة',
                            'partial' => 'مدفوعة جزئياً',
                            'overdue' => 'متأخرة',
                            default => 'مسودة',
                        };
                    @endphp
                    <span class="status-badge bg-{{ $statusClass }}">{{ $statusText }}</span>
                </div>
                <div class="col-md-2 text-start list-actions-col">
                    <div class="btn-group list-actions-group">
                        <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary shadow-sm" title="عرض">
                            <i class="fas fa-eye"></i>
                        </a>
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
        <h4 class="text-muted">لا توجد فواتير</h4>
        <p class="text-muted">ابدأ بإنشاء فاتورة جديدة</p>
        @if ($canManageInvoices)
            <a href="{{ route('invoices.create') }}" class="btn btn-gradient">
                <i class="fas fa-plus ms-1"></i> إنشاء أول فاتورة
            </a>
        @endif
    </div>
@endif
@endsection
