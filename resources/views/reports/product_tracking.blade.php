@extends('layouts.app')

@section('title', 'استعلامات تتبع المنتج')

@push('styles')
<style>
    .tracking-shell {
        --tracking-surface: #ffffff;
        --tracking-surface-soft: #f7fafc;
        --tracking-border: #dce5ee;
        --tracking-text: #243b53;
        --tracking-muted: #74859a;
        --tracking-primary: #2563eb;
        --tracking-primary-dark: #1d4ed8;
        --tracking-primary-soft: #dbeafe;
        --tracking-success: #10b981;
        --tracking-warning: #f59e0b;
        --tracking-danger: #ef4444;
        --tracking-shadow: 0 10px 28px rgba(31, 57, 88, 0.08);
    }

    .tracking-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .tracking-title {
        margin: 0;
        color: var(--tracking-text);
        font-size: 1.5rem;
        font-weight: 800;
    }

    .tracking-subtitle {
        margin: 0.15rem 0 0;
        color: var(--tracking-muted);
        font-size: 0.88rem;
    }

    .tracking-card {
        background: var(--tracking-surface);
        border: 1px solid var(--tracking-border);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--tracking-shadow);
    }

    .tracking-filters {
        background: var(--tracking-surface-soft);
        border-radius: 12px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .product-status {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 999px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .product-status.active {
        background: #d1fae5;
        color: var(--tracking-success);
    }

    .product-status.inactive {
        background: #fee2e2;
        color: var(--tracking-danger);
    }

    .product-status.low-stock {
        background: #fef3c7;
        color: var(--tracking-warning);
    }

    .expiry-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .expiry-badge.safe {
        background: #d1fae5;
        color: var(--tracking-success);
    }

    .expiry-badge.warning {
        background: #fef3c7;
        color: var(--tracking-warning);
    }

    .expiry-badge.danger {
        background: #fee2e2;
        color: var(--tracking-danger);
    }

    .location-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.5rem 1rem;
        background: var(--tracking-primary-soft);
        color: var(--tracking-primary);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .tracking-table th {
        background: var(--tracking-surface-soft);
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 1rem;
        border-bottom: 2px solid var(--tracking-border);
    }

    .tracking-table td {
        padding: 1rem;
        vertical-align: middle;
    }

    .tracking-table tbody tr:hover {
        background: var(--tracking-surface-soft);
    }

    .product-name {
        font-weight: 600;
        color: var(--tracking-text);
    }

    .product-sku {
        font-size: 0.75rem;
        color: var(--tracking-muted);
    }
</style>
@endpush

@section('content')
<div class="container-fluid tracking-shell py-4">
    <div class="tracking-header">
        <div>
            <h1 class="tracking-title">استعلامات تتبع المنتج</h1>
            <p class="tracking-subtitle">مراقبة المنتجات المتتبعة في نظامك من خلال متابعة معلومات مثل الموقع، حالة المنتج، تاريخ الانتهاء وغيرها</p>
        </div>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-right"></i> العودة للتقارير
        </a>
    </div>

    {{-- Filters --}}
    <div class="tracking-filters">
        <form method="GET" action="{{ route('reports.product-tracking') }}" class="row g-3">
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
            <div class="col-md-3">
                <label class="form-label">الموقع/المستودع</label>
                <select name="location" class="form-select">
                    <option value="">جميع المواقع</option>
                    @foreach($locations as $location)
                        <option value="{{ $location }}" {{ request('location') == $location ? 'selected' : '' }}>{{ $location }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">الحالة</label>
                <select name="status" class="form-select">
                    <option value="">جميع الحالات</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>نشط</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>غير نشط</option>
                    <option value="low_stock" {{ request('status') == 'low_stock' ? 'selected' : '' }}>مخزون منخفض</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">تاريخ الانتهاء</label>
                <select name="expiry_status" class="form-select">
                    <option value="">الكل</option>
                    <option value="expired" {{ request('expiry_status') == 'expired' ? 'selected' : '' }}>منتهي الصلاحية</option>
                    <option value="expiring_soon" {{ request('expiry_status') == 'expiring_soon' ? 'selected' : '' }}>ينتهي قريباً</option>
                    <option value="safe" {{ request('expiry_status') == 'safe' ? 'selected' : '' }}>صالح</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> بحث
                </button>
            </div>
        </form>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="tracking-card text-center">
                <div class="fs-2 fw-bold text-primary">{{ $totalProducts }}</div>
                <div class="text-muted">إجمالي المنتجات</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="tracking-card text-center">
                <div class="fs-2 fw-bold text-success">{{ $activeProducts }}</div>
                <div class="text-muted">منتجات نشطة</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="tracking-card text-center">
                <div class="fs-2 fw-bold text-warning">{{ $lowStockProducts }}</div>
                <div class="text-muted">مخزون منخفض</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="tracking-card text-center">
                <div class="fs-2 fw-bold text-danger">{{ $expiringProducts }}</div>
                <div class="text-muted">ينتهي قريباً</div>
            </div>
        </div>
    </div>

    {{-- Products Table --}}
    <div class="tracking-card">
        <div class="table-responsive">
            <table class="table tracking-table">
                <thead>
                    <tr>
                        <th>المنتج</th>
                        <th>SKU</th>
                        <th>الموقع</th>
                        <th>الحالة</th>
                        <th>الكمية</th>
                        <th>تاريخ الانتهاء</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trackedProducts as $product)
                        <tr>
                            <td>
                                <div class="product-name">{{ $product->name }}</div>
                                <div class="product-sku">{{ $product->category?->name ?? 'بدون تصنيف' }}</div>
                            </td>
                            <td><code>{{ $product->sku }}</code></td>
                            <td>
                                <span class="location-badge">
                                    <i class="bi bi-geo-alt"></i>
                                    {{ $product->location ?? 'المستودع الرئيسي' }}
                                </span>
                            </td>
                            <td>
                                @if($product->status == 'active')
                                    <span class="product-status active">
                                        <i class="bi bi-check-circle"></i> نشط
                                    </span>
                                @elseif($product->status == 'inactive')
                                    <span class="product-status inactive">
                                        <i class="bi bi-x-circle"></i> غير نشط
                                    </span>
                                @else
                                    <span class="product-status low-stock">
                                        <i class="bi bi-exclamation-triangle"></i> مخزون منخفض
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="fw-bold {{ $product->stock_quantity <= $product->min_stock_level ? 'text-danger' : 'text-success' }}">
                                    {{ $product->stock_quantity }}
                                </span>
                                <small class="text-muted">/ {{ $product->min_stock_level }} حد أدنى</small>
                            </td>
                            <td>
                                @php($expiryDate = $product->expiry_date ?? $product->batches->first()?->expiry_date)
                                @if($expiryDate)
                                    @php($daysUntilExpiry = now()->diffInDays($expiryDate, false))
                                    @if($daysUntilExpiry < 0)
                                        <span class="expiry-badge danger">
                                            <i class="bi bi-calendar-x"></i> منتهي ({{ abs($daysUntilExpiry) }} يوم)
                                        </span>
                                    @elseif($daysUntilExpiry <= 30)
                                        <span class="expiry-badge warning">
                                            <i class="bi bi-calendar-event"></i> {{ $daysUntilExpiry }} يوم
                                        </span>
                                    @else
                                        <span class="expiry-badge safe">
                                            <i class="bi bi-calendar-check"></i> {{ $daysUntilExpiry }} يوم
                                        </span>
                                    @endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('products.show', $product) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> عرض
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <p class="text-muted mt-2">لا توجد منتجات مطابقة لمعايير البحث</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($trackedProducts->hasPages())
            <div class="mt-4">
                {{ $trackedProducts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
