@extends('layouts.app')

@section('title', 'القيود المحاسبية')

@php
    $canManageJournalEntries = auth()->user()->hasPermission('manage_journal_entries');
@endphp

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-book"></i> القيود المحاسبية</h2>
        <p class="text-muted mt-2 mb-0">إدارة القيود اليومية</p>
    </div>
    @if ($canManageJournalEntries)
        <a href="{{ route('journal_entries.create') }}" class="btn btn-gradient">
            <i class="fas fa-plus ms-1"></i> إنشاء قيد جديد
        </a>
    @endif
</div>

<div class="search-box">
    <div class="row">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" placeholder="البحث عن قيد..." id="searchInput">
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0"><select class="form-select"><option>جميع القيود</option><option>مسودة</option><option>مرحلة</option><option>مستعادة</option></select></div>
        <div class="col-md-3 mb-3 mb-md-0"><input type="date" class="form-control"></div>
        <div class="col-md-2"><input type="date" class="form-control"></div>
    </div>
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

@push('scripts')
<script>
document.getElementById('searchInput')?.addEventListener('keyup', function () {
    const searchTerm = this.value.toLowerCase();
    document.querySelectorAll('.journal-card').forEach((card) => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(searchTerm) ? 'block' : 'none';
    });
});
</script>
@endpush
