@extends('layouts.app')

@section('title', 'تقرير أداء المنتج')

@push('styles')
<style>
    .performance-shell {
        --perf-surface: #ffffff;
        --perf-surface-soft: #f7fafc;
        --perf-border: #dce5ee;
        --perf-text: #243b53;
        --perf-muted: #74859a;
        --perf-primary: #2563eb;
        --perf-primary-dark: #1d4ed8;
        --perf-success: #10b981;
        --perf-warning: #f59e0b;
        --perf-danger: #ef4444;
        --perf-shadow: 0 10px 28px rgba(31, 57, 88, 0.08);
    }

    .performance-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .performance-title {
        margin: 0;
        color: var(--perf-text);
        font-size: 1.5rem;
        font-weight: 800;
    }

    .performance-subtitle {
        margin: 0.15rem 0 0;
        color: var(--perf-muted);
        font-size: 0.88rem;
    }

    .stat-card {
        background: var(--perf-surface);
        border: 1px solid var(--perf-border);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--perf-shadow);
        transition: transform 0.2s ease;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .stat-icon.blue { background: #dbeafe; color: var(--perf-primary); }
    .stat-icon.green { background: #d1fae5; color: var(--perf-success); }
    .stat-icon.orange { background: #ffedd5; color: #ea580c; }
    .stat-icon.purple { background: #f3e8ff; color: #9333ea; }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--perf-text);
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--perf-muted);
    }

    .performance-table th {
        background: var(--perf-surface-soft);
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 1rem;
        border-bottom: 2px solid var(--perf-border);
        white-space: nowrap;
    }

    .performance-table td {
        padding: 1rem;
        vertical-align: middle;
    }

    .performance-table tbody tr:hover {
        background: var(--perf-surface-soft);
    }

    .product-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .product-image {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        object-fit: cover;
        background: var(--perf-surface-soft);
    }

    .product-details h6 {
        margin: 0;
        font-weight: 600;
        color: var(--perf-text);
    }

    .product-details small {
        color: var(--perf-muted);
    }

    .trend-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .trend-badge.up {
        background: #d1fae5;
        color: var(--perf-success);
    }

    .trend-badge.down {
        background: #fee2e2;
        color: var(--perf-danger);
    }

    .profit-positive {
        color: var(--perf-success);
        font-weight: 600;
    }

    .profit-negative {
        color: var(--perf-danger);
        font-weight: 600;
    }

    .top-product {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: var(--perf-surface);
        border: 1px solid var(--perf-border);
        border-radius: 12px;
        margin-bottom: 0.75rem;
    }

    .top-product-rank {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.875rem;
    }

    .top-product-rank.gold { background: #fef3c7; color: #d97706; }
    .top-product-rank.silver { background: #f3f4f6; color: #6b7280; }
    .top-product-rank.bronze { background: #ffedd5; color: #c2410c; }
    .top-product-rank.other { background: var(--perf-surface-soft); color: var(--perf-muted); }
</style>
@endpush

@section('content')
<div class="container-fluid performance-shell py-4">
    <div class="performance-header">
        <div>
            <h1 class="performance-title">تقرير أداء المنتج</h1>
            <p class="performance-subtitle">عرض مبيعات كل منتج في النظام على حدة حتى تتمكن من معرفة المنتجات الأفضل أداءاً والأكثر تحقيقاً للأرباح</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('reports.product-performance.print') }}" class="btn btn-outline-primary" target="_blank">
                <i class="bi bi-printer"></i> طباعة
            </a>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-right"></i> العودة للتقارير
            </a>
        </div>
    </div>

    {{-- Summary Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="stat-value">{{ number_format($totalSales, 2) }}</div>
                <div class="stat-label">إجمالي المبيعات ({{ $company->currency }})</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="stat-value">{{ number_format($totalProfit, 2) }}</div>
                <div class="stat-label">إجمالي الأرباح ({{ $company->currency }})</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="bi bi-cart-check"></i>
                </div>
                <div class="stat-value">{{ number_format($totalQuantity) }}</div>
                <div class="stat-label">الكمية المباعة</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="bi bi-percent"></i>
                </div>
                <div class="stat-value">{{ number_format($avgProfitMargin, 1) }}%</div>
                <div class="stat-label">متوسط هامش الربح</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Top Products --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="bi bi-trophy text-warning me-2"></i>أفضل 5 منتجات</h5>
                </div>
                <div class="card-body">
                    @foreach($topProducts as $index => $product)
                        <div class="top-product">
                            <div class="top-product-rank {{ $index == 0 ? 'gold' : ($index == 1 ? 'silver' : ($index == 2 ? 'bronze' : 'other')) }}">
                                {{ $index + 1 }}
                            </div>
                            <img src="{{ $product['image'] ?? asset('images/default-product.png') }}" class="product-image" alt="">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $product['name'] }}</h6>
                                <small class="text-muted">{{ number_format($product['sales'], 2) }} {{ $company->currency }}</small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success">{{ number_format($product['profit'], 2) }}</div>
                                <small class="text-muted">ربح</small>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Performance Table --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white py-3">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0"><i class="bi bi-table text-primary me-2"></i>تفاصيل الأداء</h5>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" class="d-flex gap-2">
                                <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="all" {{ request('period') == 'all' ? 'selected' : '' }}>كل الفترات</option>
                                    <option value="month" {{ request('period') == 'month' ? 'selected' : '' }}>هذا الشهر</option>
                                    <option value="quarter" {{ request('period') == 'quarter' ? 'selected' : '' }}>هذا الربع</option>
                                    <option value="year" {{ request('period') == 'year' ? 'selected' : '' }}>هذا العام</option>
                                </select>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table performance-table mb-0">
                            <thead>
                                <tr>
                                    <th>المنتج</th>
                                    <th>الكمية</th>
                                    <th>المبيعات</th>
                                    <th>التكلفة</th>
                                    <th>الربح</th>
                                    <th>هامش الربح</th>
                                    <th>الاتجاه</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($productPerformance as $item)
                                    <tr>
                                        <td>
                                            <div class="product-info">
                                                <img src="{{ $item['image'] ?? asset('images/default-product.png') }}" class="product-image" alt="">
                                                <div class="product-details">
                                                    <h6>{{ $item['name'] }}</h6>
                                                    <small>{{ $item['sku'] }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ number_format($item['quantity']) }}</td>
                                        <td>{{ number_format($item['sales'], 2) }}</td>
                                        <td>{{ number_format($item['cost'], 2) }}</td>
                                        <td class="{{ $item['profit'] >= 0 ? 'profit-positive' : 'profit-negative' }}">
                                            {{ number_format($item['profit'], 2) }}
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar {{ $item['profit_margin'] >= 20 ? 'bg-success' : ($item['profit_margin'] >= 10 ? 'bg-warning' : 'bg-danger') }}" 
                                                     style="width: {{ min(abs($item['profit_margin']), 100) }}%;">
                                                </div>
                                            </div>
                                            <small class="text-muted">{{ number_format($item['profit_margin'], 1) }}%</small>
                                        </td>
                                        <td>
                                            @if($item['trend'] > 0)
                                                <span class="trend-badge up">
                                                    <i class="bi bi-arrow-up-right"></i> +{{ number_format($item['trend'], 1) }}%
                                                </span>
                                            @elseif($item['trend'] < 0)
                                                <span class="trend-badge down">
                                                    <i class="bi bi-arrow-down-right"></i> {{ number_format($item['trend'], 1) }}%
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <i class="bi bi-inbox fs-1 text-muted"></i>
                                            <p class="text-muted mt-2">لا توجد بيانات مبيعات للفترة المحددة</p>
                                        </td>
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
@endsection
