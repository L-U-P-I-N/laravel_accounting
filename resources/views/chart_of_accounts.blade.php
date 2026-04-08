@extends('layouts.app')

@section('title', 'شجرة الحسابات')

@php
    $canManageAccounts = auth()->user()->hasPermission('manage_accounts');
    $canViewReports = auth()->user()->hasPermission('view_reports');
    $accountModalErrorFields = ['code', 'name', 'name_ar', 'account_type', 'parent_id', 'description', 'allows_direct_transactions'];
    $accountTypeOptions = [
        'asset' => 'أصل',
        'liability' => 'خصم',
        'equity' => 'حق ملكية',
        'revenue' => 'إيراد',
        'expense' => 'مصروف',
        'cogs' => 'تكلفة مباعة',
    ];
    $accountsReportUrl = route('reports', ['report_type' => 'account_balances']);
    $chartAccountQuery = array_filter([
        'search' => $accountFilters['search'] ?? '',
        'account_type' => $accountFilters['account_type'] ?? '',
        'min_balance' => $accountFilters['min_balance'] ?? '',
        'max_balance' => $accountFilters['max_balance'] ?? '',
        'include_dynamic' => $includeDynamicAccounts ? 1 : null,
    ], fn ($value) => $value !== '' && $value !== null);
    $printChartUrl = route('chart_of_accounts.print', $chartAccountQuery);
    $exportChartUrl = route('chart_of_accounts.export', $chartAccountQuery);
@endphp

@push('styles')
<style>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap');

.coa-page {
    --coa-asset: #1e7a63;
    --coa-liability: #b24a62;
    --coa-equity: #315ccf;
    --coa-revenue: #25836a;
    --coa-expense: #cc6a1b;
    --coa-cogs: #6a4cc2;
    --coa-surface: rgba(255, 255, 255, 0.92);
    --coa-surface-strong: #ffffff;
    --coa-muted-surface: #f7f3ea;
    --coa-border: rgba(71, 85, 105, 0.12);
    --coa-text-main: #132238;
    --coa-text-soft: #667085;
    --coa-shadow: 0 20px 50px rgba(15, 23, 42, 0.07);
    --coa-shadow-soft: 0 10px 28px rgba(15, 23, 42, 0.05);
    --coa-radius-lg: 30px;
    --coa-radius-md: 24px;
    --coa-radius-sm: 18px;
    font-family: 'Tajawal', 'Segoe UI', Tahoma, sans-serif;
    color: var(--coa-text-main);
}

.coa-page .btn {
    border-radius: 14px;
    font-weight: 700;
    padding: 0.72rem 1rem;
}

.coa-page .btn-sm {
    padding: 0.5rem 0.8rem;
}

.coa-page .form-control,
.coa-page .form-select,
.coa-page .input-group-text {
    border-radius: 14px;
    border-color: rgba(148, 163, 184, 0.28);
    min-height: 48px;
    font-size: 0.98rem;
}

.coa-page .form-control,
.coa-page .form-select {
    color: var(--coa-text-main);
    box-shadow: none;
}

.coa-page .form-control:focus,
.coa-page .form-select:focus {
    border-color: rgba(49, 92, 207, 0.42);
    box-shadow: 0 0 0 0.2rem rgba(49, 92, 207, 0.09);
}

.coa-page .input-group-text {
    background: #fbfcfe;
    color: var(--coa-text-soft);
}

.coa-page .badge {
    border-radius: 999px;
    padding: 0.55rem 0.85rem;
    font-weight: 700;
}

.coa-hero {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at top left, rgba(37, 131, 106, 0.16), transparent 34%),
        radial-gradient(circle at top right, rgba(49, 92, 207, 0.15), transparent 30%),
        linear-gradient(135deg, #faf6ee 0%, #fffefb 52%, #eef4ff 100%);
    border: 1px solid var(--coa-border);
    border-radius: var(--coa-radius-lg);
    padding: 34px;
    box-shadow: var(--coa-shadow);
}

.coa-hero::after {
    content: '';
    position: absolute;
    inset: auto -60px -80px auto;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(37, 131, 106, 0.08);
    filter: blur(12px);
}

.coa-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid rgba(37, 131, 106, 0.18);
    color: #14532d;
    font-size: 0.92rem;
    font-weight: 700;
    letter-spacing: 0.01em;
}

.coa-hero-title {
    font-size: clamp(1.9rem, 1.55rem + 1vw, 2.65rem);
    font-weight: 800;
    line-height: 1.35;
    margin: 16px 0 10px;
    color: var(--coa-text-main);
}

