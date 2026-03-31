@extends('layouts.app')

@section('title', 'الموظفون')

@php
    $canManageEmployees = auth()->user()->hasPermission('manage_employees');
    $statusLabels = [
        'active' => 'نشط',
        'on_leave' => 'في إجازة',
        'terminated' => 'منتهي الخدمة',
    ];
    $employmentTypeLabels = [
        'full_time' => 'دوام كامل',
        'part_time' => 'دوام جزئي',
        'contract' => 'عقد',
        'temporary' => 'مؤقت',
    ];
@endphp

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-user-tie"></i> الموظفون</h2>
        <p class="text-muted mt-2 mb-0">كل موظف مرتبط بفرع واحد، ويمكن ربط المستخدمين بهم من شاشة إدارة المستخدمين.</p>
    </div>
    @if ($canManageEmployees)
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEmployeeModal">
            <i class="fas fa-plus ms-1"></i> إضافة موظف
        </button>
    @endif
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-value">{{ $employees->count() }}</div><div class="stat-label">إجمالي الموظفين</div></div></div>
    <div class="col-md-3 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon green"><i class="fas fa-user-check"></i></div><div class="stat-value">{{ $employees->where('status', 'active')->count() }}</div><div class="stat-label">نشطون</div></div></div>
    <div class="col-md-3 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon orange"><i class="fas fa-building"></i></div><div class="stat-value">{{ $branches->count() }}</div><div class="stat-label">الفروع المتاحة</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon red"><i class="fas fa-link"></i></div><div class="stat-value">{{ $employees->filter(fn ($employee) => $employee->users->isNotEmpty())->count() }}</div><div class="stat-label">مرتبطة بمستخدمين</div></div></div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">قائمة الموظفين</h5>
        <small class="text-muted">الربط الحالي بين الموظف والفرع والمستخدم</small>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>الرقم</th>
                        <th>الاسم</th>
                        <th>الفرع</th>
                        <th>المنصب / القسم</th>
                        <th>الراتب</th>
                        <th>المستخدم المرتبط</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>{{ $employee->employee_number }}</td>
                            <td>
                                <div class="fw-semibold">{{ $employee->full_name }}</div>
                                <small class="text-muted">{{ $employee->email ?: 'بدون بريد إلكتروني' }}</small>
                            </td>
                            <td>{{ $employee->branch?->name ?? 'بدون فرع' }}</td>
                            <td>
                                <div>{{ $employee->position ?: '-' }}</div>
                                <small class="text-muted">{{ $employee->department ?: ($employmentTypeLabels[$employee->employment_type] ?? '-') }}</small>
                            </td>
                            <td>{{ number_format((float) $employee->salary, 2) }} {{ $company->currency }}</td>
                            <td>
                                @if ($employee->users->isNotEmpty())
                                    {{ $employee->users->pluck('full_name')->join('، ') }}
                                @else
                                    <span class="text-muted">غير مرتبط</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $employee->status === 'active' ? 'success' : ($employee->status === 'on_leave' ? 'warning text-dark' : 'secondary') }}">
                                    {{ $statusLabels[$employee->status] ?? $employee->status }}
                                </span>
                            </td>
                            <td>
                                @if ($canManageEmployees)
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editEmployeeModal{{ $employee->id }}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" action="{{ route('employees.destroy', $employee) }}" class="d-inline" onsubmit="return confirm('سيتم حذف الموظف نهائيًا إذا لم يكن مرتبطًا بأي مستخدم أو عملية بيع. هل تريد المتابعة؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4">لا يوجد موظفون بعد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($canManageEmployees)
    @include('partials.employee_modal', [
        'modalId' => 'createEmployeeModal',
        'modalTitle' => 'إضافة موظف',
        'action' => route('employees.store'),
        'employee' => null,
        'branches' => $branches,
        'statusLabels' => $statusLabels,
        'employmentTypeLabels' => $employmentTypeLabels,
    ])

    @foreach ($employees as $employee)
        @include('partials.employee_modal', [
            'modalId' => 'editEmployeeModal' . $employee->id,
            'modalTitle' => 'تعديل الموظف: ' . $employee->full_name,
            'action' => route('employees.update', $employee),
            'employee' => $employee,
            'branches' => $branches,
            'statusLabels' => $statusLabels,
            'employmentTypeLabels' => $employmentTypeLabels,
        ])
    @endforeach
@endif
@endsection

@push('scripts')
@if ($errors->any() && old('employee_modal'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById(@json(old('employee_modal')));

    if (modalElement) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
</script>
@endif
@endpush
