@extends('layouts.app')

@section('title', 'إنشاء قيد محاسبي')

@php
    $canManageJournalEntries = auth()->user()->hasPermission('manage_journal_entries');
@endphp

@push('styles')
<style>
.journal-form {
    background: #fff;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

.journal-form .form-control,
.journal-form .form-select {
    border-radius: 10px;
    padding: 12px;
    border: 2px solid #e0e0e0;
}

.btn-save {
    background: linear-gradient(45deg, #667eea, #764ba2);
    border: none;
    border-radius: 25px;
    padding: 12px 30px;
    color: #fff;
    font-weight: 700;
}

.journal-line,
.balance-info {
    border-radius: 12px;
    padding: 20px;
}

.journal-line {
    background: #f8f9fa;
}

.line-row {
    background: #fff;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 10px;
    border: 1px solid #e0e0e0;
}

.balance-info {
    background: linear-gradient(45deg, #e8f5e8, #f0f8f0);
    margin-top: 20px;
}

.debit {
    color: #dc3545;
    font-weight: 700;
}

.credit {
    color: #28a745;
    font-weight: 700;
}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-book-medical"></i> إنشاء قيد محاسبي جديد</h2>
        <p class="text-muted mt-2 mb-0">إضافة قيد يومي يدوي بنفس هيكلة صفحة Flask الأصلية</p>
    </div>
    <a href="{{ route('journal_entries') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-right ms-2"></i>العودة للقيود
    </a>
</div>

<div class="journal-form">
    @php
        $manualLines = collect(old('line_account', ['', '']))->map(function ($accountId, $index) {
            return (object) [
                'account_id' => $accountId,
                'description' => old('line_description.' . $index),
                'debit' => old('line_debit.' . $index, 0),
                'credit' => old('line_credit.' . $index, 0),
            ];
        });
    @endphp
    <form method="POST" action="{{ route('journal_entries.store') }}" data-journal-form>
        @csrf
        <div class="row mb-4">
            <div class="col-md-3 mb-3 mb-md-0">
                <label class="form-label">رقم القيد</label>
                <input type="text" class="form-control" value="{{ $nextEntryNumber }}" readonly>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <label class="form-label">التاريخ *</label>
                <input type="date" name="entry_date" class="form-control" value="{{ old('entry_date', now()->format('Y-m-d')) }}" required>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <label class="form-label">المرجع</label>
                <input type="text" name="reference" class="form-control" placeholder="رقم المستند أو المرجع" value="{{ old('reference', $suggestedJournalReference) }}">
                <small class="text-muted d-block mt-2">يتم توليد المرجع تلقائيًا ويمكنك تعديله إذا لزم.</small>
            </div>
            <div class="col-md-3">
                <label class="form-label">الوصف *</label>
                <input type="text" name="description" class="form-control" placeholder="وصف القيد" value="{{ old('description') }}" required>
            </div>
        </div>

        <div class="journal-line">
            <h5 class="mb-3"><i class="fas fa-list ms-2 text-primary"></i>بنود القيد <small class="text-muted me-2">(يجب أن يتساوى المدين والدائن)</small></h5>

            <div id="linesContainer">
                @foreach ($manualLines as $line)
                    <div class="line-row" data-journal-line-row>
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label class="form-label">الحساب *</label>
                                <select name="line_account[]" class="form-select">
                                    <option value="">اختر الحساب</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}" {{ (string) $line->account_id === (string) $account->id ? 'selected' : '' }}>{{ $account->code }} - {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label class="form-label">الوصف</label>
                                <input type="text" name="line_description[]" class="form-control" placeholder="وصف البند" value="{{ $line->description }}">
                            </div>
                            <div class="col-md-2 mb-3 mb-md-0">
                                <label class="form-label">مدين</label>
                                <input type="number" name="line_debit[]" class="form-control line-debit" value="{{ $line->debit }}" min="0" step="0.01" lang="en" dir="ltr">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">دائن</label>
                                <input type="number" name="line_credit[]" class="form-control line-credit" value="{{ $line->credit }}" min="0" step="0.01" lang="en" dir="ltr">
                                <button type="button" class="btn btn-sm btn-outline-danger mt-2 remove-line w-100"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-3">
                @if ($canManageJournalEntries)
                    <button type="button" class="btn btn-outline-primary" id="addJournalLine">
                        <i class="fas fa-plus ms-2"></i>إضافة بند جديد
                    </button>
                @endif
            </div>
        </div>

        <div class="balance-info">
            <div class="row">
                <div class="col-md-4 mb-3 mb-md-0">
                    <label class="form-label">إجمالي المدين</label>
                    <h4 id="totalDebit" class="debit">0.00 {{ $company->currency }}</h4>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <label class="form-label">إجمالي الدائن</label>
                    <h4 id="totalCredit" class="credit">0.00 {{ $company->currency }}</h4>
                </div>
                <div class="col-md-4">
                    <label class="form-label">الرصيد</label>
                    <h4 id="balanceStatus" class="text-success">0.00 {{ $company->currency }}</h4>
                </div>
            </div>
            <div id="balanceWarning" class="alert alert-warning mt-3 d-none">
                <i class="fas fa-exclamation-triangle ms-2"></i>القيد غير متوازن. يجب أن يتساوى إجمالي المدين والدائن.
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="d-flex gap-2 flex-wrap">
                    @if ($canManageJournalEntries)
                        <button type="submit" class="btn btn-save">
                            <i class="fas fa-save ms-2"></i>حفظ القيد
                        </button>
                    @endif
                    <a href="{{ route('journal_entries') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times ms-2"></i>إلغاء
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function recalculateJournalBalance(form) {
    let totalDebit = 0;
    let totalCredit = 0;

    form.querySelectorAll('.line-debit').forEach((input) => {
        totalDebit += Number(input.value || 0);
    });

    form.querySelectorAll('.line-credit').forEach((input) => {
        totalCredit += Number(input.value || 0);
    });

    const balance = totalDebit - totalCredit;
    form.querySelector('#totalDebit').textContent = `${totalDebit.toFixed(2)} {{ $company->currency }}`;
    form.querySelector('#totalCredit').textContent = `${totalCredit.toFixed(2)} {{ $company->currency }}`;

    const balanceStatus = form.querySelector('#balanceStatus');
    balanceStatus.textContent = `${Math.abs(balance).toFixed(2)} {{ $company->currency }}`;
    balanceStatus.className = balance === 0 ? 'text-success' : 'text-danger';

    form.querySelector('#balanceWarning').classList.toggle('d-none', balance === 0);
}

function bindJournalLine(row, form) {
    row.querySelectorAll('.line-debit, .line-credit').forEach((input) => {
        input.addEventListener('input', () => recalculateJournalBalance(form));
    });

    row.querySelector('.remove-line')?.addEventListener('click', () => {
        if (form.querySelectorAll('[data-journal-line-row]').length > 2) {
            row.remove();
            recalculateJournalBalance(form);
        }
    });
}

function addJournalLine(form) {
    const firstRow = form.querySelector('[data-journal-line-row]');
    const container = form.querySelector('#linesContainer');
    if (!firstRow || !container) {
        return;
    }

    const clone = firstRow.cloneNode(true);
    clone.querySelectorAll('input').forEach((input) => {
        input.value = input.classList.contains('line-debit') || input.classList.contains('line-credit') ? '0' : '';
    });
    clone.querySelectorAll('select').forEach((select) => {
        select.selectedIndex = 0;
    });

    container.appendChild(clone);
    bindJournalLine(clone, form);
    recalculateJournalBalance(form);
}

document.querySelectorAll('[data-journal-form]').forEach((form) => {
    form.querySelectorAll('[data-journal-line-row]').forEach((row) => bindJournalLine(row, form));
    form.querySelector('#addJournalLine')?.addEventListener('click', () => addJournalLine(form));
    recalculateJournalBalance(form);
});
</script>
@endpush
