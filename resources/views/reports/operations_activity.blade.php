@extends('layouts.app')

@section('title', 'تقرير الحركة التشغيلية')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div>
            <h2 class="page-title"><i class="fas fa-arrow-right-arrow-left"></i> تقرير الحركة التشغيلية</h2>
            <p class="text-muted mt-2 mb-0">متابعة الدفعات وحركات المخزون يومياً أو حسب المرجع من شاشة جاهزة واحدة.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('reports') }}" class="btn btn-outline-primary">
                <i class="fas fa-chart-bar ms-1"></i> مركز التقارير
            </a>
            <a href="{{ route('purchases') }}" class="btn btn-outline-secondary">
                <i class="fas fa-shopping-cart ms-1"></i> المشتريات
            </a>
        </div>
    </div>

    <div class="list-card mb-4">
        <form class="row g-3" method="GET" action="{{ route('reports.operations_activity') }}">
            <div class="col-md-3">
                <label class="form-label">طريقة التجميع</label>
                <select name="group_by" class="form-select">
                    <option value="day" {{ $groupBy === 'day' ? 'selected' : '' }}>يومي</option>
                    <option value="reference" {{ $groupBy === 'reference' ? 'selected' : '' }}>حسب المرجع</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">من تاريخ</label>
                <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">إلى تاريخ</label>
                <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">مرجع محدد</label>
                <input type="text" name="reference" class="form-control" value="{{ $reference }}" placeholder="PUR-PMT أو رقم مستند">
            </div>
            <div class="col-md-3">
                <label class="form-label">ترتيب التاريخ</label>
                <select name="sort_direction" class="form-select">
                    <option value="desc" {{ $sortDirection === 'desc' ? 'selected' : '' }}>الأحدث أولاً</option>
                    <option value="asc" {{ $sortDirection === 'asc' ? 'selected' : '' }}>الأقدم أولاً</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">تحديث التقرير</button>
                <a href="{{ route('reports.operations_activity') }}" class="btn btn-secondary">إعادة الضبط</a>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card h-100">
                <div class="stat-icon green"><i class="fas fa-money-bill-trend-up"></i></div>
                <div class="stat-value">{{ number_format((float) $paymentSummary['incoming'], 2) }}</div>
                <div class="stat-label">مقبوضات ({{ $company->currency }})</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card h-100">
                <div class="stat-icon red"><i class="fas fa-money-bill-transfer"></i></div>
                <div class="stat-value">{{ number_format((float) $paymentSummary['outgoing'], 2) }}</div>
                <div class="stat-label">مدفوعات ({{ $company->currency }})</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card h-100">
                <div class="stat-icon blue"><i class="fas fa-boxes-stacked"></i></div>
                <div class="stat-value">{{ number_format((float) $movementSummary['incoming_quantity'], 2) }}</div>
                <div class="stat-label">وارد مخزون</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card h-100">
                <div class="stat-icon orange"><i class="fas fa-truck-ramp-box"></i></div>
                <div class="stat-value">{{ number_format((float) $movementSummary['outgoing_quantity'], 2) }}</div>
                <div class="stat-label">منصرف مخزون</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="recent-activity h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="mb-1">ملخص الدفعات</h5>
                        <p class="text-muted mb-0">مجمّع {{ $groupBy === 'reference' ? 'حسب المرجع' : 'حسب اليوم' }}.</p>
                    </div>
                    <span class="badge text-bg-light">{{ $paymentSummary['count'] }} حركة</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>{{ $groupBy === 'reference' ? 'المرجع' : 'اليوم' }}</th>
                                <th>عدد السجلات</th>
                                <th>المقبوض</th>
                                <th>المدفوع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paymentGroups as $group)
                                <tr>
                                    <td>{{ $group['label'] }}</td>
                                    <td>{{ $group['count'] }}</td>
                                    <td>{{ number_format((float) $group['in_total'], 2) }} {{ $company->currency }}</td>
                                    <td>{{ number_format((float) $group['out_total'], 2) }} {{ $company->currency }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">لا توجد دفعات ضمن الفلاتر الحالية.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="recent-activity h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="mb-1">ملخص المخزون</h5>
                        <p class="text-muted mb-0">تلخيص الحركات الواردة والمنصرفة لنفس التجميع.</p>
                    </div>
                    <span class="badge text-bg-light">{{ $movementSummary['count'] }} حركة</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>{{ $groupBy === 'reference' ? 'المرجع' : 'اليوم' }}</th>
                                <th>عدد السجلات</th>
                                <th>الوارد</th>
                                <th>المنصرف</th>
                                <th>التكلفة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($movementGroups as $group)
                                <tr>
                                    <td>{{ $group['label'] }}</td>
                                    <td>{{ $group['count'] }}</td>
                                    <td>{{ number_format((float) $group['incoming_quantity'], 2) }}</td>
                                    <td>{{ number_format((float) $group['outgoing_quantity'], 2) }}</td>
                                    <td>{{ number_format((float) $group['inventory_cost'], 2) }} {{ $company->currency }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">لا توجد حركات مخزون ضمن الفلاتر الحالية.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="recent-activity h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="mb-1">تفاصيل الدفعات</h5>
                        <p class="text-muted mb-0">جميع السجلات المالية التشغيلية المطابقة للفلاتر.</p>
                    </div>
                    <span class="badge text-bg-light">{{ $payments->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>التصنيف</th>
                                <th>المرجع</th>
                                <th>الطرف</th>
                                <th>المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr>
                                    <td>{{ optional($payment->payment_date)->format('Y-m-d') ?: '-' }}</td>
                                    <td>{{ $payment->payment_category }}</td>
                                    <td>{{ $payment->reference ?: '-' }}</td>
                                    <td>{{ $payment->supplier?->name ?? $payment->invoice?->customer?->name ?? '-' }}</td>
                                    <td>{{ number_format((float) $payment->amount, 2) }} {{ $company->currency }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">لا توجد دفعات لعرضها.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="recent-activity h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h5 class="mb-1">تفاصيل حركات المخزون</h5>
                        <p class="text-muted mb-0">مرتبطة بالمشتريات والمبيعات المولدة لحركة المخزون.</p>
                    </div>
                    <span class="badge text-bg-light">{{ $inventoryMovements->count() }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>المرجع</th>
                                <th>المنتج</th>
                                <th>الاتجاه</th>
                                <th>الكمية</th>
                                <th>التكلفة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($inventoryMovements as $movement)
                                <tr>
                                    <td>{{ optional($movement->movement_date)->format('Y-m-d') ?: '-' }}</td>
                                    <td>{{ $movement->reference_number ?: '-' }}</td>
                                    <td>{{ $movement->product?->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $movement->direction === 'in' ? 'success' : 'warning' }}">
                                            {{ $movement->direction === 'in' ? 'وارد' : 'منصرف' }}
                                        </span>
                                    </td>
                                    <td>{{ number_format((float) $movement->quantity, 2) }}</td>
                                    <td>{{ number_format((float) $movement->total_cost, 2) }} {{ $company->currency }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">لا توجد حركات مخزون لعرضها.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