.coa-hero-text {
    max-width: 760px;
    color: var(--coa-text-soft);
    font-size: 1.02rem;
    line-height: 1.95;
    margin: 0;
}

.coa-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: flex-end;
}

.coa-snapshot-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 16px;
}

.coa-snapshot-card {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.96) 0%, rgba(250, 248, 243, 0.94) 100%);
    border: 1px solid var(--coa-border);
    border-radius: var(--coa-radius-sm);
    padding: 20px 18px;
    box-shadow: var(--coa-shadow-soft);
}

.coa-snapshot-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
}

.coa-type-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 13px;
    border-radius: 999px;
    color: #fff;
    font-size: 0.82rem;
    font-weight: 700;
    box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.18);
}

.coa-type-pill.asset, .coa-type-dot.asset { background: var(--coa-asset); }
.coa-type-pill.liability, .coa-type-dot.liability { background: var(--coa-liability); }
.coa-type-pill.equity, .coa-type-dot.equity { background: var(--coa-equity); }
.coa-type-pill.revenue, .coa-type-dot.revenue { background: var(--coa-revenue); }
.coa-type-pill.expense, .coa-type-dot.expense { background: var(--coa-expense); }
.coa-type-pill.cogs, .coa-type-dot.cogs { background: var(--coa-cogs); }

.coa-type-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.coa-snapshot-card h4 {
    margin: 0;
    font-size: 2.15rem;
    font-weight: 800;
    color: var(--coa-text-main);
}

.coa-snapshot-card p {
    margin: 10px 0 0;
    color: var(--coa-text-soft);
    font-size: 0.92rem;
    line-height: 1.7;
}

.coa-filter-panel,
.coa-tree-shell {
    background: var(--coa-surface);
    backdrop-filter: blur(10px);
    border: 1px solid var(--coa-border);
    border-radius: var(--coa-radius-md);
    box-shadow: var(--coa-shadow);
}

.coa-filter-panel {
    padding: 24px;
}

.coa-filter-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
}

.coa-filter-title {
    font-weight: 800;
    font-size: 1.22rem;
    margin: 0;
    color: var(--coa-text-main);
}

.coa-filter-subtitle {
    margin: 8px 0 0;
    color: var(--coa-text-soft);
    line-height: 1.8;
}

.coa-filter-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.coa-tree-shell {
    padding: 26px;
    background:
        linear-gradient(180deg, #fffdf8 0%, #ffffff 100%);
}

.coa-tree-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
}

.coa-tree-title {
    margin: 0;
    font-size: 1.26rem;
    font-weight: 800;
    color: var(--coa-text-main);
}

.coa-tree-subtitle {
    margin: 8px 0 0;
    color: var(--coa-text-soft);
    line-height: 1.8;
}

.coa-tree-toolbar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.coa-tree-canvas {
    position: relative;
}

