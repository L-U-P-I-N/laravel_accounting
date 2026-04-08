@extends('layouts.app')

@section('title', 'المصروفات')

@php
    $createExpenseModalHasErrors = $errors->any();
    $canViewReports = auth()->user()->hasPermission('view_reports');
    $expensesReportUrl = route('reports', ['report_type' => 'expense_details']);
@endphp

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div>
            <h2 class="page-title"><i class="fas fa-receipt"></i> المصروفات</h2>
            <p class="text-muted mt-2 mb-0">تسجيل المصروفات باسم واضح وربطها مباشرة بحساب المصروف وحساب السداد في شجرة الحسابات.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if ($canViewReports)
                <a href="{{ $expensesReportUrl }}" class="btn btn-outline-primary">
                    <i class="fas fa-chart-bar ms-1"></i> مركز التقارير
                </a>
            @endif
            <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="fas fa-plus ms-1"></i> إضافة مصروف
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="list-card mb-4">
        <div class="card-header bg-transparent border-0 px-0 pt-0"><h5 class="mb-0">فلترة المصروفات</h5></div>
        <div class="card-body px-0 pb-0">
            <form method="GET" action="{{ route('expenses') }}" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">بحث</label>
                    <input type="text" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="اسم المصروف أو المرجع">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] }}">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] }}">
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">حساب المصروف</label>
                    <select name="expense_account_id" class="form-select">
                        <option value="">كل الحسابات</option>
                        @foreach ($expenseAccounts as $account)
                            <option value="{{ $account->id }}" {{ $filters['expense_account_id'] === $account->id ? 'selected' : '' }}>{{ $account->code }} - {{ $account->name_ar ?? $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">مصروف محدد</label>
                    <select name="expense_id" class="form-select">
                        <option value="">كل المصروفات</option>
                        @foreach ($expenseTargets as $expenseTarget)
                            <option value="{{ $expenseTarget->id }}" {{ $filters['expense_id'] === $expenseTarget->id ? 'selected' : '' }}>
                                {{ $expenseTarget->name ?: ($expenseTarget->reference ?: 'مصروف #' . $expenseTarget->id) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">ترتيب التاريخ</label>
                    <select name="sort_direction" class="form-select">
                        <option value="desc" {{ $filters['sort_direction'] === 'desc' ? 'selected' : '' }}>الأحدث أولاً</option>
                        <option value="asc" {{ $filters['sort_direction'] === 'asc' ? 'selected' : '' }}>الأقدم أولاً</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter ms-1"></i>تطبيق</button>
                </div>
                <div class="col-lg-2 col-md-6 d-grid">
                    <a href="{{ route('expenses') }}" class="btn btn-outline-secondary">إعادة تعيين</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-wallet"></i></div>
                <div class="stat-value">{{ number_format((float) $expenses->sum('total'), 2) }}</div>
                <div class="stat-label">إجمالي المصروفات ({{ $company->currency }})</div>
            </div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
                <div class="stat-value">{{ $expenses->count() }}</div>
                <div class="stat-label">عدد العمليات</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-sitemap"></i></div>
                <div class="stat-value">{{ $expenseAccounts->count() }}</div>
                <div class="stat-label">حسابات المصروف المتاحة</div>
            </div>
        </div>
    </div>

    <div class="recent-activity">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">سجل المصروفات</h5>
                <p class="text-muted mb-0">كل مصروف هنا يُنشئ قيدًا محاسبيًا آليًا ويرتبط مباشرة بشجرة الحسابات.</p>
            </div>
            <span class="badge text-bg-light">{{ $expenses->count() }} سجل</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>رقم المصروف</th>
                        <th>اسم المصروف</th>
                        <th>التاريخ</th>
                        <th>حساب المصروف</th>
                        <th>حساب السداد</th>
                        <th>الإجمالي</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($expenses->isEmpty())
                        <tr>
                            <td colspan="7" class="text-center text-muted">لا توجد مصروفات مسجلة بعد.</td>
                        </tr>
                    @else
                        @foreach ($expenses as $expense)
                            <tr>
                                <td>{{ $expense->expense_number }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $expense->name }}</div>
                                    @if ($expense->reference)
                                        <small class="text-muted">المرجع: {{ $expense->reference }}</small>
                                    @endif
                                </td>
                                <td>{{ optional($expense->expense_date)->format('Y-m-d') ?: $expense->expense_date }}</td>
                                <td>{{ $expense->expenseAccount?->code }} - {{ $expense->expenseAccount?->name_ar ?? $expense->expenseAccount?->name }}</td>
                                <td>{{ $expense->paymentAccount?->code }} - {{ $expense->paymentAccount?->name_ar ?? $expense->paymentAccount?->name }}</td>
                                <td>{{ number_format((float) $expense->total, 2) }} {{ $company->currency }}</td>
                                <td class="list-actions-col">
                                    <div class="list-actions-group">
                                        <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا المصروف؟ سيتم أيضًا عكس القيد المحاسبي المرتبط به.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
        <div class="modal-content">
            <form method="POST" action="{{ route('expenses.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">إضافة مصروف جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($createExpenseModalHasErrors)
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">اسم المصروف</label><input type="text" name="name" class="form-control" value="{{ old('name') }}" required></div>
                        <div class="col-md-3"><label class="form-label">تاريخ المصروف</label><input type="date" name="expense_date" class="form-control" value="{{ old('expense_date', now()->format('Y-m-d')) }}" required></div>
                        <div class="col-md-3">
                            <label class="form-label">المرجع</label>
                            <input type="text" name="reference" class="form-control" value="{{ old('reference', $suggestedExpenseReference) }}">
                            <div class="form-text">يتم توليد المرجع تلقائيًا ويمكنك تعديله إذا لزم.</div>
                        </div>
                        <div class="col-md-6"><label class="form-label">حساب المصروف</label><select name="expense_account_id" class="form-select" required><option value="">اختر الحساب</option>@foreach ($expenseAccounts as $account)<option value="{{ $account->id }}" {{ (string) old('expense_account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->code }} - {{ $account->name_ar ?? $account->name }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">حساب السداد</label><select name="payment_account_id" class="form-select" required><option value="">اختر الحساب</option>@foreach ($paymentAccounts as $account)<option value="{{ $account->id }}" {{ (string) old('payment_account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->code }} - {{ $account->name_ar ?? $account->name }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">المبلغ قبل الضريبة</label><input type="number" name="amount" class="form-control" min="0.01" step="0.01" value="{{ old('amount') }}" required lang="en" dir="ltr"></div>
                        <div class="col-md-4"><label class="form-label">الضريبة %</label><input type="number" name="tax_rate" class="form-control" min="0" max="100" step="0.01" value="{{ old('tax_rate', 0) }}" lang="en" dir="ltr"></div>
                        <div class="col-12"><label class="form-label">وصف المصروف</label><textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ المصروف</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
@if ($createExpenseModalHasErrors)
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('addExpenseModal');

    if (modalElement && window.bootstrap) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
@endif
</script>
@endpush
