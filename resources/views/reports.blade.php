@extends('layouts.app')

@section('title', 'التقارير')

@push('styles')
<style>
    .report-toolbar,
    .report-card,
    .report-sidebar {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
    }

    .report-toolbar {
        padding: 1.5rem;
    }

    .report-card,
    .report-sidebar {
        padding: 1.25rem;
        height: 100%;
    }

    .report-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.85rem;
        border-radius: 999px;
        background: #eef2ff;
        color: #3730a3;
        font-size: 0.9rem;
        margin: 0 0 0.5rem 0.5rem;
    }

    .report-kpi {
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 1rem;
        height: 100%;
    }

    .report-kpi .value {
        font-size: 1.5rem;
        font-weight: 800;
    }

    .report-meta {
        color: #6b7280;
        font-size: 0.92rem;
    }

    .report-table tbody tr td {
        vertical-align: middle;
    }

    .target-field {
        display: none;
    }

    .target-field.active {
        display: block;
    }

    @media print {
        .sidebar,
        .top-navbar,
        .page-header,
        .report-actions,
        .report-toolbar,
        .sidebar-overlay {
            display: none !important;
        }

        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }

        .container-fluid {
            width: 100% !important;
            max-width: 100% !important;
            padding: 0 !important;
        }

        .report-card,
        .report-sidebar,
        .report-kpi {
            box-shadow: none !important;
            border: 1px solid #d1d5db !important;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <h2 class="page-title"><i class="fas fa-chart-line"></i> التقارير المالية</h2>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="report-toolbar">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <h5 class="mb-2">إعدادات التقرير</h5>
                        <div class="report-meta">اختر نوع التقرير والفترة، ثم حدّد إن كنت تريد التقرير العام أو تقريراً لحساب أو منتج أو مصروف أو عميل أو مورد محدد.</div>
                    </div>
                    <div class="report-actions d-flex gap-2 flex-wrap">
                        <a href="{{ route('reports') }}" class="btn btn-outline-secondary">إعادة تعيين</a>
                        <a href="{{ route('reports', array_merge(request()->query(), ['print' => 1])) }}" target="_blank" class="btn btn-outline-primary">
                            <i class="fas fa-print ms-2"></i>عرض الطباعة / PDF
                        </a>
                    </div>
                </div>

                <form method="GET" action="{{ route('reports') }}" class="row g-3" id="reportFiltersForm">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">نوع التقرير</label>
                        <select class="form-select" name="report_type" id="report_type" required>
                            @foreach ($reportTypes as $key => $type)
                                <option value="{{ $key }}" {{ $selectedReportType === $key ? 'selected' : '' }}>{{ $type['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">الفترة</label>
                        <select class="form-select" name="period" id="period" required>
                            @foreach ($periodOptions as $key => $label)
                                <option value="{{ $key }}" {{ $selectedPeriod === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 custom-period-field {{ $selectedPeriod === 'custom' ? '' : 'd-none' }}">
                        <label class="form-label">من تاريخ</label>
                        <input type="date" class="form-control" name="date_from" value="{{ $dateFrom->format('Y-m-d') }}">
                    </div>
                    <div class="col-lg-2 col-md-6 custom-period-field {{ $selectedPeriod === 'custom' ? '' : 'd-none' }}">
                        <label class="form-label">إلى تاريخ</label>
                        <input type="date" class="form-control" name="date_to" value="{{ $dateTo->format('Y-m-d') }}">
                    </div>
                    <div class="col-lg-2 col-md-6 target-field" data-target="account">
                        <label class="form-label">الحساب</label>
                        <select class="form-select" name="account_id">
                            <option value="">كل الحسابات</option>
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" {{ $selectedAccountId === $account->id ? 'selected' : '' }}>{{ $account->code }} - {{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 target-field" data-target="product">
                        <label class="form-label">المنتج</label>
                        <select class="form-select" name="product_id">
                            <option value="">كل المنتجات</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" {{ $selectedProductId === $product->id ? 'selected' : '' }}>{{ $product->name }}{{ $product->code ? ' (' . $product->code . ')' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 target-field" data-target="expense">
                        <label class="form-label">المصروف</label>
                        <select class="form-select" name="expense_id">
                            <option value="">كل المصروفات</option>
                            @foreach ($expenses as $expense)
                                <option value="{{ $expense->id }}" {{ $selectedExpenseId === $expense->id ? 'selected' : '' }}>{{ $expense->name ?: ($expense->reference ?: 'مصروف #' . $expense->id) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 target-field" data-target="customer">
                        <label class="form-label">العميل</label>
                        <select class="form-select" name="customer_id">
                            <option value="">كل العملاء</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" {{ $selectedCustomerId === $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 target-field" data-target="supplier">
                        <label class="form-label">المورد</label>
                        <select class="form-select" name="supplier_id">
                            <option value="">كل الموردين</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ $selectedSupplierId === $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-file-alt ms-2"></i>إنشاء التقرير
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3 mb-md-0"><div class="report-kpi"><div class="report-meta mb-2">إجمالي الإيرادات</div><div class="value text-primary">{{ number_format((float) $stats['total_revenue'], 2) }}</div><div class="report-meta">{{ $company->currency }}</div></div></div>
        <div class="col-md-3 mb-3 mb-md-0"><div class="report-kpi"><div class="report-meta mb-2">إجمالي المصروفات</div><div class="value text-danger">{{ number_format((float) $stats['total_expenses'], 2) }}</div><div class="report-meta">{{ $company->currency }}</div></div></div>
        <div class="col-md-3 mb-3 mb-md-0"><div class="report-kpi"><div class="report-meta mb-2">صافي الربح</div><div class="value text-success">{{ number_format((float) $stats['net_profit'], 2) }}</div><div class="report-meta">{{ $company->currency }}</div></div></div>
        <div class="col-md-3"><div class="report-kpi"><div class="report-meta mb-2">الذمم المدينة الحالية</div><div class="value text-warning">{{ number_format((float) $stats['outstanding_receivables'], 2) }}</div><div class="report-meta">{{ $company->currency }}</div></div></div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4 mb-lg-0">
            <div class="report-card">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <h4 class="mb-2">{{ $report['title'] }}</h4>
                        <div class="report-meta mb-2">{{ $report['description'] }}</div>
                        <div class="report-meta">{{ $report['date_range_label'] }}</div>
                    </div>
                    <div class="text-start">
                        <span class="report-chip"><i class="fas fa-calendar-alt"></i>{{ $periodOptions[$selectedPeriod] }}</span>
                        @if (($reportTypes[$selectedReportType]['focus'] ?? null) === 'account' && $selectedAccountId)
                            <span class="report-chip"><i class="fas fa-sitemap"></i>{{ optional($accounts->firstWhere('id', $selectedAccountId))->name }}</span>
                        @endif
                        @if (($reportTypes[$selectedReportType]['focus'] ?? null) === 'product' && $selectedProductId)
                            <span class="report-chip"><i class="fas fa-box"></i>{{ optional($products->firstWhere('id', $selectedProductId))->name }}</span>
                        @endif
                        @if (($reportTypes[$selectedReportType]['focus'] ?? null) === 'expense' && $selectedExpenseId)
                            <span class="report-chip"><i class="fas fa-receipt"></i>{{ optional($expenses->firstWhere('id', $selectedExpenseId))->name ?: 'مصروف محدد' }}</span>
                        @endif
                        @if (($reportTypes[$selectedReportType]['focus'] ?? null) === 'customer' && $selectedCustomerId)
                            <span class="report-chip"><i class="fas fa-user"></i>{{ optional($customers->firstWhere('id', $selectedCustomerId))->name }}</span>
                        @endif
                        @if (($reportTypes[$selectedReportType]['focus'] ?? null) === 'supplier' && $selectedSupplierId)
                            <span class="report-chip"><i class="fas fa-truck"></i>{{ optional($suppliers->firstWhere('id', $selectedSupplierId))->name }}</span>
                        @endif
                        @if ($selectedReportType === 'tax_summary')
                            <span class="report-chip"><i class="fas fa-percent"></i>ملخص الضريبة</span>
                        @endif
                    </div>
                </div>

                @if ($reportRows->isNotEmpty())
                    <canvas id="reportChart" width="400" height="200"></canvas>
                    <div class="table-responsive mt-4">
                        <table class="table table-striped report-table">
                            <thead>
                                <tr><th>البند</th><th>التفاصيل</th><th>القيمة</th><th>النسبة المئوية</th></tr>
                            </thead>
                            <tbody>
                                @php
                                    $base = max((float) collect($reportRows)->pluck('value')->map(fn ($value) => abs((float) $value))->max(), 1);
                                @endphp
                                @foreach ($reportRows as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="text-muted">{{ $row['meta'] ?? '—' }}</td>
                                        <td>{{ number_format((float) $row['value'], 2) }} {{ $company->currency }}</td>
                                        <td>{{ number_format((abs((float) $row['value']) / $base) * 100, 1) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-light border mb-0">{{ $report['empty_message'] }}</div>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="report-sidebar">
                <h5 class="mb-3">ملخص سريع</h5>
                <div class="d-grid gap-3 mb-4">
                    @foreach ($report['highlights'] as $highlight)
                        <div class="report-kpi">
                            <div class="report-meta mb-1">{{ $highlight['label'] }}</div>
                            <div class="value">{{ number_format((float) $highlight['value'], 2) }}</div>
                            <div class="report-meta">{{ $company->currency }}</div>
                        </div>
                    @endforeach
                </div>

                <h6 class="mb-3">تحليلات عامة</h6>
                <div class="card-body p-0">
                    @php
                        $profitMargin = $stats['total_revenue'] > 0 ? max(min(($stats['net_profit'] / $stats['total_revenue']) * 100, 100), 0) : 0;
                        $expenseEfficiency = $stats['total_revenue'] > 0 ? max(min((1 - ($stats['total_expenses'] / $stats['total_revenue'])) * 100, 100), 0) : 0;
                    @endphp
                    <div class="mb-3">
                        <h6>هامش الربح</h6>
                        <div class="progress"><div class="progress-bar bg-success" style="width: {{ $profitMargin }}%">{{ number_format($profitMargin, 1) }}%</div></div>
                    </div>
                    <div class="mb-3">
                        <h6>نمو الإيرادات</h6>
                        <div class="progress"><div class="progress-bar bg-primary" style="width: 62%">62%</div></div>
                    </div>
                    <div class="mb-3">
                        <h6>كفاءة المصروفات</h6>
                        <div class="progress"><div class="progress-bar bg-warning" style="width: {{ $expenseEfficiency }}%">{{ number_format($expenseEfficiency, 1) }}%</div></div>
                    </div>
                    <hr>
                    <h6>أهم المقاييس</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><strong>متوسط حجم الطلب:</strong> <span class="float-start">{{ number_format((float) $stats['avg_order_value'], 2) }} {{ $company->currency }}</span></li>
                        <li class="mb-2"><strong>عدد العملاء:</strong> <span class="float-start">{{ $stats['total_customers'] }}</span></li>
                        <li class="mb-2"><strong>قيمة المخزون:</strong> <span class="float-start">{{ number_format((float) $stats['inventory_value'], 2) }} {{ $company->currency }}</span></li>
                        <li class="mb-2"><strong>الذمم المستحقة:</strong> <span class="float-start">{{ number_format((float) $stats['outstanding_receivables'], 2) }} {{ $company->currency }}</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const reportCtx = document.getElementById('reportChart');
if (reportCtx) {
    const labels = @json(($report['chart']['labels'] ?? collect())->values(), JSON_UNESCAPED_UNICODE);
    const values = @json(($report['chart']['values'] ?? collect())->values(), JSON_UNESCAPED_UNICODE);
    new Chart(reportCtx, {
        type: @json($report['chart']['type'] ?? 'bar'),
        data: {
            labels,
            datasets: [{
                label: 'قيم التقرير',
                data: values,
                backgroundColor: ['#2563eb', '#dc2626', '#059669', '#d97706', '#7c3aed', '#0891b2', '#65a30d', '#ea580c'],
                borderRadius: 10,
            }],
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } },
        },
    });
}

const reportTypeElement = document.getElementById('report_type');
const periodElement = document.getElementById('period');
const targetFields = document.querySelectorAll('.target-field');
const customPeriodFields = document.querySelectorAll('.custom-period-field');
const reportTypeFocusMap = @json(collect($reportTypes)->mapWithKeys(fn ($type, $key) => [$key => $type['focus']])->all());

function syncReportFilters() {
    const reportType = reportTypeElement ? reportTypeElement.value : 'income_statement';
    const requiredTarget = reportTypeFocusMap[reportType];

    targetFields.forEach((field) => {
        const isActive = field.dataset.target === requiredTarget;
        field.classList.toggle('active', isActive);
        field.querySelectorAll('select').forEach((select) => {
            select.disabled = !isActive;
        });
    });

    const isCustom = periodElement && periodElement.value === 'custom';
    customPeriodFields.forEach((field) => {
        field.classList.toggle('d-none', !isCustom);
        field.querySelectorAll('input').forEach((input) => {
            input.disabled = !isCustom;
        });
    });
}

reportTypeElement?.addEventListener('change', syncReportFilters);
periodElement?.addEventListener('change', syncReportFilters);
syncReportFilters();
</script>
@endpush
