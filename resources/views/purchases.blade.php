@extends('layouts.app')

@php use Illuminate\Support\Facades\Storage; @endphp

@section('title', 'المشتريات')

@php
    $canManagePurchases = auth()->user()->hasPermission('manage_purchases');
    $canViewReports = auth()->user()->hasPermission('view_reports');
@endphp

@section('content')
<div class="container-fluid">
    @php
        $statusOptions = [
            'draft' => 'مسودة',
            'pending' => 'في الانتظار',
            'approved' => 'معتمد',
            'partial' => 'مدفوع جزئياً',
            'paid' => 'مدفوع',
            'cancelled' => 'ملغي',
        ];
        $paymentStatusOptions = [
            'paid' => 'دفع كامل',
            'partial' => 'دفع جزئي',
            'pending' => 'أجل',
        ];
        $purchasesReportParams = array_filter([
            'report_type' => 'payables',
            'supplier_id' => $supplierFilter !== '' ? $supplierFilter : null,
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
        ], fn ($value) => $value !== null && $value !== '');
        $purchaseActivityReportParams = array_filter([
            'group_by' => 'day',
            'date_from' => $dateFrom !== '' ? $dateFrom : null,
            'date_to' => $dateTo !== '' ? $dateTo : null,
            'reference' => request('reference'),
        ], fn ($value) => $value !== null && $value !== '');
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title"><i class="fas fa-shopping-cart"></i> المشتريات</h2>
            <p class="text-muted mt-2 mb-0">إدارة المشتريات ومتابعة طلبات الشراء.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if ($canViewReports)
                <a href="{{ route('reports', $purchasesReportParams) }}" class="btn btn-outline-primary">
                    <i class="fas fa-chart-bar ms-1"></i> مركز التقارير
                </a>
                <a href="{{ route('reports.operations_activity', $purchaseActivityReportParams) }}" class="btn btn-outline-dark">
                    <i class="fas fa-arrow-right-arrow-left ms-1"></i> تقرير الحركة
                </a>
            @endif
            @if ($canManagePurchases)
                <a href="{{ route('purchases.create') }}" class="btn btn-gradient">
                    <i class="fas fa-plus ms-1"></i> إنشاء طلب شراء
                </a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @php
        $activePurchaseModal = old('purchase_modal');
        $createPurchaseModalHasErrors = $errors->any() && $activePurchaseModal === 'create';
    @endphp

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="list-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">الفلاتر</h5>
                        <p class="text-muted mb-0">فلترة طلبات الشراء حسب الحالة والمورد والفترة الزمنية.</p>
                    </div>
                </div>
                <div class="card-body p-0">
                    <form class="row g-3" method="GET" action="{{ route('purchases') }}">
                        <div class="col-md-3">
                            <label class="form-label">الحالة</label>
                            <select name="status" class="form-select">
                                <option value="" {{ $statusFilter === '' ? 'selected' : '' }}>الكل</option>
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" {{ $statusFilter === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">المورد</label>
                            <select name="supplier_id" class="form-select">
                                <option value="" {{ $supplierFilter === '' ? 'selected' : '' }}>الكل</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ (string) $supplierFilter === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">من تاريخ</label>
                            <input type="date" class="form-control" name="date_from" value="{{ $dateFrom }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">إلى تاريخ</label>
                            <input type="date" class="form-control" name="date_to" value="{{ $dateTo }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ترتيب التاريخ</label>
                            <select name="sort_direction" class="form-select">
                                <option value="desc" {{ $sortDirection === 'desc' ? 'selected' : '' }}>الأحدث أولاً</option>
                                <option value="asc" {{ $sortDirection === 'asc' ? 'selected' : '' }}>الأقدم أولاً</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">بحث</button>
                            <a href="{{ route('purchases') }}" class="btn btn-secondary">مسح الفلاتر</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-shopping-cart"></i></div>
                <div class="stat-value">{{ number_format((float) $purchases->sum('total'), 2) }}</div>
                <div class="stat-label">إجمالي المشتريات ({{ $company->currency }})</div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <div class="stat-value">{{ $pendingPurchasesCount }}</div>
                <div class="stat-label">في الانتظار</div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value">{{ $paidPurchasesCount }}</div>
                <div class="stat-label">معتمد / مدفوع</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div>
                <div class="stat-value">{{ number_format((float) $purchases->whereIn('status', ['pending', 'approved', 'partial'])->sum('balance_due'), 2) }}</div>
                <div class="stat-label">الديون المستحقة ({{ $company->currency }})</div>
            </div>
        </div>
    </div>

    <div class="recent-activity">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-1">قائمة المشتريات</h5>
                <p class="text-muted mb-0">عرض منظم لطلبات الشراء مع إجراءات واضحة لكل طلب.</p>
            </div>
            <span class="badge text-bg-light">{{ $purchases->count() }} طلب</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>المورد</th>
                        <th>التاريخ</th>
                        <th>الإجمالي</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                        <th>الحالة</th>
                        <th>حالة الدفع</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($purchases->isEmpty())
                        <tr>
                            <td colspan="9" class="text-center">لا توجد مشتريات</td>
                        </tr>
                    @else
                        @foreach ($purchases as $purchase)
                            @php
                                $purchaseClass = match ($purchase->status) {
                                    'draft' => 'secondary',
                                    'pending' => 'warning',
                                    'approved' => 'info',
                                    'partial' => 'primary',
                                    'paid' => 'success',
                                    default => 'danger',
                                };
                                $purchaseText = $statusOptions[$purchase->status] ?? 'ملغي';
                                $paymentStatusClass = match ($purchase->payment_status) {
                                    'paid' => 'success',
                                    'partial' => 'info',
                                    default => 'warning',
                                };
                                $paymentStatusText = $paymentStatusOptions[$purchase->payment_status] ?? 'غير محدد';
                                $attachmentUrl = $purchase->attachment_path ? route('purchases.attachment', $purchase) : null;
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $purchase->purchase_number }}</div>
                                    <small class="text-muted d-block">{{ $purchase->items->count() }} بند</small>
                                    @if ($purchase->supplier_invoice_number)
                                        <small class="text-muted d-block">فاتورة المورد: {{ $purchase->supplier_invoice_number }}</small>
                                    @endif
                                    @if ($attachmentUrl)
                                        <a href="{{ $attachmentUrl }}" class="d-inline-flex align-items-center gap-1 small mt-1" target="_blank" rel="noopener">
                                            <i class="fas fa-paperclip"></i> عرض الإرفاق
                                        </a>
                                    @endif
                                </td>
                                <td>{{ $purchase->supplier?->name ?? '-' }}</td>
                                <td>{{ optional($purchase->purchase_date)->format('Y-m-d') ?: $purchase->purchase_date }}</td>
                                <td>{{ number_format((float) $purchase->total, 2) }} {{ $company->currency }}</td>
                                <td>{{ number_format((float) $purchase->paid_amount, 2) }} {{ $company->currency }}</td>
                                <td>{{ number_format((float) $purchase->balance_due, 2) }} {{ $company->currency }}</td>
                                <td><span class="badge bg-{{ $purchaseClass }}">{{ $purchaseText }}</span></td>
                                <td><span class="badge bg-{{ $paymentStatusClass }}">{{ $paymentStatusText }}</span></td>
                                <td class="list-actions-col">
                                    <div class="list-actions-group">
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#showPurchaseModal{{ $purchase->id }}" title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        @if ($canManagePurchases)
                                            @if ((float) $purchase->balance_due > 0 && ! in_array($purchase->status, ['draft', 'cancelled'], true))
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#purchasePaymentModal{{ $purchase->id }}" title="تسجيل دفعة">
                                                    <i class="fas fa-money-bill-wave"></i>
                                                </button>
                                            @endif
                                            <a href="{{ route('purchases.edit', $purchase) }}" class="btn btn-sm btn-warning" title="تعديل الطلب">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @if (in_array($purchase->status, ['draft', 'pending'], true))
                                                <form method="POST" action="{{ route('purchases.approve', $purchase) }}" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-sm btn-success" title="اعتماد الطلب">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('purchases.destroy', $purchase) }}" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف طلب الشراء؟');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" title="حذف الطلب">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($purchases as $purchase)
        @php
            $showPurchaseClass = match ($purchase->status) {
                'draft' => 'secondary',
                'pending' => 'warning',
                'approved' => 'info',
                'partial' => 'primary',
                'paid' => 'success',
                default => 'danger',
            };
            $showPaymentStatus = $paymentStatusOptions[$purchase->payment_status] ?? 'غير محدد';
            $showAttachmentUrl = $purchase->attachment_path ? route('purchases.attachment', $purchase) : null;
        @endphp
        <div class="modal fade" id="showPurchaseModal{{ $purchase->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">تفاصيل طلب الشراء {{ $purchase->purchase_number }}</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-3"><div class="list-card mb-0"><strong>المورد:</strong><div class="text-muted mt-1">{{ $purchase->supplier?->name ?? '-' }}</div></div></div>
                            <div class="col-md-3"><div class="list-card mb-0"><strong>تاريخ الشراء:</strong><div class="text-muted mt-1">{{ optional($purchase->purchase_date)->format('Y-m-d') ?: $purchase->purchase_date }}</div></div></div>
                            <div class="col-md-3"><div class="list-card mb-0"><strong>تاريخ الاستحقاق:</strong><div class="text-muted mt-1">{{ optional($purchase->due_date)->format('Y-m-d') ?: ($purchase->due_date ?? '-') }}</div></div></div>
                            <div class="col-md-3"><div class="list-card mb-0"><strong>الحالة:</strong><div class="mt-1"><span class="badge bg-{{ $showPurchaseClass }}">{{ $statusOptions[$purchase->status] ?? 'ملغي' }}</span></div></div></div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4"><div class="list-card mb-0"><strong>رقم فاتورة المورد:</strong><div class="text-muted mt-1">{{ $purchase->supplier_invoice_number ?: '-' }}</div></div></div>
                            <div class="col-md-4"><div class="list-card mb-0"><strong>حالة الدفع:</strong><div class="mt-1"><span class="badge bg-{{ $purchase->payment_status === 'paid' ? 'success' : ($purchase->payment_status === 'partial' ? 'info' : 'warning') }}">{{ $showPaymentStatus }}</span></div></div></div>
                            <div class="col-md-4"><div class="list-card mb-0"><strong>تاريخ الدفع:</strong><div class="text-muted mt-1">{{ optional($purchase->payment_date)->format('Y-m-d') ?: '-' }}</div></div></div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>المنتج</th>
                                        <th>الوصف</th>
                                        <th>الكمية</th>
                                        <th>سعر الحبة</th>
                                        <th>نسبة الضريبة</th>
                                        <th>المبلغ الضريبي</th>
                                        <th>الإجمالي</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchase->items as $item)
                                        <tr>
                                            <td>{{ $item->product?->name ?? '-' }}</td>
                                            <td>{{ $item->description ?: '-' }}</td>
                                            <td>{{ number_format((float) $item->quantity, 2) }}</td>
                                            <td>{{ number_format((float) $item->unit_price, 2) }} {{ $company->currency }}</td>
                                            <td>{{ number_format((float) $item->tax_rate, 2) }}%</td>
                                            <td>{{ number_format((float) $item->tax_amount, 2) }} {{ $company->currency }}</td>
                                            <td>{{ number_format((float) $item->total, 2) }} {{ $company->currency }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-4"><div class="list-card mb-0"><strong>المجموع الفرعي:</strong> <span>{{ number_format((float) $purchase->subtotal, 2) }} {{ $company->currency }}</span></div></div>
                            <div class="col-md-4"><div class="list-card mb-0"><strong>الضريبة:</strong> <span>{{ number_format((float) $purchase->tax_amount, 2) }} {{ $company->currency }}</span></div></div>
                            <div class="col-md-4"><div class="list-card mb-0"><strong>الإجمالي:</strong> <span>{{ number_format((float) $purchase->total, 2) }} {{ $company->currency }}</span></div></div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-4"><div class="list-card mb-0"><strong>المبلغ المدفوع:</strong> <span>{{ number_format((float) $purchase->paid_amount, 2) }} {{ $company->currency }}</span></div></div>
                            <div class="col-md-4"><div class="list-card mb-0"><strong>المتبقي:</strong> <span>{{ number_format((float) $purchase->balance_due, 2) }} {{ $company->currency }}</span></div></div>
                            @if ($showAttachmentUrl)
                                <div class="col-md-4"><div class="list-card mb-0 text-center"><strong>ملف الفاتورة:</strong>
                                    <div class="mt-2"><a href="{{ $showAttachmentUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-download ms-1"></i> تنزيل</a></div>
                                </div></div>
                            @endif
                        </div>

                        @if ($purchase->notes)
                            <div class="list-card mb-0 mt-3">
                                <strong>الملاحظات:</strong>
                                <div class="text-muted mt-2">{{ $purchase->notes }}</div>
                            </div>
                        @endif

                        <div class="list-card mb-0 mt-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <strong>سجل دفعات الشراء</strong>
                                    <div class="text-muted small mt-1">كل دفعة جزئية تُحفظ كسجل مستقل مع المرجع.</div>
                                </div>
                                @if ($canManagePurchases && (float) $purchase->balance_due > 0 && ! in_array($purchase->status, ['draft', 'cancelled'], true))
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#purchasePaymentModal{{ $purchase->id }}">
                                        <i class="fas fa-plus ms-1"></i> إضافة دفعة
                                    </button>
                                @endif
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>التاريخ</th>
                                            <th>المرجع</th>
                                            <th>المبلغ</th>
                                            <th>ملاحظات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($purchase->payments as $payment)
                                            <tr>
                                                <td>{{ optional($payment->payment_date)->format('Y-m-d') ?: '-' }}</td>
                                                <td>{{ $payment->reference ?: '-' }}</td>
                                                <td>{{ number_format((float) $payment->amount, 2) }} {{ $company->currency }}</td>
                                                <td>{{ $payment->notes ?: '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">لا توجد دفعات مسجلة على هذا الطلب حتى الآن.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @if ($canManagePurchases)
        @foreach ($purchases as $purchase)
            @php
                $paymentPurchaseModalKey = 'payment-' . $purchase->id;
                $paymentPurchaseModalHasErrors = $errors->any() && $activePurchaseModal === $paymentPurchaseModalKey;
            @endphp
            <div class="modal fade" id="purchasePaymentModal{{ $purchase->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('purchases.payments.store', $purchase) }}">
                            @csrf
                            <input type="hidden" name="purchase_modal" value="{{ $paymentPurchaseModalKey }}">
                            <div class="modal-header">
                                <div>
                                    <h5 class="modal-title">تسجيل دفعة على {{ $purchase->purchase_number }}</h5>
                                    <div class="text-muted small mt-1">المتبقي الحالي: {{ number_format((float) $purchase->balance_due, 2) }} {{ $company->currency }}</div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                @if ($paymentPurchaseModalHasErrors)
                                    <div class="alert alert-danger">
                                        <ul class="mb-0 ps-3">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">قيمة الدفعة</label>
                                        <input type="number" name="payment_amount" class="form-control" min="0.01" max="{{ number_format((float) $purchase->balance_due, 2, '.', '') }}" step="0.01" value="{{ $paymentPurchaseModalHasErrors ? old('payment_amount') : number_format((float) $purchase->balance_due, 2, '.', '') }}" required lang="en" dir="ltr">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">حساب السداد</label>
                                        <select name="payment_account_id" class="form-select" required>
                                            <option value="">اختر الحساب</option>
                                            @foreach ($paymentAccounts as $account)
                                                <option value="{{ $account->id }}" {{ (string) ($paymentPurchaseModalHasErrors ? old('payment_account_id') : '') === (string) $account->id ? 'selected' : '' }}>{{ $account->code }} - {{ $account->name_ar ?? $account->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">تاريخ الدفعة</label>
                                        <input type="date" name="payment_date" class="form-control" value="{{ $paymentPurchaseModalHasErrors ? old('payment_date', now()->format('Y-m-d')) : now()->format('Y-m-d') }}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">المرجع</label>
                                        <input type="text" name="payment_reference" class="form-control" value="{{ $paymentPurchaseModalHasErrors ? old('payment_reference') : '' }}" placeholder="يُولّد تلقائياً عند تركه فارغاً">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">ملاحظات</label>
                                        <textarea name="payment_notes" class="form-control" rows="3" placeholder="مثال: دفعة مقدمة، تحويل بنكي، أو تسوية جزئية">{{ $paymentPurchaseModalHasErrors ? old('payment_notes') : '' }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                <button type="submit" class="btn btn-primary">تسجيل الدفعة</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    @if ($canManagePurchases)
        @foreach ($purchases as $purchase)
            @php
                $editPurchaseModalKey = 'edit-' . $purchase->id;
                $editPurchaseModalHasErrors = $errors->any() && $activePurchaseModal === $editPurchaseModalKey;
                $itemsSource = $editPurchaseModalHasErrors ? collect(old('item_description', []))->map(function ($description, $index) {
                    return (object) [
                        'product_id' => old('item_product_id.' . $index),
                        'description' => $description,
                        'quantity' => old('item_quantity.' . $index, 1),
                        'unit_price' => old('item_price.' . $index, 0),
                        'cost_price' => old('item_cost_price.' . $index, 0),
                        'tax_rate' => old('item_tax_rate.' . $index, 0),
                    ];
                }) : $purchase->items;
            @endphp
            <div class="modal fade" id="editPurchaseModal{{ $purchase->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('purchases.update', $purchase) }}" class="purchase-form" data-purchase-form data-paid-amount="{{ number_format((float) $purchase->paid_amount, 2, '.', '') }}" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="purchase_modal" value="{{ $editPurchaseModalKey }}">
                            <div class="modal-header">
                                <h5 class="modal-title">تعديل طلب الشراء {{ $purchase->purchase_number }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                @if ($editPurchaseModalHasErrors)
                                    <div class="alert alert-danger">
                                        <ul class="mb-0 ps-3">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">المورد</label>
                                        <select name="supplier_id" class="form-select purchase-supplier-select">
                                            <option value="">اختر المورد</option>
                                            @foreach ($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}" {{ (string) ($editPurchaseModalHasErrors ? old('supplier_id') : $purchase->supplier_id) === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">تاريخ الشراء</label>
                                        <input type="date" name="purchase_date" class="form-control" value="{{ $editPurchaseModalHasErrors ? old('purchase_date') : optional($purchase->purchase_date)->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">تاريخ الاستحقاق</label>
                                        <input type="date" name="due_date" class="form-control" value="{{ $editPurchaseModalHasErrors ? old('due_date') : optional($purchase->due_date)->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">الحالة</label>
                                        <select name="status" class="form-select">
                                            @foreach (['draft' => 'مسودة', 'pending' => 'في الانتظار', 'approved' => 'معتمد'] as $value => $label)
                                                <option value="{{ $value }}" {{ ($editPurchaseModalHasErrors ? old('status', $purchase->status) : $purchase->status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">حالة الدفع</label>
                                        <select name="payment_status" class="form-select" data-payment-status-select>
                                            @foreach ($paymentStatusOptions as $value => $label)
                                                <option value="{{ $value }}" {{ ($editPurchaseModalHasErrors ? old('payment_status', $purchase->payment_status) : $purchase->payment_status) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4" data-paid-amount-container>
                                        <label class="form-label">المبلغ المدفوع</label>
                                        <input type="number" name="paid_amount" class="form-control" min="0" step="0.01" value="{{ $editPurchaseModalHasErrors ? old('paid_amount', number_format((float) $purchase->paid_amount, 2, '.', '')) : number_format((float) $purchase->paid_amount, 2, '.', '') }}" data-purchase-paid-amount lang="en" dir="ltr">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">حساب السداد</label>
                                        <select name="payment_account_id" class="form-select">
                                            <option value="">اختر الحساب</option>
                                            @foreach ($paymentAccounts as $account)
                                                <option value="{{ $account->id }}" {{ (string) ($editPurchaseModalHasErrors ? old('payment_account_id', $purchase->payment_account_id) : $purchase->payment_account_id) === (string) $account->id ? 'selected' : '' }}>{{ $account->code }} - {{ $account->name_ar ?? $account->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">رقم فاتورة المورد (اختياري)</label>
                                        <input type="text" name="supplier_invoice_number" class="form-control" value="{{ $editPurchaseModalHasErrors ? old('supplier_invoice_number') : $purchase->supplier_invoice_number }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">تاريخ الدفع</label>
                                        <input type="date" name="payment_date" class="form-control" value="{{ $editPurchaseModalHasErrors ? old('payment_date') : optional($purchase->payment_date)->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label d-flex justify-content-between align-items-center">
                                            <span>ملف الفاتورة</span>
                                            @if ($purchase->attachment_path)
                                                <a href="{{ Storage::disk('public')->url($purchase->attachment_path) }}" target="_blank" rel="noopener" class="small">عرض الحالي</a>
                                            @endif
                                        </label>
                                        <input type="file" name="attachment" class="form-control" accept="application/pdf,image/*">
                                        <small class="text-muted">PDF أو صورة، حتى 8 ميغابايت</small>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">الملاحظات</label>
                                        <textarea name="notes" class="form-control" rows="2">{{ $editPurchaseModalHasErrors ? old('notes') : $purchase->notes }}</textarea>
                                    </div>
                                </div>
                                <h6 class="mt-4">بنود الطلب</h6>
                                <div class="table-responsive">
                                    <table class="table align-middle">
                                        <thead>
                                            <tr>
                                                <th>المنتج</th>
                                                <th>التكلفة</th>
                                                <th>الوصف</th>
                                                <th>الكمية</th>
                                                <th>سعر البيع</th>
                                                <th>الضريبة %</th>
                                                <th>المبلغ الضريبي</th>
                                                <th>الإجمالي</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody data-purchase-items>
                                            @foreach ($itemsSource as $item)
                                                <tr data-purchase-item-row>
                                                    <td style="position: relative;">
                                                        <input type="hidden" name="item_product_id[]" class="purchase-product-id" value="{{ $item->product_id }}">
                                                        <input type="text" name="item_product_name[]" class="form-control purchase-product-autocomplete"
                                                               value="{{ $item->product_id ? ($products->firstWhere('id', $item->product_id)->name ?? '') : '' }}"
                                                               placeholder="اكتب اسم المنتج..."
                                                               autocomplete="off"
                                                               data-products-json="{{ json_encode($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'description' => $p->description ?? '', 'cost_price' => $p->cost_price ?? 0, 'sell_price' => $p->sell_price ?? 0, 'tax_rate' => $p->tax_rate ?? 0])) }}">
                                                        <div class="product-autocomplete-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 1000;"></div>
                                                    </td>
                                                    <td><input type="number" name="item_cost_price[]" class="form-control purchase-item-cost" min="0" step="0.01" value="{{ $item->cost_price ?? 0 }}" lang="en" dir="ltr"></td>
                                                    <td><input type="text" name="item_description[]" class="form-control purchase-item-description" value="{{ $item->description }}"></td>
                                                    <td><input type="number" name="item_quantity[]" class="form-control purchase-item-quantity" min="0.01" step="0.01" value="{{ $item->quantity }}" lang="en" dir="ltr"></td>
                                                    <td><input type="number" name="item_price[]" class="form-control purchase-item-price" min="0" step="0.01" value="{{ $item->unit_price }}" lang="en" dir="ltr"></td>
                                                    <td><input type="number" name="item_tax_rate[]" class="form-control purchase-item-tax" min="0" max="100" step="0.01" value="{{ $item->tax_rate ?? 0 }}" lang="en" dir="ltr"></td>
                                                    <td><input type="text" class="form-control purchase-item-tax-amount" readonly></td>
                                                    <td><input type="text" class="form-control purchase-item-total" readonly></td>
                                                    <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-purchase-item><i class="fas fa-trash"></i></button></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" class="btn btn-outline-primary" data-add-purchase-item>إضافة بند</button>
                                <div class="row mt-4 g-3">
                                    <div class="col-md-3"><div class="list-card"><strong>المجموع الفرعي:</strong> <span data-purchase-subtotal>0.00 {{ $company->currency }}</span></div></div>
                                    <div class="col-md-3"><div class="list-card"><strong>الضريبة:</strong> <span data-purchase-tax>0.00 {{ $company->currency }}</span></div></div>
                                    <div class="col-md-3"><div class="list-card"><strong>الإجمالي:</strong> <span data-purchase-total>0.00 {{ $company->currency }}</span></div></div>
                                    <div class="col-md-3"><div class="list-card"><strong>المدفوع:</strong> <span data-purchase-paid-summary>{{ number_format((float) $purchase->paid_amount, 2) }} {{ $company->currency }}</span></div></div>
                                    <div class="col-md-3"><div class="list-card"><strong>المتبقي:</strong> <span data-purchase-remaining>{{ number_format((float) $purchase->balance_due, 2) }} {{ $company->currency }}</span></div></div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                <button type="submit" class="btn btn-primary">حفظ</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>

@if ($canManagePurchases)
    <div class="modal fade" id="addPurchaseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="POST" action="{{ route('purchases.store') }}" class="purchase-form" data-purchase-form data-paid-amount="0" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="purchase_modal" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">إنشاء طلب شراء جديد</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @if ($createPurchaseModalHasErrors)
                            <div class="alert alert-danger">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">المورد</label>
                                <select name="supplier_id" class="form-select purchase-supplier-select">
                                    <option value="">اختر المورد</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ (string) old('supplier_id') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">تاريخ الشراء</label>
                                <input type="date" name="purchase_date" class="form-control" value="{{ old('purchase_date', now()->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">تاريخ الاستحقاق</label>
                                <input type="date" name="due_date" class="form-control" value="{{ old('due_date', now()->addDays(30)->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">الحالة</label>
                                <select name="status" class="form-select">
                                    @foreach (['draft' => 'مسودة', 'pending' => 'في الانتظار', 'approved' => 'معتمد'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('status', 'draft') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">حالة الدفع</label>
                                <select name="payment_status" class="form-select" data-payment-status-select>
                                    @foreach ($paymentStatusOptions as $value => $label)
                                        <option value="{{ $value }}" {{ old('payment_status', 'pending') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4" data-paid-amount-container>
                                <label class="form-label">المبلغ المدفوع</label>
                                <input type="number" name="paid_amount" class="form-control" min="0" step="0.01" value="{{ old('paid_amount', '0') }}" data-purchase-paid-amount lang="en" dir="ltr">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">حساب السداد</label>
                                <select name="payment_account_id" class="form-select">
                                    <option value="">اختر الحساب</option>
                                    @foreach ($paymentAccounts as $account)
                                        <option value="{{ $account->id }}" {{ (string) old('payment_account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->code }} - {{ $account->name_ar ?? $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">رقم فاتورة المورد (اختياري)</label>
                                <input type="text" name="supplier_invoice_number" class="form-control" value="{{ old('supplier_invoice_number') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">تاريخ الدفع</label>
                                <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ملف الفاتورة</label>
                                <input type="file" name="attachment" class="form-control" accept="application/pdf,image/*" required>
                                <small class="text-muted">PDF أو صورة، حتى 8 ميغابايت</small>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">الملاحظات</label>
                                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                        <h6 class="mt-4">بنود الطلب</h6>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>المنتج</th>
                                        <th>التكلفة</th>
                                        <th>الوصف</th>
                                        <th>الكمية</th>
                                        <th>سعر البيع</th>
                                        <th>الضريبة %</th>
                                        <th>المبلغ الضريبي</th>
                                        <th>الإجمالي</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-purchase-items>
                                    @php
                                        $createItems = collect(old('item_description', ['']))->map(function ($description, $index) {
                                            return (object) [
                                                'product_id' => old('item_product_id.' . $index),
                                                'description' => $description,
                                                'quantity' => old('item_quantity.' . $index, 1),
                                                'unit_price' => old('item_price.' . $index, 0),
                                                'cost_price' => old('item_cost_price.' . $index, 0),
                                                'tax_rate' => old('item_tax_rate.' . $index, 15),
                                            ];
                                        });
                                    @endphp
                                    @foreach ($createItems as $item)
                                        <tr data-purchase-item-row>
                                            <td style="position: relative;">
                                                <input type="hidden" name="item_product_id[]" class="purchase-product-id" value="{{ $item->product_id }}">
                                                <input type="text" name="item_product_name[]" class="form-control purchase-product-autocomplete"
                                                       value="{{ $item->product_id ? ($products->firstWhere('id', $item->product_id)->name ?? '') : '' }}"
                                                       placeholder="اكتب اسم المنتج..."
                                                       autocomplete="off"
                                                       data-products-json="{{ json_encode($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'description' => $p->description ?? '', 'cost_price' => $p->cost_price ?? 0, 'sell_price' => $p->sell_price ?? 0, 'tax_rate' => $p->tax_rate ?? 0])) }}">
                                                <div class="product-autocomplete-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 1000;"></div>
                                            </td>
                                            <td><input type="number" name="item_cost_price[]" class="form-control purchase-item-cost" min="0" step="0.01" value="{{ $item->cost_price ?? 0 }}" lang="en" dir="ltr"></td>
                                            <td><input type="text" name="item_description[]" class="form-control purchase-item-description" value="{{ $item->description }}"></td>
                                            <td><input type="number" name="item_quantity[]" class="form-control purchase-item-quantity" min="0.01" step="0.01" value="{{ $item->quantity }}" lang="en" dir="ltr"></td>
                                            <td><input type="number" name="item_price[]" class="form-control purchase-item-price" min="0" step="0.01" value="{{ $item->unit_price }}" lang="en" dir="ltr"></td>
                                            <td><input type="number" name="item_tax_rate[]" class="form-control purchase-item-tax" min="0" max="100" step="0.01" value="{{ $item->tax_rate }}" lang="en" dir="ltr"></td>
                                            <td><input type="text" class="form-control purchase-item-tax-amount" readonly></td>
                                            <td><input type="text" class="form-control purchase-item-total" readonly></td>
                                            <td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-purchase-item><i class="fas fa-trash"></i></button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-outline-primary" data-add-purchase-item>إضافة بند</button>
                        <div class="row mt-4 g-3">
                            <div class="col-md-3"><div class="list-card"><strong>المجموع الفرعي:</strong> <span data-purchase-subtotal>0.00 {{ $company->currency }}</span></div></div>
                            <div class="col-md-3"><div class="list-card"><strong>الضريبة:</strong> <span data-purchase-tax>0.00 {{ $company->currency }}</span></div></div>
                            <div class="col-md-3"><div class="list-card"><strong>الإجمالي:</strong> <span data-purchase-total>0.00 {{ $company->currency }}</span></div></div>
                            <div class="col-md-3"><div class="list-card"><strong>المدفوع:</strong> <span data-purchase-paid-summary>0.00 {{ $company->currency }}</span></div></div>
                            <div class="col-md-3"><div class="list-card"><strong>المتبقي:</strong> <span data-purchase-remaining>0.00 {{ $company->currency }}</span></div></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
function purchaseNumericValue(value) {
    const parsedValue = Number.parseFloat(value);

    return Number.isFinite(parsedValue) ? parsedValue : 0;
}

function calculatePurchaseFormTotals(form) {
    let subtotal = 0;
    let taxAmount = 0;

    form.querySelectorAll('[data-purchase-item-row]').forEach((row) => {
        const quantity = purchaseNumericValue(row.querySelector('.purchase-item-quantity')?.value);
        const price = purchaseNumericValue(row.querySelector('.purchase-item-price')?.value);
        const taxRate = purchaseNumericValue(row.querySelector('.purchase-item-tax')?.value);
        const lineSubtotal = quantity * price;
        const lineTax = lineSubtotal * (taxRate / 100);
        const lineTotal = lineSubtotal + lineTax;

        subtotal += lineSubtotal;
        taxAmount += lineTax;

        const totalField = row.querySelector('.purchase-item-total');
        const taxAmountField = row.querySelector('.purchase-item-tax-amount');

        if (taxAmountField) {
            taxAmountField.value = lineTax.toFixed(2);
        }

        if (totalField) {
            totalField.value = lineTotal.toFixed(2);
        }
    });

    const subtotalTarget = form.querySelector('[data-purchase-subtotal]');
    const taxTarget = form.querySelector('[data-purchase-tax]');
    const totalTarget = form.querySelector('[data-purchase-total]');
    const grandTotal = subtotal + taxAmount;
    const paidAmountInput = form.querySelector('[data-purchase-paid-amount]');
    const paidAmountPreview = purchaseNumericValue(paidAmountInput?.value || form.dataset.paidAmount || '0');
    const paidSummaryTarget = form.querySelector('[data-purchase-paid-summary]');
    const remainingTarget = form.querySelector('[data-purchase-remaining]');

    if (subtotalTarget) {
        subtotalTarget.textContent = `${subtotal.toFixed(2)} {{ $company->currency }}`;
    }

    if (taxTarget) {
        taxTarget.textContent = `${taxAmount.toFixed(2)} {{ $company->currency }}`;
    }

    if (totalTarget) {
        totalTarget.textContent = `${grandTotal.toFixed(2)} {{ $company->currency }}`;
    }

    if (paidSummaryTarget) {
        paidSummaryTarget.textContent = `${Math.min(paidAmountPreview, grandTotal).toFixed(2)} {{ $company->currency }}`;
    }

    if (remainingTarget) {
        const remainingValue = Math.max(grandTotal - Math.min(paidAmountPreview, grandTotal), 0);
        remainingTarget.textContent = `${remainingValue.toFixed(2)} {{ $company->currency }}`;
    }
}

function applySelectedPurchaseProduct(row, form, product) {
    const descriptionInput = row.querySelector('.purchase-item-description');
    const costInput = row.querySelector('.purchase-item-cost');
    const priceInput = row.querySelector('.purchase-item-price');
    const taxInput = row.querySelector('.purchase-item-tax');

    if (!product) {
        calculatePurchaseFormTotals(form);
        return;
    }

    const currentCost = purchaseNumericValue(costInput?.value);
    const currentPrice = purchaseNumericValue(priceInput?.value);
    const currentTaxRate = purchaseNumericValue(taxInput?.value);

    if (costInput && (!costInput.value || currentCost === 0 || costInput.dataset.autoFilled === 'true')) {
        costInput.value = product.cost_price || '';
        costInput.dataset.autoFilled = 'true';
    }

    if (descriptionInput && (!descriptionInput.value || descriptionInput.dataset.autoFilled === 'true')) {
        descriptionInput.value = product.description || product.name;
        descriptionInput.dataset.autoFilled = 'true';
    }

    if (priceInput && (!priceInput.value || currentPrice === 0 || priceInput.dataset.autoFilled === 'true')) {
        priceInput.value = product.sell_price || '';
        priceInput.dataset.autoFilled = 'true';
    }

    if (taxInput && (!taxInput.value || currentTaxRate === 0 || taxInput.dataset.autoFilled === 'true')) {
        taxInput.value = product.tax_rate || '0';
        taxInput.dataset.autoFilled = 'true';
    }

    calculatePurchaseFormTotals(form);
}

function bindPurchaseRow(row, form) {
    const autocompleteInput = row.querySelector('.purchase-product-autocomplete');
    const productIdInput = row.querySelector('.purchase-product-id');
    const dropdown = row.querySelector('.product-autocomplete-dropdown');
    const costInput = row.querySelector('.purchase-item-cost');
    const descriptionInput = row.querySelector('.purchase-item-description');
    const priceInput = row.querySelector('.purchase-item-price');
    const taxInput = row.querySelector('.purchase-item-tax');
    const removeButton = row.querySelector('[data-remove-purchase-item]');

    let products = [];
    try {
        products = JSON.parse(autocompleteInput?.dataset.productsJson || '[]');
    } catch (e) {
        products = [];
    }

    // Autocomplete functionality
    if (autocompleteInput && dropdown) {
        autocompleteInput.addEventListener('input', () => {
            const query = autocompleteInput.value.trim().toLowerCase();
            productIdInput.value = ''; // Clear product ID until selected

            if (query.length < 1) {
                dropdown.style.display = 'none';
                return;
            }

            const matches = products.filter(p => p.name.toLowerCase().includes(query));

            if (matches.length > 0) {
                dropdown.innerHTML = matches.map(p =>
                    `<div class="product-suggestion" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;" data-product='${JSON.stringify(p)}'>
                        ${p.name}
                    </div>`
                ).join('');
                dropdown.style.display = 'block';

                // Add click handlers
                dropdown.querySelectorAll('.product-suggestion').forEach(suggestion => {
                    suggestion.addEventListener('click', () => {
                        const product = JSON.parse(suggestion.dataset.product);
                        autocompleteInput.value = product.name;
                        productIdInput.value = product.id;
                        dropdown.style.display = 'none';
                        applySelectedPurchaseProduct(row, form, product);
                    });
                });
            } else {
                dropdown.innerHTML = `<div style="padding: 8px 12px; color: #666;">لم يتم العثور على منتج - سيتم إنشاؤه عند الحفظ</div>`;
                dropdown.style.display = 'block';
            }
        });

        // Hide dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!autocompleteInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Show all products on focus
        autocompleteInput.addEventListener('focus', () => {
            if (autocompleteInput.value.trim() === '') {
                dropdown.innerHTML = products.slice(0, 10).map(p =>
                    `<div class="product-suggestion" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #eee;" data-product='${JSON.stringify(p)}'>
                        ${p.name}
                    </div>`
                ).join('');
                if (products.length > 0) dropdown.style.display = 'block';

                dropdown.querySelectorAll('.product-suggestion').forEach(suggestion => {
                    suggestion.addEventListener('click', () => {
                        const product = JSON.parse(suggestion.dataset.product);
                        autocompleteInput.value = product.name;
                        productIdInput.value = product.id;
                        dropdown.style.display = 'none';
                        applySelectedPurchaseProduct(row, form, product);
                    });
                });
            }
        });
    }

    costInput?.addEventListener('input', () => {
        costInput.dataset.autoFilled = 'false';
    });

    descriptionInput?.addEventListener('input', () => {
        descriptionInput.dataset.autoFilled = 'false';
    });

    priceInput?.addEventListener('input', () => {
        priceInput.dataset.autoFilled = 'false';
        calculatePurchaseFormTotals(form);
    });

    taxInput?.addEventListener('input', () => {
        taxInput.dataset.autoFilled = 'false';
        calculatePurchaseFormTotals(form);
    });

    row.querySelectorAll('.purchase-item-quantity').forEach((input) => {
        input.addEventListener('input', () => calculatePurchaseFormTotals(form));
    });

    removeButton?.addEventListener('click', () => {
        const rows = form.querySelectorAll('[data-purchase-item-row]');
        if (rows.length > 1) {
            row.remove();
            calculatePurchaseFormTotals(form);
        }
    });

    // If product is already selected, apply its data
    if (productIdInput?.value) {
        const selectedProduct = products.find(p => String(p.id) === productIdInput.value);
        if (selectedProduct) {
            applySelectedPurchaseProduct(row, form, selectedProduct);
        }
    } else if (priceInput?.value) {
        calculatePurchaseFormTotals(form);
    }
}

function addPurchaseRow(form) {
    const tbody = form.querySelector('[data-purchase-items]');
    const firstRow = tbody?.querySelector('[data-purchase-item-row]');

    if (!tbody || !firstRow) {
        return;
    }

    const clone = firstRow.cloneNode(true);
    clone.querySelectorAll('input').forEach((input) => {
        if (input.classList.contains('purchase-item-quantity')) {
            input.value = '1';
        } else if (input.classList.contains('purchase-item-cost')) {
            input.value = '';
        } else if (input.classList.contains('purchase-item-tax')) {
            input.value = '15';
        } else if (input.classList.contains('purchase-item-tax-amount')) {
            input.value = '0.00';
        } else if (input.classList.contains('purchase-item-total')) {
            input.value = '0.00';
        } else {
            input.value = '';
        }

        delete input.dataset.autoFilled;
    });

    const productIdInput = clone.querySelector('.purchase-product-id');
    const autocompleteInput = clone.querySelector('.purchase-product-autocomplete');
    const dropdown = clone.querySelector('.product-autocomplete-dropdown');

    if (productIdInput) productIdInput.value = '';
    if (autocompleteInput) {
        autocompleteInput.value = '';
        // Copy products data to new row
        const firstRowInput = firstRow.querySelector('.purchase-product-autocomplete');
        if (firstRowInput) {
            autocompleteInput.dataset.productsJson = firstRowInput.dataset.productsJson;
        }
    }
    if (dropdown) dropdown.style.display = 'none';
    if (dropdown) dropdown.innerHTML = '';

    tbody.appendChild(clone);
    bindPurchaseRow(clone, form);
    calculatePurchaseFormTotals(form);
}

function initializePurchasePaymentBehavior(form) {
    const statusSelect = form.querySelector('[data-payment-status-select]');
    const paidAmountWrapper = form.querySelector('[data-paid-amount-container]');
    const paidAmountInput = form.querySelector('[data-purchase-paid-amount]');

    if (!statusSelect) {
        return;
    }

    const togglePaymentPresentation = () => {
        const isDeferred = statusSelect.value === 'pending';
        const isPaid = statusSelect.value === 'paid';
        paidAmountWrapper?.classList.toggle('d-none', isDeferred);

        const currentTotal = purchaseNumericValue(form.querySelector('[data-purchase-total]')?.textContent);

        if (paidAmountInput) {
            paidAmountInput.disabled = isDeferred || isPaid;

            if (isDeferred) {
                paidAmountInput.value = '0';
            } else if (isPaid) {
                paidAmountInput.value = currentTotal.toFixed(2);
            }
        }

        calculatePurchaseFormTotals(form);
    };

    paidAmountInput?.addEventListener('input', () => calculatePurchaseFormTotals(form));
    statusSelect.addEventListener('change', togglePaymentPresentation);
    togglePaymentPresentation();
}

document.querySelectorAll('[data-purchase-form]').forEach((form) => {
    form.querySelectorAll('[data-purchase-item-row]').forEach((row) => bindPurchaseRow(row, form));
    form.querySelector('[data-add-purchase-item]')?.addEventListener('click', () => addPurchaseRow(form));
    initializePurchasePaymentBehavior(form);
    calculatePurchaseFormTotals(form);
});

@if ($errors->any())
@php
    $purchaseErrorModalId = $activePurchaseModal === 'create'
        ? 'addPurchaseModal'
        : (str_starts_with((string) $activePurchaseModal, 'edit-')
            ? 'editPurchaseModal' . substr((string) $activePurchaseModal, 5)
            : (str_starts_with((string) $activePurchaseModal, 'payment-')
                ? 'purchasePaymentModal' . substr((string) $activePurchaseModal, 8)
                : 'addPurchaseModal'));
@endphp
document.addEventListener('DOMContentLoaded', () => {
    const modalId = @json($purchaseErrorModalId);
    const modalElement = document.getElementById(modalId);

    if (modalElement && window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
@endif
</script>
@endpush
