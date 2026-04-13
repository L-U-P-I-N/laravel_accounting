@extends('layouts.app')

@section('title', 'تفاصيل المبيعات')

@php
    $canManageInvoices = auth()->user()->hasPermission('manage_invoices');
    $customerCountryLabel = $invoice->customer?->country ?: 'غير محددة';
    $customerCityLabel = $invoice->customer?->city ?: '-';
@endphp

@push('styles')
<style>
.invoice-view {
    background: #fff;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

.invoice-header {
    border-bottom: 2px solid #667eea;
    padding-bottom: 20px;
    margin-bottom: 30px;
}

.invoice-summary {
    background: linear-gradient(45deg, #f8f9fa, #e9ecef);
    border-radius: 10px;
    padding: 20px;
}

.btn-action {
    border-radius: 20px;
    padding: 8px 20px;
    font-weight: 700;
}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-eye"></i> تفاصيل المبيعات</h2>
        <p class="text-muted mt-2 mb-0">تفاصيل عملية البيع {{ $invoice->invoice_number }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('invoices') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right ms-2"></i>العودة للمبيعات
        </a>
        @if (!empty($journalEntry))
            <a href="{{ route('journal_entries.show', $journalEntry) }}" class="btn btn-outline-success">
                <i class="fas fa-book ms-2"></i>عرض القيد المحاسبي
            </a>
        @endif
    </div>
</div>

<div class="invoice-view">
    <div class="invoice-header">
        <div class="row">
            <div class="col-md-6 mb-4 mb-md-0">
                @if($company->logo_url)
                    <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }}" style="max-height: 80px; max-width: 200px; object-fit: contain; margin-bottom: 15px;">
                @endif
                <h3>{{ $company->name }}</h3>
                <p class="text-muted mb-1">{{ trim(($company->address ?? '') . ' ' . ($company->city ?? '')) }}</p>
                <p class="text-muted mb-1">{{ $company->phone }} @if($company->email) • {{ $company->email }} @endif</p>
                @if ($company->tax_number)
                    <p class="text-muted mb-0">الرقم الضريبي: {{ $company->tax_number }}</p>
                @endif
            </div>
            <div class="col-md-6 text-start">
                <h2 class="text-primary">مبيعات</h2>
                <h4>{{ $invoice->invoice_number }}</h4>
                <p class="mb-1"><strong>التاريخ:</strong> {{ optional($invoice->invoice_date)->format('Y-m-d') }}</p>
                @if ($invoice->due_date)
                    <p class="mb-1"><strong>تاريخ الاستحقاق:</strong> {{ optional($invoice->due_date)->format('Y-m-d') }}</p>
                @endif
                @php
                    $statusText = match ($invoice->status) {
                        'sent' => 'مرسلة',
                        'paid' => 'مدفوعة',
                        'partial' => 'مدفوعة جزئياً',
                        'overdue' => 'متأخرة',
                        default => 'مسودة',
                    };
                    $statusClass = match ($invoice->status) {
                        'sent' => 'info',
                        'paid' => 'success',
                        'partial' => 'warning',
                        'overdue' => 'danger',
                        default => 'secondary',
                    };
                @endphp
                <span class="status-badge bg-{{ $statusClass }}">{{ $statusText }}</span>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6 mb-4 mb-md-0">
            <h5>مبيعات إلى:</h5>
            <strong>{{ $invoice->customer?->name ?? 'عميل غير محدد' }}</strong><br>
            @if ($invoice->customer?->address)
                {{ $invoice->customer->address }}<br>
            @endif
            @if ($invoice->customer)
                <div>المدينة: {{ $customerCityLabel }}</div>
                <div>الدولة: {{ $customerCountryLabel }}</div>
            @endif
            @if ($invoice->customer?->phone)
                {{ $invoice->customer->phone }}<br>
            @endif
            @if ($invoice->customer?->email)
                {{ $invoice->customer->email }}<br>
            @endif
            @if ($invoice->customer?->tax_number)
                الرقم الضريبي: {{ $invoice->customer->tax_number }}
            @endif
        </div>
        <div class="col-md-6 text-start">
            <div class="invoice-summary">
                <h5>ملخص الدفع</h5>
                <p class="mb-1"><strong>المجموع الفرعي:</strong> {{ number_format((float) $invoice->subtotal, 2) }} {{ $invoice->currency ?: $company->currency }}</p>
                <p class="mb-1"><strong>ضريبة القيمة المضافة:</strong> {{ number_format((float) $invoice->tax_amount, 2) }} {{ $invoice->currency ?: $company->currency }}</p>
                <h4 class="text-primary"><strong>الإجمالي:</strong> {{ number_format((float) $invoice->total, 2) }} {{ $invoice->currency ?: $company->currency }}</h4>
                <p class="mb-1"><strong>حالة الدفع:</strong> {{ match ($invoice->payment_status) { 'full', 'paid' => 'دفع كامل', 'partial' => 'دفع جزئي', default => 'آجل' } }}</p>
                <p class="mb-1"><strong>المدفوع:</strong> {{ number_format((float) $invoice->paid_amount, 2) }} {{ $invoice->currency ?: $company->currency }}</p>
                <h4 class="text-{{ (float) $invoice->balance_due <= 0 ? 'success' : 'danger' }}">
                    <strong>الرصيد المتبقي:</strong> {{ number_format((float) $invoice->balance_due, 2) }} {{ $invoice->currency ?: $company->currency }}
                </h4>
                @if ($invoice->attachment_path)
                    <div class="mt-2"><a href="{{ route('invoices.attachment', $invoice) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fas fa-paperclip ms-1"></i>عرض المرفق</a></div>
                @endif
            </div>
        </div>
    </div>

    <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr>
                    <th>الوصف</th>
                    <th class="text-center">الكمية</th>
                    <th class="text-center">السعر</th>
                    <th class="text-center">الضريبة %</th>
                    <th class="text-end">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="text-center">{{ number_format((float) $item->quantity, 2) }}</td>
                        <td class="text-center">{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="text-center">{{ number_format((float) $item->tax_rate, 1) }}%</td>
                        <td class="text-end">{{ number_format((float) $item->total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">لا توجد بنود فاتورة محفوظة في قاعدة البيانات الحالية.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($invoice->notes)
        <div class="row mb-4">
            <div class="col-md-12">
                <h5>ملاحظات:</h5>
                <p>{{ $invoice->notes }}</p>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <button type="button" class="btn btn-info btn-action">
                    <i class="fas fa-envelope ms-2"></i>إرسال بالبريد
                </button>
                @if ($canManageInvoices)
                    <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-warning btn-action">
                        <i class="fas fa-edit ms-2"></i>تعديل
                    </a>
                    <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('سيتم حذف الفاتورة وعكس المخزون والقيد المحاسبي المرتبط بها. هل تريد المتابعة؟');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-action" {{ (float) $invoice->paid_amount > 0 ? 'disabled' : '' }}>
                            <i class="fas fa-trash ms-2"></i>حذف
                        </button>
                    </form>
                @endif
                <a href="{{ route('reports', ['report_type' => 'receivables', 'customer_id' => $invoice->customer_id]) }}" class="btn btn-primary btn-action">
                    <i class="fas fa-chart-bar ms-2"></i>مركز التقارير
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
