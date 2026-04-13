@extends('layouts.app')

@section('title', 'تفاصيل الحساب')

@php
    $accountTypeLabel = $account->display_account_type ?: match ($account->account_type) {
        'asset' => 'أصل',
        'liability' => 'خصم',
        'equity' => 'حق ملكية',
        'revenue' => 'إيراد',
        'expense' => 'مصروف',
        default => 'تكلفة مباعة',
    };
    $canViewJournalEntries = auth()->user()?->hasPermission('manage_journal_entries');
@endphp

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div>
            <h2 class="page-title"><i class="fas fa-sitemap"></i> {{ $account->code }} - {{ $account->name_ar ?: $account->name }}</h2>
            <p class="text-muted mt-2 mb-0">عرض تفصيلي للحساب داخل الشجرة، مع الأب، الفروع التابعة، والحركات المحاسبية المرتبطة به.</p>
        </div>
        <div class="list-actions-group">
            <a href="{{ route('chart_of_accounts') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right ms-1"></i> العودة إلى شجرة الحسابات
            </a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-wallet"></i></div>
                <div class="stat-value fs-5">{{ number_format((float) ($account->rolled_up_balance ?? $account->balance), 2) }}</div>
                <div class="stat-label">الرصيد الإجمالي ({{ $company->currency }})</div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-layer-group"></i></div>
                <div class="stat-value fs-5">{{ $accountTypeLabel }}</div>
                <div class="stat-label">النوع</div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-code-branch"></i></div>
                <div class="stat-value fs-5">{{ $account->children_count }}</div>
                <div class="stat-label">عدد الفروع المباشرة</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-book-open"></i></div>
                <div class="stat-value fs-5">{{ $account->journal_lines_count }}</div>
                <div class="stat-label">عدد الحركات المرتبطة</div>
            </div>
        </div>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-lg-5">
            <div class="list-card mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="mb-0">بيانات الحساب</h5>
                    <span class="badge bg-{{ $account->is_active ? 'success' : 'secondary' }}">{{ $account->is_active ? 'نشط' : 'غير نشط' }}</span>
                </div>

                <div class="row g-3">
                    <div class="col-md-6"><strong>الكود</strong><div class="text-muted mt-1">{{ $account->code }}</div></div>
                    <div class="col-md-6"><strong>الاسم</strong><div class="text-muted mt-1">{{ $account->name }}</div></div>
                    <div class="col-md-6"><strong>الاسم بالعربي</strong><div class="text-muted mt-1">{{ $account->name_ar ?: '-' }}</div></div>
                    <div class="col-md-6"><strong>النوع</strong><div class="text-muted mt-1">{{ $accountTypeLabel }}</div></div>
                    <div class="col-md-6"><strong>الوضع داخل الشجرة</strong><div class="text-muted mt-1">{{ $hierarchyLabel }}</div></div>
                    <div class="col-md-6"><strong>حساب نظام</strong><div class="text-muted mt-1">{{ $account->is_system ? 'نعم' : 'لا' }}</div></div>
                    <div class="col-md-6"><strong>قابل للدفع/التحصيل</strong><div class="text-muted mt-1">{{ $account->allows_direct_transactions ? 'نعم' : 'لا' }}</div></div>
                    <div class="col-12">
                        <strong>الحساب الأب</strong>
                        <div class="text-muted mt-1">
                            @if ($account->parent)
                                <a href="{{ route('chart_of_accounts.show', $account->parent) }}">{{ $account->parent->code }} - {{ $account->parent->name_ar ?: $account->parent->name }}</a>
                            @else
                                لا يوجد، هذا حساب جذري.
                            @endif
                        </div>
                    </div>
                    <div class="col-12">
                        <strong>المسار الهرمي</strong>
                        <div class="text-muted mt-1 d-flex flex-wrap gap-2 align-items-center">
                            @forelse ($ancestors as $ancestor)
                                <a href="{{ route('chart_of_accounts.show', $ancestor) }}" class="badge text-bg-light text-decoration-none">{{ $ancestor->code }} - {{ $ancestor->name_ar ?: $ancestor->name }}</a>
                            @empty
                                <span class="badge text-bg-light">جذر الشجرة</span>
                            @endforelse
                            <span class="badge text-bg-primary">{{ $account->code }} - {{ $account->name_ar ?: $account->name }}</span>
                        </div>
                    </div>
                    <div class="col-12"><strong>الوصف</strong><div class="text-muted mt-1">{{ $account->description ?: '-' }}</div></div>
                </div>
            </div>

            <div class="list-card">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">الحسابات الفرعية</h5>
                        <p class="text-muted mb-0">كل الحسابات التي ترث هذا الحساب مباشرة في الشجرة.</p>
                    </div>
                    <span class="badge text-bg-light">{{ $account->children_count }} فرع</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>الكود</th>
                                <th>الحساب</th>
                                <th>الرصيد</th>
                                <th>عرض</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($account->children as $child)
                                <tr>
                                    <td>{{ $child->code }}</td>
                                    <td>{{ $child->name_ar ?: $child->name }}</td>
                                    <td>{{ number_format((float) ($child->rolled_up_balance ?? $child->balance), 2) }} {{ $company->currency }}</td>
                                    <td>
                                        <a href="{{ route('chart_of_accounts.show', $child) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye ms-1"></i> عرض
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">هذا الحساب لا يملك فروعًا مباشرة، لذلك يعتبر حسابًا نهائيًا داخل هذا المستوى.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="list-card">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">آخر الحركات المحاسبية</h5>
                        <p class="text-muted mb-0">آخر البنود التي استُخدم فيها هذا الحساب داخل القيود المحاسبية.</p>
                    </div>
                    <span class="badge text-bg-light">{{ $recentJournalLines->count() }} سطر</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>رقم القيد</th>
                                <th>التاريخ</th>
                                <th>الوصف</th>
                                <th>مدين</th>
                                <th>دائن</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentJournalLines as $line)
                                <tr>
                                    <td>
                                        @if ($canViewJournalEntries && $line->journalEntry)
                                            <a href="{{ route('journal_entries.show', $line->journalEntry) }}">{{ $line->journalEntry->entry_number }}</a>
                                        @else
                                            {{ $line->journalEntry?->entry_number ?: '-' }}
                                        @endif
                                    </td>
                                    <td>{{ optional($line->journalEntry?->entry_date)->format('Y-m-d') ?: '-' }}</td>
                                    <td>{{ $line->description ?: ($line->journalEntry?->description ?: '-') }}</td>
                                    <td class="debit">{{ number_format((float) $line->debit, 2) }} {{ $company->currency }}</td>
                                    <td class="credit">{{ number_format((float) $line->credit, 2) }} {{ $company->currency }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">لا توجد حركات محاسبية مرتبطة بهذا الحساب حتى الآن.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
