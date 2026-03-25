@extends('layouts.app')

@section('title', 'لوحة التحكم - ' . $company->name)

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</h2>
        <p class="text-muted mt-2 mb-0">مرحباً {{ auth()->user()->first_name }}، آخر تحديث: الآن</p>
    </div>
    <div class="page-header-meta">
        <span class="badge bg-success py-2 px-3 fs-6">
            <i class="fas fa-check-circle ms-2"></i>
            {{ ucfirst($company->subscription_plan) }}
        </span>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-arrow-up"></i></div>
            <div class="stat-value">{{ number_format($stats['total_revenue_month'], 2) }} {{ $company->currency }}</div>
            <div class="stat-label">إجمالي الإيرادات هذا الشهر</div>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-arrow-down"></i></div>
            <div class="stat-value">{{ number_format($stats['total_expenses_month'], 2) }} {{ $company->currency }}</div>
            <div class="stat-label">إجمالي المصروفات هذا الشهر</div>
        </div>
    </div>
    <div class="col-md-3 mb-3 mb-md-0">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-users"></i></div>
            <div class="stat-value">{{ $stats['total_customers'] }}</div>
            <div class="stat-label">إجمالي العملاء</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-value">{{ $stats['total_invoices_month'] }}</div>
            <div class="stat-label">الفواتير هذا الشهر</div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8 mb-4 mb-md-0">
        <div class="chart-container">
            <h5 class="mb-3"><i class="fas fa-chart-line ms-2 text-primary"></i> نظرة عامة على 6 أشهر</h5>
            <div class="chart-wrapper">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="chart-container">
            <h5 class="mb-3"><i class="fas fa-info-circle ms-2 text-info"></i> معلومات سريعة</h5>
            <div class="mb-3">
                <small class="text-muted">الذمم المدينة</small>
                <h4 class="text-danger">{{ number_format($stats['outstanding_receivables'], 2) }} {{ $company->currency }}</h4>
            </div>
            <div class="mb-3">
                <small class="text-muted">الذمم الدائنة</small>
                <h4 class="text-warning">{{ number_format($stats['outstanding_payables'], 2) }} {{ $company->currency }}</h4>
            </div>
            <div class="mb-3">
                <small class="text-muted">الفواتير المتأخرة</small>
                <h4 class="text-danger">{{ $stats['overdue_invoices'] }}</h4>
            </div>
            <div>
                <small class="text-muted">الموظفين النشطين</small>
                <h4 class="text-success">{{ $stats['total_employees'] }}</h4>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4 mb-md-0">
        <div class="recent-activity">
            <h5 class="mb-3"><i class="fas fa-file-invoice ms-2 text-primary"></i> آخر الفواتير</h5>
            @forelse ($recentInvoices as $invoice)
                <div class="activity-item">
                    <div class="d-flex justify-content-between align-items-center activity-split">
                        <div>
                            <strong>{{ $invoice->invoice_number }}</strong>
                            <br>
                            <small class="text-muted">{{ $invoice->customer?->name ?? 'عميل غير محدد' }} • {{ optional($invoice->invoice_date)->format('Y-m-d') }}</small>
                        </div>
                        <div class="text-start">
                            <strong>{{ number_format($invoice->total, 2) }} {{ $invoice->currency ?: $company->currency }}</strong>
                            <br>
                            <span class="badge bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'partial' ? 'warning' : 'secondary') }}">{{ $invoice->status }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5 text-muted">لا توجد فواتير حديثة</div>
            @endforelse
        </div>
    </div>
    <div class="col-md-6">
        <div class="recent-activity">
            <h5 class="mb-3"><i class="fas fa-shopping-cart ms-2 text-success"></i> آخر المشتريات</h5>
            @forelse ($recentPurchases as $purchase)
                <div class="activity-item">
                    <div class="d-flex justify-content-between align-items-center activity-split">
                        <div>
                            <strong>{{ $purchase->purchase_number }}</strong>
                            <br>
                            <small class="text-muted">{{ $purchase->supplier?->name ?? 'مورد غير محدد' }} • {{ optional($purchase->purchase_date)->format('Y-m-d') }}</small>
                        </div>
                        <div class="text-start">
                            <strong>{{ number_format($purchase->total, 2) }} {{ $purchase->currency ?: $company->currency }}</strong>
                            <br>
                            <span class="badge bg-{{ $purchase->status === 'paid' ? 'success' : ($purchase->status === 'partial' ? 'warning' : 'secondary') }}">{{ $purchase->status }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5 text-muted">لا توجد مشتريات حديثة</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const ctx = document.getElementById('revenueChart').getContext('2d');
const chartData = @json($chartData, JSON_UNESCAPED_UNICODE);

new Chart(ctx, {
    type: 'line',
    data: {
        labels: chartData.labels,
        datasets: [{
            label: 'الإيرادات',
            data: chartData.revenue,
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4
        }, {
            label: 'المصروفات',
            data: chartData.expenses,
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    font: {
                        family: 'Tajawal'
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback(value) {
                        return value.toLocaleString() + ' {{ $company->currency }}';
                    }
                }
            }
        }
    }
});
</script>
@endpush
