@extends('layouts.app')

@section('title', 'الموارد البشرية')

@php
    $canManageEmployees = auth()->user()->hasPermission('manage_employees');
    $canManagePayroll = auth()->user()->hasPermission('manage_payroll');
@endphp

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <h2 class="page-title"><i class="fas fa-users"></i> الموارد البشرية</h2>
        <div>
            @if ($canManageEmployees)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal"><i class="fas fa-plus ms-1"></i> إضافة موظف</button>
            @endif
            @if ($canManagePayroll)
                <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#payrollModal"><i class="fas fa-calculator ms-1"></i> تشغيل الرواتب</button>
            @endif
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-value">{{ $employees->count() }}</div><div class="stat-label">إجمالي الموظفين</div></div></div>
        <div class="col-md-3 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon green"><i class="fas fa-user-check"></i></div><div class="stat-value">{{ $employees->where('status', 'active')->count() }}</div><div class="stat-label">نشطون</div></div></div>
        <div class="col-md-3 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon orange"><i class="fas fa-plane-departure"></i></div><div class="stat-value">{{ $employees->where('status', 'on_leave')->count() }}</div><div class="stat-label">في إجازة</div></div></div>
        <div class="col-md-3"><div class="stat-card"><div class="stat-icon red"><i class="fas fa-user-times"></i></div><div class="stat-value">{{ $employees->where('status', 'terminated')->count() }}</div><div class="stat-label">منتهي الخدمة</div></div></div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">قائمة الموظفين</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>المنصب</th>
                            <th>القسم</th>
                            <th>الراتب</th>
                            <th>تاريخ التوظيف</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr>
                                <td>{{ $employee->first_name }} {{ $employee->last_name }}</td>
                                <td>{{ $employee->position ?: '-' }}</td>
                                <td>{{ $employee->department ?: '-' }}</td>
                                <td>{{ number_format((float) $employee->salary, 2) }} {{ $company->currency }}</td>
                                <td>{{ $employee->hire_date ?: '-' }}</td>
                                <td>
                                    @if ($employee->status === 'active')
                                        <span class="badge bg-success">نشط</span>
                                    @elseif ($employee->status === 'on_leave')
                                        <span class="badge bg-warning">في إجازة</span>
                                    @else
                                        <span class="badge bg-danger">منتهي الخدمة</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></button>
                                    @if ($canManageEmployees)
                                        <button type="button" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></button>
                                        <button type="button" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center">لا يوجد موظفين</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if ($canManageEmployees)
    <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">إضافة موظف جديد</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-info">تم نقل واجهة إدارة الموظفين، وسيتم ربط الحفظ الفعلي لاحقًا.</div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">الاسم الأول</label><input type="text" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">الاسم الأخير</label><input type="text" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">البريد الإلكتروني</label><input type="email" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">رقم الهاتف</label><input type="text" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">المنصب</label><input type="text" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">القسم</label><input type="text" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">الراتب الأساسي</label><input type="number" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">تاريخ التوظيف</label><input type="date" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button type="button" class="btn btn-primary" disabled>حفظ</button></div>
            </div>
        </div>
    </div>
@endif

@if ($canManagePayroll)
    <div class="modal fade" id="payrollModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">تشغيل الرواتب</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">الشهر</label><input type="month" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">تاريخ الدفع</label><input type="date" class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button><button type="button" class="btn btn-success" disabled>تشغيل الرواتب</button></div>
            </div>
        </div>
    </div>
@endif
@endsection
