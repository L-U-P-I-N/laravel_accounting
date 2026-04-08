@extends('layouts.app')

@section('title', 'تفاصيل المورد')

@php
    $purchaseStatusLabels = [
        'draft' => 'مسودة',
        'pending' => 'في الانتظار',
        'approved' => 'معتمد',
        'partial' => 'مدفوع جزئياً',
        'paid' => 'مدفوع',
        'cancelled' => 'ملغي',
    ];
    $paymentModalHasErrors = $errors->any() && old('supplier_action') === 'payment';
    $supplierCountryLabel = $supplier->country ?: ($companyCountry['name_ar'] ?? '-');
    $supplierCityLabel = $supplier->city ?: '-';
    $hasOutstandingSupplierBalance = (float) $supplier->balance > 0;
@endphp

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div>
            <h2 class="page-title"><i class="fas fa-truck"></i> {{ $supplier->name }}</h2>
            <p class="text-muted mt-2 mb-0">صفحة عرض فقط لبيانات المورد، مشترياته، والمنتجات المرتبطة به.</p>
        </div>
        <div class="list-actions-group">
            <a href="{{ route('suppliers') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right ms-1"></i> العودة إلى الموردين
            </a>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#supplierPaymentModal">
                <i class="fas fa-money-bill-wave ms-1"></i> الدفع
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row mb-4">
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="stat-value">{{ $supplier->purchases_count }}</div>
                <div class="stat-label">عدد فواتير الشراء</div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-box"></i></div>
                <div class="stat-value">{{ $supplier->products_count }}</div>
                <div class="stat-label">المنتجات المرتبطة</div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-wallet"></i></div>
                <div class="stat-value">{{ number_format((float) $supplier->purchases_total, 2) }}</div>
                <div class="stat-label">إجمالي المشتريات ({{ $company->currency }})</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-value">{{ number_format((float) $supplier->balance, 2) }}</div>
                <div class="stat-label">الرصيد المستحق ({{ $company->currency }})</div>
            </div>
        </div>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-lg-5">
            <div class="list-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">البيانات الأساسية</h5>
                    <span class="badge bg-{{ $supplier->is_active ? 'success' : 'secondary' }}">{{ $supplier->is_active ? 'نشط' : 'غير نشط' }}</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6"><strong>كود المورد</strong><div class="text-muted mt-1">{{ $supplier->code }}</div></div>
                    <div class="col-md-6"><strong>الاسم بالعربي</strong><div class="text-muted mt-1">{{ $supplier->name_ar ?: '-' }}</div></div>
                    <div class="col-md-6"><strong>البريد الإلكتروني</strong><div class="text-muted mt-1">{{ $supplier->email ?: '-' }}</div></div>
                    <div class="col-md-6"><strong>الهاتف</strong><div class="text-muted mt-1">{{ $supplier->phone ?: '-' }}</div></div>
                    <div class="col-md-6"><strong>الجوال</strong><div class="text-muted mt-1">{{ $supplier->mobile ?: '-' }}</div></div>
                    <div class="col-md-6"><strong>الرقم الضريبي</strong><div class="text-muted mt-1">{{ $supplier->tax_number ?: '-' }}</div></div>
                    <div class="col-md-12">
                        <strong>الموقع</strong>
                        <div class="text-muted mt-1">المدينة: {{ $supplierCityLabel }}</div>
                        <div class="text-muted">الدولة: {{ $supplierCountryLabel }}</div>
                    </div>
                    <div class="col-md-6"><strong>الحد الائتماني</strong><div class="text-muted mt-1">{{ number_format((float) $supplier->credit_limit, 2) }} {{ $company->currency }}</div></div>
                    <div class="col-12"><strong>العنوان</strong><div class="text-muted mt-1">{{ $supplier->address ?: '-' }}</div></div>
                </div>
            </div>

        </div>

        <div class="col-lg-7">
            <div class="list-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">فواتير المشتريات</h5>
                        <p class="text-muted mb-0">جميع فواتير الشراء المرتبطة بهذا المورد فقط.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge text-bg-light">{{ $supplier->purchases->count() }} فاتورة</span>
                        <form method="GET" action="{{ route('suppliers.show', $supplier) }}" class="d-flex align-items-center gap-2">
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
                            @if ($supplier->purchases->isEmpty())
                                <tr>
                                    <td colspan="6" class="text-center text-muted">لا توجد فواتير مشتريات مرتبطة بهذا المورد.</td>
                                </tr>
                            @else
                                @foreach ($supplier->purchases as $purchase)
                                    @php
                                        $purchaseStatusClass = match ($purchase->status) {
                                            'draft' => 'secondary',
                                            'pending' => 'warning',
                                            'approved' => 'info',
                                            'partial' => 'primary',
                                            'paid' => 'success',
                                            default => 'danger',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $purchase->purchase_number }}</td>
                                        <td>{{ optional($purchase->purchase_date)->format('Y-m-d') ?: $purchase->purchase_date }}</td>
                                        <td>{{ number_format((float) $purchase->total, 2) }} {{ $company->currency }}</td>
                                        <td>{{ number_format((float) $purchase->paid_amount, 2) }} {{ $company->currency }}</td>
                                        <td>{{ number_format((float) $purchase->balance_due, 2) }} {{ $company->currency }}</td>
                                        <td><span class="badge bg-{{ $purchaseStatusClass }}">{{ $purchaseStatusLabels[$purchase->status] ?? 'ملغي' }}</span></td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="list-card">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">المنتجات المرتبطة</h5>
                        <p class="text-muted mb-0">المنتجات التي تم ربطها بهذا المورد داخل النظام.</p>
                    </div>
                    <span class="badge text-bg-light">{{ $supplier->products->count() }} منتج</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>اسم المنتج</th>
                                <th>الكود</th>
                                <th>النوع</th>
                                <th>سعر البيع</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($supplier->products->isEmpty())
                                <tr>
                                    <td colspan="5" class="text-center text-muted">لا توجد منتجات مرتبطة بهذا المورد.</td>
                                </tr>
                            @else
                                @foreach ($supplier->products as $product)
                                    <tr>
                                        <td>{{ $product->name }}</td>
                                        <td>{{ $product->code ?: '-' }}</td>
                                        <td>{{ $product->type === 'service' ? 'خدمة' : 'منتج' }}</td>
                                        <td>{{ number_format((float) $product->sell_price, 2) }} {{ $company->currency }}</td>
                                        <td><span class="badge bg-{{ $product->is_active ? 'success' : 'secondary' }}">{{ $product->is_active ? 'نشط' : 'غير نشط' }}</span></td>
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

