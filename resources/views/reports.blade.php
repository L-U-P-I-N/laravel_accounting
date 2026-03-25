@extends('layouts.app')

@section('title', 'التقارير')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <h2 class="page-title"><i class="fas fa-chart-line"></i> التقارير المالية</h2>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="list-card">
                <div class="card-header bg-transparent border-0 px-0 pt-0"><h5 class="mb-0">إعدادات التقرير</h5></div>
                <div class="card-body px-0 pb-0">
                    <form class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">نوع التقرير</label>
                            <select class="form-select"><option>قائمة الدخل</option><option>الميزانية العمومية</option><option>تدفق النقدية</option><option>الذمم المدينة</option><option>الذمم الدائنة</option></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">الفترة</label>
                            <select class="form-select"><option>شهري</option><option>ربع سنوي</option><option>سنوي</option><option>مخصص</option></select>
                        </div>
                        <div class="col-md-2"><label class="form-label">من تاريخ</label><input type="date" class="form-control"></div>
                        <div class="col-md-2"><label class="form-label">إلى تاريخ</label><input type="date" class="form-control"></div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="button" class="btn btn-primary"><i class="fas fa-file-alt ms-2"></i>إنشاء التقرير</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-arrow-up"></i></div><div class="stat-value">{{ number_format((float) $stats['total_revenue'], 2) }}</div><div class="stat-label">إجمالي الإيرادات ({{ $company->currency }})</div></div></div>
        <div class="col-md-3 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon red"><i class="fas fa-arrow-down"></i></div><div class="stat-value">{{ number_format((float) $stats['total_expenses'], 2) }}</div><div class="stat-label">إجمالي المصروفات ({{ $company->currency }})</div></div></div>
        <div class="col-md-3 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon green"><i class="fas fa-chart-pie"></i></div><div class="stat-value">{{ number_format((float) $stats['net_profit'], 2) }}</div><div class="stat-label">صافي الربح ({{ $company->currency }})</div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-icon orange"><i class="fas fa-money-bill-wave"></i></div><div class="stat-value">{{ number_format((float) $stats['cash_flow'], 2) }}</div><div class="stat-label">التدفق النقدي ({{ $company->currency }})</div></div></div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4 mb-lg-0">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">عرض التقرير</h5></div>
                <div class="card-body">
                    <canvas id="reportChart" width="400" height="200"></canvas>
                    <div class="table-responsive mt-4">
                        <table class="table table-striped">
                            <thead>
                                <tr><th>البند</th><th>القيمة</th><th>النسبة المئوية</th></tr>
                            </thead>
                            <tbody>
                                @php $base = max((float) $stats['total_revenue'], 1); @endphp
                                @foreach ($reportRows as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td>{{ number_format((float) $row['value'], 2) }} {{ $company->currency }}</td>
                                        <td>{{ number_format((((float) $row['value']) / $base) * 100, 1) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">تحليلات سريعة</h5></div>
                <div class="card-body">
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
    const labels = @json(array_column($reportRows, 'label'), JSON_UNESCAPED_UNICODE);
    const values = @json(array_map(fn ($row) => (float) $row['value'], $reportRows), JSON_UNESCAPED_UNICODE);
    new Chart(reportCtx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'قيم التقرير',
                data: values,
                backgroundColor: ['#2563eb', '#dc2626', '#059669', '#d97706'],
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
</script>
@endpush
