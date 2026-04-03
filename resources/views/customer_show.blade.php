@extends('layouts.app')

@section('title', 'تفاصيل العميل')

@php
    $invoiceStatusLabels = [
        'draft' => 'مسودة',
        'sent' => 'مرسلة',
        'partial' => 'مدفوعة جزئياً',
        'paid' => 'مدفوعة',
        'overdue' => 'متأخرة',
        'cancelled' => 'ملغية',
    ];
    $customerCountryLabel = $customer->country ?: ($companyCountry['name_ar'] ?? '-');
    $customerCityLabel = $customer->city ?: '-';
@endphp

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div>
            <h2 class="page-title"><i class="fas fa-user"></i> {{ $customer->name }}</h2>
            <p class="text-muted mt-2 mb-0">صفحة عرض فقط لبيانات العميل وفواتيره المرتبطة.</p>
        </div>
        <div class="list-actions-group">
            <a href="{{ route('customers') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right ms-1"></i> العودة إلى العملاء
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row mb-4">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-file-invoice"></i></div>
                <div class="stat-value">{{ $customer->invoices->count() }}</div>
                <div class="stat-label">عدد الفواتير</div>
            </div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-wallet"></i></div>
                <div class="stat-value">{{ number_format((float) $customer->invoices_total, 2) }}</div>
                <div class="stat-label">إجمالي الفواتير ({{ $company->currency }})</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-value">{{ number_format((float) $customer->balance, 2) }}</div>
                <div class="stat-label">الرصيد المستحق ({{ $company->currency }})</div>
            </div>
        </div>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-lg-5">
            <div class="list-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">البيانات الأساسية</h5>
                    <span class="badge bg-{{ $customer->is_active ? 'success' : 'secondary' }}">{{ $customer->is_active ? 'نشط' : 'غير نشط' }}</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6"><strong>كود العميل</strong><div class="text-muted mt-1">{{ $customer->code }}</div></div>
                    <div class="col-md-6"><strong>الاسم بالعربي</strong><div class="text-muted mt-1">{{ $customer->name_ar ?: '-' }}</div></div>
                    <div class="col-md-6"><strong>البريد الإلكتروني</strong><div class="text-muted mt-1">{{ $customer->email ?: '-' }}</div></div>
                    <div class="col-md-6"><strong>الهاتف</strong><div class="text-muted mt-1">{{ $customer->phone ?: '-' }}</div></div>
                    <div class="col-md-6"><strong>الجوال</strong><div class="text-muted mt-1">{{ $customer->mobile ?: '-' }}</div></div>
                    <div class="col-md-6"><strong>الرقم الضريبي</strong><div class="text-muted mt-1">{{ $customer->tax_number ?: '-' }}</div></div>
                    <div class="col-md-12">
                        <strong>الموقع</strong>
                        <div class="text-muted mt-1">المدينة: {{ $customerCityLabel }}</div>
                        <div class="text-muted">الدولة: {{ $customerCountryLabel }}</div>
                    </div>
                    <div class="col-md-6"><strong>الحد الائتماني</strong><div class="text-muted mt-1">{{ number_format((float) $customer->credit_limit, 2) }} {{ $company->currency }}</div></div>
                    <div class="col-12"><strong>العنوان</strong><div class="text-muted mt-1">{{ $customer->address ?: '-' }}</div></div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="list-card">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">فواتير العميل</h5>
                        <p class="text-muted mb-0">جميع فواتير المبيعات المرتبطة بهذا العميل فقط.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge text-bg-light">{{ $customer->invoices->count() }} فاتورة</span>
                        <form method="GET" action="{{ route('customers.show', $customer) }}" class="d-flex align-items-center gap-2">
                            <select name="sort_direction" class="form-select form-select-sm">
                                <option value="desc" {{ $sortDirection === 'desc' ? 'selected' : '' }}>الأحدث أولاً</option>
                                <option value="asc" {{ $sortDirection === 'asc' ? 'selected' : '' }}>الأقدم أولاً</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-primary">ترتيب</button>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>الإجمالي</th>
                                <th>المدفوع</th>
                                <th>المتبقي</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($customer->invoices->isEmpty())
                                <tr>
                                    <td colspan="6" class="text-center text-muted">لا توجد فواتير مرتبطة بهذا العميل.</td>
                                </tr>
                            @else
                                @foreach ($customer->invoices as $invoice)
                                    @php
                                        $invoiceStatusClass = match ($invoice->status) {
                                            'draft' => 'secondary',
                                            'sent' => 'info',
                                            'partial' => 'primary',
                                            'paid' => 'success',
                                            'overdue' => 'danger',
                                            default => 'secondary',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $invoice->invoice_number }}</td>
                                        <td>{{ optional($invoice->invoice_date)->format('Y-m-d') ?: $invoice->invoice_date }}</td>
                                        <td>{{ number_format((float) $invoice->total, 2) }} {{ $company->currency }}</td>
                                        <td>{{ number_format((float) $invoice->paid_amount, 2) }} {{ $company->currency }}</td>
                                        <td>{{ number_format((float) $invoice->balance_due, 2) }} {{ $company->currency }}</td>
                                        <td><span class="badge bg-{{ $invoiceStatusClass }}">{{ $invoiceStatusLabels[$invoice->status] ?? 'غير محددة' }}</span></td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
