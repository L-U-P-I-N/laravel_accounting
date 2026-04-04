@extends('layouts.app')

@section('title', 'القيود المحاسبية')

@php
    $filters = array_merge([
        'search' => '',
        'status' => '',
        'account_id' => null,
        'date_from' => '',
        'date_to' => '',
        'sort_direction' => 'desc',
    ], $filters ?? []);
    $canManageJournalEntries = auth()->user()->hasPermission('manage_journal_entries');
    $canViewReports = auth()->user()->hasPermission('view_reports');
    $journalReportParams = array_filter([
        'report_type' => 'account_balances',
        'period' => ($filters['date_from'] !== '' || $filters['date_to'] !== '') ? 'custom' : 'monthly',
        'account_id' => $filters['account_id'],
        'date_from' => $filters['date_from'] !== '' ? $filters['date_from'] : null,
        'date_to' => $filters['date_to'] !== '' ? $filters['date_to'] : null,
    ], fn ($value) => $value !== null && $value !== '');
    $journalEntriesReportUrl = route('reports', $journalReportParams);
@endphp

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-book"></i> القيود المحاسبية</h2>
        <p class="text-muted mt-2 mb-0">إدارة القيود اليومية</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if ($canViewReports)
            <a href="{{ $journalEntriesReportUrl }}" class="btn btn-outline-primary">
                <i class="fas fa-chart-bar ms-1"></i> مركز التقارير
            </a>
        @endif
        @if ($canManageJournalEntries)
            <a href="{{ route('journal_entries.create') }}" class="btn btn-gradient">
                <i class="fas fa-plus ms-1"></i> إنشاء قيد جديد
            </a>
        @endif
    </div>
</div>

<div class="search-box">
    <form method="GET" action="{{ route('journal_entries') }}" class="row g-3 align-items-end">
        <div class="col-lg-3 col-md-6">
            <label class="form-label">بحث</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" placeholder="رقم القيد أو الوصف أو المرجع" id="searchInput" name="search" value="{{ $filters['search'] }}">
            </div>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">الحالة</label>
            <select class="form-select" name="status">
                <option value="" {{ $filters['status'] === '' ? 'selected' : '' }}>جميع القيود</option>
                <option value="draft" {{ $filters['status'] === 'draft' ? 'selected' : '' }}>مسودة</option>
                <option value="posted" {{ $filters['status'] === 'posted' ? 'selected' : '' }}>مرحلة</option>
                <option value="reversed" {{ $filters['status'] === 'reversed' ? 'selected' : '' }}>مستعادة</option>
            </select>
        </div>
        <div class="col-lg-3 col-md-6">
            <label class="form-label">الحساب</label>
            <select class="form-select" name="account_id">
                <option value="">كل الحسابات</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" {{ $filters['account_id'] === $account->id ? 'selected' : '' }}>
                        {{ $account->code }} - {{ $account->name_ar ?? $account->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">من تاريخ</label>
            <input type="date" class="form-control" name="date_from" value="{{ $filters['date_from'] }}">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">إلى تاريخ</label>
            <input type="date" class="form-control" name="date_to" value="{{ $filters['date_to'] }}">
        </div>
        <div class="col-lg-2 col-md-6">
            <label class="form-label">ترتيب التاريخ</label>
            <select class="form-select" name="sort_direction">
                <option value="desc" {{ $filters['sort_direction'] === 'desc' ? 'selected' : '' }}>الأحدث أولاً</option>
                <option value="asc" {{ $filters['sort_direction'] === 'asc' ? 'selected' : '' }}>الأقدم أولاً</option>
            </select>
        </div>
        <div class="col-lg-2 col-md-6 d-grid">
            <button type="submit" class="btn btn-primary">تطبيق</button>
        </div>
        <div class="col-lg-2 col-md-6 d-grid">
            <a href="{{ route('journal_entries') }}" class="btn btn-outline-secondary">إعادة تعيين</a>
        </div>
    </form>
</div>

@if ($entries->isNotEmpty())
    @foreach ($entries as $entry)
        <div class="list-card journal-card">
            <div class="row align-items-center mb-3">
                <div class="col-md-3 mb-3 mb-md-0">
                    <h5 class="mb-1 fw-bold">{{ $entry->entry_number }}</h5>
                    <small class="text-muted">{{ $entry->entry_date }}</small>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <strong class="text-dark">{{ $entry->description ?: 'لا يوجد وصف' }}</strong>
                    @if ($entry->reference)
                        <br><small class="text-muted">المرجع: {{ $entry->reference }}</small>
                    @endif
                </div>
                <div class="col-md-2 mb-3 mb-md-0">
                    @php
                        $entryClass = match ($entry->status) {
                            'draft' => 'secondary',
                            'posted' => 'success',
                            default => 'danger',
                        };
                        $entryText = match ($entry->status) {
                            'draft' => 'مسودة',
                            'posted' => 'مرحلة',
                            default => 'مستعادة',
                        };
                        $entryTypeText = match ($entry->entry_type) {
                            'manual' => 'يدوي',
                            'invoice' => 'فاتورة',
                            'purchase' => 'شراء',
                            'payment' => 'دفعة',
                            'expense' => 'مصروف',
                            'payroll' => 'رواتب',
                            default => 'تسوية',
                        };
                        $entryOriginText = $entry->entry_origin === 'automatic' ? 'آلي' : 'يدوي';
                    @endphp
                    <span class="status-badge bg-{{ $entryClass }}">{{ $entryText }}</span>
                </div>
                <div class="col-md-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="debit">{{ number_format((float) $entry->total_debit, 2) }}</span>
                            <span class="ms-2">|</span>
                            <span class="credit ms-2">{{ number_format((float) $entry->total_credit, 2) }}</span>
                        </div>
                        <div class="btn-group">
                            <a href="{{ route('journal_entries.show', $entry) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                            @if ($canManageJournalEntries && $entry->status === 'draft')
                                <button type="button" class="btn btn-sm btn-outline-success" title="ترحيل القيد"><i class="fas fa-check"></i></button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-8">
                    <small class="text-muted">النوع: {{ $entryTypeText }} | طريقة الإنشاء: {{ $entryOriginText }}</small>
                    @if ($entry->source_type && $entry->source_id)
                        <br><small class="text-muted">المصدر: {{ class_basename(str_replace(':payment', '', $entry->source_type)) }} #{{ $entry->source_id }} | الأسطر: {{ $entry->lines->count() }}</small>
                    @endif
                </div>
                <div class="col-md-4 text-start">
                    <small class="text-muted">الإجمالي: <span class="debit">{{ number_format((float) $entry->total_debit, 2) }}</span> | <span class="credit">{{ number_format((float) $entry->total_credit, 2) }}</span></small>
                </div>
            </div>
        </div>
    @endforeach
@else
    <div class="text-center py-5">
        <i class="fas fa-book fa-4x text-muted mb-3"></i>
        <h4 class="text-muted">لا توجد قيود محاسبية</h4>
        <p class="text-muted">ابدأ بإنشاء أول قيد محاسبي</p>
        @if ($canManageJournalEntries)
            <a href="{{ route('journal_entries.create') }}" class="btn btn-gradient">
                <i class="fas fa-plus ms-1"></i> إنشاء أول قيد
            </a>
        @endif
    </div>
@endif
@endsection

