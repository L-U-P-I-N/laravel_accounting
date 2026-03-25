@extends('layouts.app')

@section('title', 'تفاصيل القيد المحاسبي')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div>
            <h2 class="page-title"><i class="fas fa-book-open"></i> {{ $journalEntry->entry_number }}</h2>
            <p class="text-muted mt-2 mb-0">مراجعة القيد سطرًا بسطر مع مصدره المحاسبي وطريقة إنشائه.</p>
        </div>
        <div class="list-actions-group">
            <a href="{{ route('journal_entries') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right ms-1"></i> العودة إلى القيود
            </a>
            @if ($sourceContext['route'])
                <a href="{{ $sourceContext['route'] }}" class="btn btn-outline-primary">
                    <i class="fas fa-link ms-1"></i> {{ $sourceContext['label'] }}
                </a>
            @endif
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-value fs-5">{{ optional($journalEntry->entry_date)->format('Y-m-d') }}</div>
                <div class="stat-label">تاريخ القيد</div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-gears"></i></div>
                <div class="stat-value fs-5">{{ $journalEntry->entry_origin === 'automatic' ? 'آلي' : 'يدوي' }}</div>
                <div class="stat-label">طريقة الإنشاء</div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-arrow-down"></i></div>
                <div class="stat-value fs-5">{{ number_format((float) $journalEntry->total_debit, 2) }} {{ $company->currency }}</div>
                <div class="stat-label">إجمالي المدين</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-arrow-up"></i></div>
                <div class="stat-value fs-5">{{ number_format((float) $journalEntry->total_credit, 2) }} {{ $company->currency }}</div>
                <div class="stat-label">إجمالي الدائن</div>
            </div>
        </div>
    </div>

    <div class="list-card mb-4">
        <div class="row g-3">
            <div class="col-md-4"><strong>الوصف</strong><div class="text-muted mt-1">{{ $journalEntry->description ?: '-' }}</div></div>
            <div class="col-md-2"><strong>المرجع</strong><div class="text-muted mt-1">{{ $journalEntry->reference ?: '-' }}</div></div>
            <div class="col-md-2"><strong>النوع</strong><div class="text-muted mt-1">{{ $journalEntry->entry_type }}</div></div>
            <div class="col-md-2"><strong>الحالة</strong><div class="text-muted mt-1">{{ $journalEntry->status }}</div></div>
            <div class="col-md-2"><strong>المصدر</strong><div class="text-muted mt-1">{{ $sourceContext['label'] }}</div></div>
        </div>
    </div>

    <div class="recent-activity">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">بنود القيد</h5>
                <p class="text-muted mb-0">كل سطر مرتبط مباشرة بحساب من شجرة الحسابات مع قيمة المدين أو الدائن.</p>
            </div>
            <span class="badge text-bg-light">{{ $journalEntry->lines->count() }} أسطر</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>الحساب</th>
                        <th>الوصف</th>
                        <th>مدين</th>
                        <th>دائن</th>
                        <th>الرصيد الحالي للحساب</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($journalEntry->lines as $line)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $line->account?->code }} - {{ $line->account?->name_ar ?? $line->account?->name }}</div>
                                <small class="text-muted">{{ $line->account?->account_type }}</small>
                            </td>
                            <td>{{ $line->description ?: '-' }}</td>
                            <td class="debit">{{ number_format((float) $line->debit, 2) }} {{ $company->currency }}</td>
                            <td class="credit">{{ number_format((float) $line->credit, 2) }} {{ $company->currency }}</td>
                            <td>{{ number_format((float) ($line->account?->balance ?? 0), 2) }} {{ $company->currency }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
