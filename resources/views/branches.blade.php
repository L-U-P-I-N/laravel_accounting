@extends('layouts.app')

@section('title', 'الفروع')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-code-branch"></i> الفروع</h2>
        <p class="text-muted mt-2 mb-0">إدارة الفروع وربط الموظفين والمبيعات بها.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBranchModal">
        <i class="fas fa-plus ms-1"></i> إضافة فرع
    </button>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-code-branch"></i></div><div class="stat-value">{{ $branches->count() }}</div><div class="stat-label">إجمالي الفروع</div></div></div>
    <div class="col-md-4 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon green"><i class="fas fa-star"></i></div><div class="stat-value">{{ $branches->where('is_default', true)->count() }}</div><div class="stat-label">فروع افتراضية</div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon orange"><i class="fas fa-users"></i></div><div class="stat-value">{{ $branches->sum('employees_count') }}</div><div class="stat-label">إجمالي الموظفين المرتبطين</div></div></div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">قائمة الفروع</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>الكود</th>
                        <th>المدينة</th>
                        <th>الموظفون</th>
                        <th>المبيعات</th>
                        <th>الافتراضي</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td>{{ $branch->name }}</td>
                            <td>{{ $branch->code }}</td>
                            <td>{{ $branch->city ?: '-' }}</td>
                            <td>{{ $branch->employees_count }}</td>
                            <td>{{ $branch->invoices_count }}</td>
                            <td>{!! $branch->is_default ? '<span class="badge bg-success">افتراضي</span>' : '<span class="text-muted">-</span>' !!}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editBranchModal{{ $branch->id }}"><i class="fas fa-edit"></i></button>
                                <form method="POST" action="{{ route('branches.destroy', $branch) }}" class="d-inline" onsubmit="return confirm('سيتم حذف الفرع فقط إذا لم يكن مرتبطًا بأي موظف أو عملية بيع. هل تريد المتابعة؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center py-4">لا توجد فروع بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('partials.branch_modal', [
    'modalId' => 'createBranchModal',
    'modalTitle' => 'إضافة فرع',
    'action' => route('branches.store'),
    'branch' => null,
])

@foreach ($branches as $branch)
    @include('partials.branch_modal', [
        'modalId' => 'editBranchModal' . $branch->id,
        'modalTitle' => 'تعديل الفرع: ' . $branch->name,
        'action' => route('branches.update', $branch),
        'branch' => $branch,
    ])
@endforeach
@endsection

@push('scripts')
@if ($errors->any() && old('branch_modal'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById(@json(old('branch_modal')));

    if (modalElement) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
</script>
@endif
@endpush
