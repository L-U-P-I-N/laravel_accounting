@extends('layouts.app')

@section('title', 'حركة المنتجات')

@push('styles')
<style>
    .movements-shell {
        --move-surface: #ffffff;
        --move-surface-soft: #f7fafc;
        --move-border: #dce5ee;
        --move-text: #243b53;
        --move-muted: #74859a;
        --move-primary: #2563eb;
        --move-success: #10b981;
        --move-warning: #f59e0b;
        --move-danger: #ef4444;
        --move-shadow: 0 10px 28px rgba(31, 57, 88, 0.08);
    }

    .movements-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .movements-title {
        margin: 0;
        color: var(--move-text);
        font-size: 1.5rem;
        font-weight: 800;
    }

    .movements-subtitle {
        margin: 0.15rem 0 0;
        color: var(--move-muted);
        font-size: 0.88rem;
    }

    .stat-card {
        background: var(--move-surface);
        border: 1px solid var(--move-border);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--move-shadow);
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

    .stat-icon.blue { background: #dbeafe; color: var(--move-primary); }
    .stat-icon.green { background: #d1fae5; color: var(--move-success); }
    .stat-icon.orange { background: #fef3c7; color: var(--move-warning); }
    .stat-icon.red { background: #fee2e2; color: var(--move-danger); }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--move-text);
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--move-muted);
    }

    .movements-table th {
        background: var(--move-surface-soft);
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 1rem;
        border-bottom: 2px solid var(--move-border);
        white-space: nowrap;
    }

    .movements-table td {
        padding: 1rem;
        vertical-align: middle;
    }

    .movements-table tbody tr:hover {
        background: var(--move-surface-soft);
    }

    .movement-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .movement-badge.in {
        background: #d1fae5;
        color: var(--move-success);
    }

    .movement-badge.out {
        background: #fee2e2;
        color: var(--move-danger);
    }

    .movement-badge.adjustment {
        background: #fef3c7;
        color: var(--move-warning);
    }

    .movement-badge.transfer {
        background: #dbeafe;
        color: var(--move-primary);
    }

    .product-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .product-image {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        object-fit: cover;
        background: var(--move-surface-soft);
    }

    .product-details h6 {
        margin: 0;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .product-details small {
        color: var(--move-muted);
        font-size: 0.75rem;
    }

    .filters-section {
        background: var(--move-surface);
        border: 1px solid var(--move-border);
        border-radius: 12px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .timeline-item {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        border-bottom: 1px solid var(--move-border);
    }

    .timeline-item:last-child {
        border-bottom: none;
    }

    .timeline-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .timeline-icon.in { background: #d1fae5; color: var(--move-success); }
    .timeline-icon.out { background: #fee2e2; color: var(--move-danger); }
    .timeline-icon.adjust { background: #fef3c7; color: var(--move-warning); }

    .timeline-content {
        flex-grow: 1;
    }

    .timeline-meta {
        color: var(--move-muted);
        font-size: 0.8rem;
    }
</style>
@endpush

@section('content')
<div class="container-fluid movements-shell py-4">
    <div class="movements-header">
        <div>
            <h1 class="movements-title">حركة المنتجات</h1>
            <p class="movements-subtitle">تعرف على حركة منتجات مخزونك. يتم إدراج هذه الحركات في تقارير المخزون</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('reports.product-movements.print') }}" class="btn btn-outline-primary" target="_blank">
                <i class="bi bi-printer"></i> طباعة
            </a>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-right"></i> العودة للتقارير
            </a>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="bi bi-arrow-down-left"></i>
                </div>
                <div class="stat-value">{{ number_format($totalIn) }}</div>
                <div class="stat-label">إجمالي الوارد</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="bi bi-arrow-up-right"></i>
                </div>
                <div class="stat-value">{{ number_format($totalOut) }}</div>
                <div class="stat-label">إجمالي المنصرف</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="bi bi-sliders"></i>
                </div>
                <div class="stat-value">{{ number_format($totalAdjustments) }}</div>
                <div class="stat-label">تعديلات المخزون</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="stat-value">{{ number_format($currentStock) }}</div>
                <div class="stat-label">الرصيد الحالي</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filters-section">
        <form method="GET" action="{{ route('reports.product-movements') }}" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">المنتج</label>
                <select name="product_id" class="form-select">
                    <option value="">جميع المنتجات</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                            {{ $product->name }} ({{ $product->sku }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">نوع الحركة</label>
                <select name="type" class="form-select">
                    <option value="">الكل</option>
                    <option value="in" {{ request('type') == 'in' ? 'selected' : '' }}>وارد</option>
                    <option value="out" {{ request('type') == 'out' ? 'selected' : '' }}>منصرف</option>
                    <option value="adjustment" {{ request('type') == 'adjustment' ? 'selected' : '' }}>تعديل</option>
                    <option value="transfer" {{ request('type') == 'transfer' ? 'selected' : '' }}>نقل</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">من تاريخ</label>
                <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">إلى تاريخ</label>
                <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">المستودع</label>
                <select name="warehouse" class="form-select">
                    <option value="">الكل</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" {{ request('warehouse') == $warehouse->id ? 'selected' : '' }}>
                            {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>
    </div>

    {{-- Movements Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table movements-table mb-0">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>المنتج</th>
                            <th>نوع الحركة</th>
                            <th>الكمية</th>
                            <th>المستودع</th>
                            <th>المرجع</th>
                            <th>الموظف</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $movement)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $movement->created_at->format('Y-m-d') }}</div>
                                    <small class="text-muted">{{ $movement->created_at->format('H:i') }}</small>
                                </td>
                                <td>
                                    <div class="product-info">
                                        <img src="{{ $movement->product?->image ?? asset('images/default-product.png') }}" class="product-image" alt="">
                                        <div class="product-details">
                                            <h6>{{ $movement->product?->name ?? 'منتج محذوف' }}</h6>
                                            <small>{{ $movement->product?->sku ?? '-' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @switch($movement->type)
                                        @case('in')
                                            <span class="movement-badge in">
                                                <i class="bi bi-arrow-down-left"></i> وارد
                                            </span>
                                            @break
                                        @case('out')
                                            <span class="movement-badge out">
                                                <i class="bi bi-arrow-up-right"></i> منصرف
                                            </span>
                                            @break
                                        @case('adjustment')
                                            <span class="movement-badge adjustment">
                                                <i class="bi bi-sliders"></i> تعديل
                                            </span>
                                            @break
                                        @case('transfer')
                                            <span class="movement-badge transfer">
                                                <i class="bi bi-arrow-left-right"></i> نقل
                                            </span>
                                            @break
                                    @endswitch
                                </td>
                                <td>
                                    <span class="fw-bold {{ $movement->type == 'in' ? 'text-success' : ($movement->type == 'out' ? 'text-danger' : 'text-warning') }}">
                                        {{ $movement->type == 'in' ? '+' : ($movement->type == 'out' ? '-' : '') }}{{ number_format($movement->quantity) }}
                                    </span>
                                </td>
                                <td>{{ $movement->warehouse?->name ?? '-' }}</td>
                                <td>
                                    @if($movement->reference_type == 'purchase')
                                        <a href="{{ route('purchases.show', $movement->reference_id) }}" class="text-decoration-none">
                                            <i class="bi bi-cart"></i> فاتورة شراء #{{ $movement->reference_id }}
                                        </a>
                                    @elseif($movement->reference_type == 'invoice')
                                        <a href="{{ route('invoices.show', $movement->reference_id) }}" class="text-decoration-none">
                                            <i class="bi bi-receipt"></i> فاتورة مبيعات #{{ $movement->reference_id }}
                                        </a>
                                    @else
                                        <span class="text-muted">{{ $movement->reference_type ?? '-' }}</span>
                                    @endif
                                </td>
                                <td>{{ $movement->user?->name ?? '-' }}</td>
                                <td>{{ $movement->notes ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bi bi-inbox fs-1 text-muted"></i>
                                    <p class="text-muted mt-2">لا توجد حركات مخزون للفترة المحددة</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($movements->hasPages())
            <div class="card-footer">
                {{ $movements->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
