@extends('layouts.app')

@section('title', 'المنتجات')

@php
    $canManageProducts = auth()->user()->hasPermission('manage_products');
    $canViewReports = auth()->user()->hasPermission('view_reports');
    $productsReportUrl = route('reports', ['report_type' => 'product_sales']);
@endphp

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-box"></i> المنتجات</h2>
        <p class="text-muted mt-2 mb-0">إدارة المخزون والمنتجات</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if ($canViewReports)
            <a href="{{ $productsReportUrl }}" class="btn btn-outline-primary">
                <i class="fas fa-chart-bar ms-1"></i> مركز التقارير
            </a>
        @endif
        @if ($canManageProducts)
            <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fas fa-plus ms-1"></i> إضافة منتج جديد
            </button>
        @endif
    </div>
</div>

<div class="search-box">
    <div class="row g-3">
        <div class="col-md-8 mb-3 mb-md-0">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" placeholder="البحث عن منتج..." id="searchInput">
            </div>
        </div>
        <div class="col-md-4">
            <select class="form-select">
                <option>جميع المنتجات</option>
                <option>المنتجات النشطة</option>
                <option>الخدمات</option>
                <option>المنتجات فقط</option>
            </select>
        </div>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@php
    $activeProductModal = old('product_modal');
    $createProductModalHasErrors = $errors->any() && $activeProductModal === 'create';
@endphp

@if ($products->isNotEmpty())
    @foreach ($products as $product)
        <div class="list-card product-card">
            <div class="row align-items-center g-3">
                <div class="col-md-1 mb-3 mb-md-0">
                    <div class="avatar-square avatar-orange"><i class="fas fa-box"></i></div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <h5 class="mb-1 fw-bold">{{ $product->name }}</h5>
                    <small class="text-muted">{{ $product->code ?? 'لا يوجد كود' }}</small>
                    <br>
                    <small class="text-muted">المورد: {{ $product->supplier?->name ?? 'غير محدد' }}</small>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <div class="mb-1"><strong>سعر البيع:</strong> {{ number_format((float) ($product->sell_price ?? 0), 2) }} {{ $company->currency }}</div>
                    <div><strong>سعر التكلفة:</strong> {{ number_format((float) ($product->cost_price ?? 0), 2) }} {{ $company->currency }}</div>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <div class="mb-1"><strong>المخزون:</strong> <span class="stock-badge bg-success">{{ $product->stock_quantity ?? 0 }} {{ $product->unit ?? 'وحدة' }}</span></div>
                    <div><strong>الحد الأدنى:</strong> {{ $product->min_stock ?? 0 }} {{ $product->unit ?? 'وحدة' }}</div>
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    <div class="mb-1"><strong>الضريبة:</strong> {{ number_format((float) ($product->tax_rate ?? 0), 1) }}%</div>
                    <div><span class="badge bg-success">خاضع</span></div>
                </div>
                <div class="col-md-2 text-start list-actions-col">
                    <span class="badge bg-{{ $product->type === 'service' ? 'secondary' : 'info' }} mb-2 d-inline-block">{{ $product->type === 'service' ? 'خدمة' : 'منتج' }}</span>
                    @if ($canManageProducts)
                        <div class="list-actions-group">
                            <button type="button" class="btn btn-sm btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#editProductModal{{ $product->id }}"><i class="fas fa-edit"></i></button>
                            <form method="POST" action="{{ route('products.destroy', $product) }}" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنتج؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm ms-1"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($canManageProducts)
            @php
                $editModalKey = 'edit-' . $product->id;
                $editModalHasErrors = $errors->any() && $activeProductModal === $editModalKey;
            @endphp
            <div class="modal fade" id="editProductModal{{ $product->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('products.update', $product) }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="product_modal" value="{{ $editModalKey }}">
                            <div class="modal-header">
                                <h5 class="modal-title">تعديل المنتج</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                @if ($editModalHasErrors)
                                    <div class="alert alert-danger">
                                        <ul class="mb-0 ps-3">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label">الاسم</label><input type="text" name="name" class="form-control" value="{{ $editModalHasErrors ? old('name') : $product->name }}" required></div>
                                    <div class="col-md-6"><label class="form-label">الاسم بالعربي</label><input type="text" name="name_ar" class="form-control" value="{{ $editModalHasErrors ? old('name_ar') : $product->name_ar }}"></div>
                                    <div class="col-md-4"><label class="form-label">الكود</label><input type="text" name="code" class="form-control" value="{{ $editModalHasErrors ? old('code') : $product->code }}"></div>
                                    <div class="col-md-4"><label class="form-label">المورد</label><select name="supplier_id" class="form-select"><option value="">بدون مورد</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" {{ (string) ($editModalHasErrors ? old('supplier_id') : $product->supplier_id) === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>@endforeach</select></div>
                                    <div class="col-md-4"><label class="form-label">النوع</label><select name="type" class="form-select"><option value="product" {{ ($editModalHasErrors ? old('type') : $product->type) === 'product' ? 'selected' : '' }}>منتج</option><option value="service" {{ ($editModalHasErrors ? old('type') : $product->type) === 'service' ? 'selected' : '' }}>خدمة</option></select></div>
                                    <div class="col-md-4"><label class="form-label">الوحدة</label><input type="text" name="unit" class="form-control" value="{{ $editModalHasErrors ? old('unit') : $product->unit }}"></div>
                                    <div class="col-md-4"><label class="form-label">سعر التكلفة</label><input type="number" name="cost_price" class="form-control" min="0" step="0.01" value="{{ $editModalHasErrors ? old('cost_price') : $product->cost_price }}" lang="en" dir="ltr"></div>
                                    <div class="col-md-4"><label class="form-label">سعر البيع</label><input type="number" name="sell_price" class="form-control" min="0" step="0.01" value="{{ $editModalHasErrors ? old('sell_price') : $product->sell_price }}" lang="en" dir="ltr"></div>
                                    <div class="col-md-4"><label class="form-label">الضريبة %</label><input type="number" name="tax_rate" class="form-control" min="0" max="100" step="0.01" value="{{ $editModalHasErrors ? old('tax_rate') : $product->tax_rate }}" lang="en" dir="ltr"></div>
                                    <div class="col-md-6"><label class="form-label">المخزون الحالي</label><input type="number" name="stock_quantity" class="form-control" min="0" step="0.01" value="{{ $editModalHasErrors ? old('stock_quantity') : $product->stock_quantity }}" lang="en" dir="ltr"></div>
                                    <div class="col-md-6"><label class="form-label">الحد الأدنى للمخزون</label><input type="number" name="min_stock" class="form-control" min="0" step="0.01" value="{{ $editModalHasErrors ? old('min_stock') : $product->min_stock }}" lang="en" dir="ltr"></div>
                                    <div class="col-12"><label class="form-label">الوصف</label><textarea name="description" class="form-control" rows="3">{{ $editModalHasErrors ? old('description') : $product->description }}</textarea></div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@else
    <div class="text-center py-5">
        <i class="fas fa-box fa-4x text-muted mb-3"></i>
        <h4 class="text-muted">لا توجد منتجات</h4>
        <p class="text-muted">ابدأ بإضافة أول منتج أو خدمة وربطها بعمليات البيع والشراء.</p>
        @if ($canManageProducts)
            <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fas fa-plus ms-1"></i> إضافة أول منتج
            </button>
        @endif
    </div>