.coa-empty-state {
    padding: 64px 22px;
    text-align: center;
    border: 1px dashed rgba(107, 114, 128, 0.35);
    border-radius: var(--coa-radius-sm);
    background: linear-gradient(180deg, #fbfcfe 0%, #f9f6ef 100%);
    color: var(--coa-text-soft);
}

.coa-empty-state i {
    font-size: 2.4rem;
    margin-bottom: 14px;
    color: #9ca3af;
}

@media (max-width: 1199.98px) {
    .coa-snapshot-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {
    .coa-hero {
        padding: 24px;
    }

    .coa-hero-actions,
    .coa-filter-actions,
    .coa-tree-header,
    .coa-filter-header {
        justify-content: flex-start;
    }

    .coa-snapshot-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .coa-tree-shell,
    .coa-filter-panel {
        padding: 18px;
    }
}

@media (max-width: 575.98px) {
    .coa-snapshot-grid {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush

@section('content')
<div class="coa-page">
    <section class="coa-hero mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <span class="coa-eyebrow"><i class="fas fa-sitemap"></i> دليل الشركة المحاسبي</span>
                <h1 class="coa-hero-title">شجرة الحسابات بشكل بصري أوضح وأكثر قابلية للتصفح</h1>
                <p class="coa-hero-text">
                    استعرض الجذور والفروع والحسابات التابعة بطريقة هرمية مباشرة، مع فلترة سريعة وإحصاءات تساعدك على فهم توزيع الحسابات داخل الشركة بدون ازدحام بصري.
                </p>
            </div>
            <div class="col-lg-4">
                <div class="coa-hero-actions">
                    @if ($canViewReports)
                        <a href="{{ $accountsReportUrl }}" class="btn btn-outline-primary">
                            <i class="fas fa-chart-column ms-1"></i> مركز التقارير
                        </a>
                    @endif
                    <a href="{{ $printChartUrl }}" class="btn btn-outline-dark" target="_blank" rel="noopener">
                        <i class="fas fa-print ms-1"></i> طباعة الشجرة
                    </a>
                    <a href="{{ $exportChartUrl }}" class="btn btn-outline-success">
                        <i class="fas fa-file-csv ms-1"></i> تصدير Excel
                    </a>
                    @if ($canManageAccounts)
                        <form method="POST" action="{{ route('chart_of_accounts.resync') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary" onclick="return confirm('سيتم تحديث الشجرة والحسابات المرتبطة وإعادة توليد القيود الآلية الحالية. هل تريد المتابعة؟');">
                                <i class="fas fa-rotate ms-1"></i> إعادة مزامنة المحاسبة
                            </button>
                        </form>
                        <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                            <i class="fas fa-plus ms-1"></i> إضافة حساب جديد
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="coa-snapshot-grid mb-4">
        @foreach (['asset' => 'أصول', 'liability' => 'خصوم', 'equity' => 'ملكية', 'revenue' => 'إيرادات', 'expense' => 'مصروفات', 'cogs' => 'تكلفة'] as $type => $label)
            <article class="coa-snapshot-card">
                <div class="coa-snapshot-top">
                    <span class="coa-type-pill {{ $type }}">{{ $label }}</span>
                    <i class="fas fa-layer-group text-muted"></i>
                </div>
                <h4>{{ $accountStats->where('account_type', $type)->count() }}</h4>
                <p>عدد الحسابات ضمن هذا التصنيف بعد تطبيق الفلاتر الحالية.</p>
            </article>
        @endforeach
    </section>

    <section class="coa-filter-panel mb-4">
        <div class="coa-filter-header">
            <div>
                <h2 class="coa-filter-title">البحث والفلترة</h2>
                <p class="coa-filter-subtitle">اعثر على أي حساب بالاسم أو الكود، وضيّق الشجرة حسب النوع أو الرصيد لقراءة أسهل.</p>
            </div>
            <div class="coa-filter-actions">
                @if ($hasAccountFilters)
                    <span class="badge text-bg-primary align-self-start">{{ $matchingAccounts->count() }} نتيجة مطابقة</span>
                @endif
                <a href="{{ route('chart_of_accounts') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-undo ms-1"></i> مسح الفلاتر
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('chart_of_accounts') }}" class="row g-3">
            <div class="col-lg-4 col-md-6">
                <label class="form-label">بحث عن حساب</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control" value="{{ $accountFilters['search'] ?? '' }}" placeholder="اسم الحساب أو الكود">
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">نوع الحساب</label>
                <select name="account_type" class="form-select">
                    <option value="">كل الأنواع</option>
                    @foreach ($accountTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected(($accountFilters['account_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">أقل رصيد</label>
                <input type="number" step="0.01" name="min_balance" class="form-control" value="{{ $accountFilters['min_balance'] ?? '' }}" placeholder="0.00" lang="en" dir="ltr">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">أعلى رصيد</label>
                <input type="number" step="0.01" name="max_balance" class="form-control" value="{{ $accountFilters['max_balance'] ?? '' }}" placeholder="0.00" lang="en" dir="ltr">
            </div>
            <div class="col-lg-2 col-md-6 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="include_dynamic" value="1" id="includeDynamicAccounts" @checked($includeDynamicAccounts)>
                    <label class="form-check-label" for="includeDynamicAccounts">عرض الحسابات الديناميكية</label>
                </div>
            </div>
            <div class="col-lg-1 col-md-12 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter ms-1"></i> فلترة
                </button>
            </div>
        </form>
    </section>

    <section class="coa-tree-shell">
        <div class="coa-tree-header">
            <div>
                <h2 class="coa-tree-title"><i class="fas fa-diagram-project ms-2 text-primary"></i>الهيكل الشجري للحسابات</h2>
                <p class="coa-tree-subtitle">افتح الفروع التي تحتاجها فقط، وانتقل عبر الحسابات من الجذر حتى أدق الحسابات الفرعية.</p>
            </div>
            <div class="coa-tree-toolbar">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-tree-expand="all">
                    <i class="fas fa-up-right-and-down-left-from-center ms-1"></i> توسيع الكل
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-tree-collapse="all">
                    <i class="fas fa-minimize ms-1"></i> طي الكل
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($hasAccountFilters)
            <div class="alert alert-info">
                تم تطبيق الفلاتر على الشجرة، لذلك يتم إظهار الحسابات المطابقة مع آبائها للحفاظ على التسلسل المحاسبي واضحًا.
            </div>
        @elseif (! $includeDynamicAccounts)
            <div class="alert alert-light border">
                يتم إخفاء الحسابات الديناميكية الخاصة بالعملاء والموردين والمنتجات من العرض الأساسي. فعّل خيار "عرض الحسابات الديناميكية" إذا أردت ظهورها داخل الشجرة.
            </div>
        @endif

        <div class="coa-tree-canvas">
            @forelse ($accounts as $account)
                @include('partials.account_node', ['account' => $account, 'company' => $company, 'level' => 0, 'canManageAccounts' => $canManageAccounts])
            @empty
                <div class="coa-empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h5 class="mb-2">{{ $hasAccountFilters ? 'لا توجد حسابات مطابقة للفلاتر المحددة' : 'لا توجد حسابات بعد' }}</h5>
                    <p class="mb-0">جرّب توسيع نطاق الفلاتر أو أضف حسابًا جديدًا لبدء بناء الدليل.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>

@if ($canManageAccounts)
    <div class="modal fade" id="addAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة حساب جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('chart_of_accounts.store') }}" id="addAccountForm">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info">
                            سيتم اقتراح الحساب الأب تلقائيًا حسب نوع الحساب، ويمكنك تغييره يدويًا قبل الحفظ.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">الكود</label>
                                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" required>
                                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الاسم</label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الاسم بالعربي</label>
                                <input type="text" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar') }}">
                                @error('name_ar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">نوع الحساب</label>
                                <select name="account_type" id="accountTypeSelect" class="form-select @error('account_type') is-invalid @enderror" required>
                                    @foreach ($accountTypeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('account_type', 'asset') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('account_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">الحساب الأب</label>
                                <select name="parent_id" id="parentAccountSelect" class="form-select @error('parent_id') is-invalid @enderror">
                                    <option value="">بدون أب</option>
                                    @foreach ($parentOptions as $option)
                                        <option value="{{ $option['id'] }}" data-type="{{ $option['type'] }}" @selected((string) old('parent_id') === (string) $option['id'])>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">سيتم اختيار الأب المقترح تلقائيًا حسب النوع، ويمكن تغييره هنا.</div>
                                @error('parent_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">الوصف</label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="allows_direct_transactions" value="1" id="accountAllowsDirectTransactions" @checked(old('allows_direct_transactions'))>
                                    <label class="form-check-label" for="accountAllowsDirectTransactions">يمكن الدفع والتحصيل بهذا الحساب</label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="accountIsActive" @checked(old('is_active', '1') == '1')>
                                    <label class="form-check-label" for="accountIsActive">حساب نشط</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إضافة حساب</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const accountTypeSelect = document.getElementById('accountTypeSelect');
    const parentAccountSelect = document.getElementById('parentAccountSelect');
    const suggestedParents = @json($suggestedParentIds ?? []);
    const hasOldParent = @json(old('parent_id'));
    const shouldOpenModal = @json($errors->hasAny($accountModalErrorFields));

    function applySuggestedParent() {
        if (!accountTypeSelect || !parentAccountSelect) {
            return;
        }

        const suggestedParentId = suggestedParents[accountTypeSelect.value] ?? '';
        parentAccountSelect.value = suggestedParentId ? String(suggestedParentId) : '';
    }

    if (accountTypeSelect && parentAccountSelect) {
        if (!hasOldParent) {
            applySuggestedParent();
        }

        accountTypeSelect.addEventListener('change', applySuggestedParent);
    }

    if (shouldOpenModal) {
        const modalElement = document.getElementById('addAccountModal');
        if (modalElement && window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
    }

    document.querySelector('[data-tree-expand="all"]')?.addEventListener('click', function () {
        document.querySelectorAll('.coa-node-children.collapse').forEach(function (element) {
            window.bootstrap.Collapse.getOrCreateInstance(element, { toggle: false }).show();
        });
    });

    document.querySelector('[data-tree-collapse="all"]')?.addEventListener('click', function () {
        document.querySelectorAll('.coa-node-children.collapse').forEach(function (element) {
            window.bootstrap.Collapse.getOrCreateInstance(element, { toggle: false }).hide();
        });
    });
});
</script>
@endpush