<div class="modal fade" id="supplierPaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('suppliers.payments.store', $supplier) }}">
                @csrf
                <input type="hidden" name="supplier_action" value="payment">
                <div class="modal-header">
                    <h5 class="modal-title">تسجيل دفعة للمورد {{ $supplier->name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($paymentModalHasErrors)
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="list-card mb-3">
                        <strong>الرصيد المستحق الحالي:</strong>
                        <div class="mt-2 fw-bold text-danger">{{ number_format((float) $supplier->balance, 2) }} {{ $company->currency }}</div>
                    </div>

                    <label class="form-label">مبلغ الدفع</label>
                    <input type="number" name="payment_amount" class="form-control" min="0.01" max="{{ number_format((float) $supplier->balance, 2, '.', '') }}" step="0.01" value="{{ old('payment_amount') }}" required lang="en" dir="ltr">
                    <label class="form-label mt-3">حساب السداد</label>
                    <select name="payment_account_id" class="form-select" required>
                        <option value="">اختر الحساب</option>
                        @foreach ($paymentAccounts as $account)
                            <option value="{{ $account->id }}" {{ (string) old('payment_account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->code }} - {{ $account->name_ar ?? $account->name }}</option>
                        @endforeach
                    </select>
                    <label class="form-label mt-3">المرجع</label>
                    <input type="text" name="payment_reference" class="form-control" value="{{ old('payment_reference', $suggestedPaymentReference) }}">
                    <label class="form-label mt-3">حالة الدفع</label>
                    <input type="text" class="form-control" value="{{ $hasOutstandingSupplierBalance ? 'تُحتسب تلقائياً حسب الفواتير المفتوحة' : 'لا توجد فواتير مستحقة حالياً' }}" readonly>
                    <small class="text-muted d-block mt-2">يتم توليد المرجع تلقائيًا ويمكنك تعديله إذا لزم.</small>
                    <small class="text-muted d-block mt-2">لا توجد خانة مستقلة لحالة الدفع لأن النظام يوزّع المبلغ تلقائياً على فواتير الشراء المفتوحة ثم يحدد كل فاتورة كمعلقة أو مدفوعة جزئياً أو مدفوعة بالكامل.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">تأكيد الدفع</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
@if ($paymentModalHasErrors)
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('supplierPaymentModal');

    if (modalElement && window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
@endif
</script>
@endpush