@endif

@if ($canManageProducts)
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
            <div class="modal-content">
                <form method="POST" action="{{ route('products.store') }}">
                    @csrf
                    <input type="hidden" name="product_modal" value="create">
                    <div class="modal-header">
                    <h5 class="modal-title">إضافة منتج جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                    @if ($createProductModalHasErrors)
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">الاسم</label><input type="text" name="name" class="form-control" value="{{ old('name') }}" required></div>
                        <div class="col-md-6"><label class="form-label">الاسم بالعربي</label><input type="text" name="name_ar" class="form-control" value="{{ old('name_ar') }}"></div>
                        <div class="col-md-4"><label class="form-label">الكود</label><input type="text" name="code" class="form-control" value="{{ old('code') }}" placeholder="يُنشأ تلقائياً إذا تركته فارغاً"></div>
                        <div class="col-md-4"><label class="form-label">المورد</label><select name="supplier_id" class="form-select"><option value="">بدون مورد</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" {{ (string) old('supplier_id') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">النوع</label><select name="type" class="form-select"><option value="product" {{ old('type') === 'product' ? 'selected' : '' }}>منتج</option><option value="service" {{ old('type') === 'service' ? 'selected' : '' }}>خدمة</option></select></div>
                        <div class="col-md-4"><label class="form-label">الوحدة</label><input type="text" name="unit" class="form-control" value="{{ old('unit', 'وحدة') }}"></div>
                        <div class="col-md-4"><label class="form-label">سعر التكلفة</label><input type="number" name="cost_price" class="form-control" min="0" step="0.01" value="{{ old('cost_price', 0) }}" lang="en" dir="ltr"></div>
                        <div class="col-md-4"><label class="form-label">سعر البيع</label><input type="number" name="sell_price" class="form-control" min="0" step="0.01" value="{{ old('sell_price', 0) }}" lang="en" dir="ltr"></div>
                        <div class="col-md-4"><label class="form-label">الضريبة %</label><input type="number" name="tax_rate" class="form-control" min="0" max="100" step="0.01" value="{{ old('tax_rate', 15) }}" lang="en" dir="ltr"></div>
                        <div class="col-md-6"><label class="form-label">المخزون الحالي</label><input type="number" name="stock_quantity" class="form-control" min="0" step="0.01" value="{{ old('stock_quantity', 0) }}" lang="en" dir="ltr"></div>
                        <div class="col-md-6"><label class="form-label">الحد الأدنى للمخزون</label><input type="number" name="min_stock" class="form-control" min="0" step="0.01" value="{{ old('min_stock', 0) }}" lang="en" dir="ltr"></div>
                        <div class="col-12"><label class="form-label">الوصف</label><textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea></div>
                    </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إضافة منتج</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
document.getElementById('searchInput')?.addEventListener('keyup', function () {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('.product-card').forEach((card) => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(searchTerm) ? 'block' : 'none';
    });
});

@if ($errors->any())
document.addEventListener('DOMContentLoaded', () => {
    const modalId = @json($activeProductModal === 'create' ? 'addProductModal' : (str_starts_with((string) $activeProductModal, 'edit-') ? 'editProductModal' . substr((string) $activeProductModal, 5) : 'addProductModal'));
    const modalElement = document.getElementById(modalId);
    if (modalElement && window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
@endif
</script>
@endpush
