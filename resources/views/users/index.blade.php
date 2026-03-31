@extends('layouts.app')

@section('title', 'إدارة المستخدمين')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-user-shield"></i> إدارة فريق الشركة</h2>
        <p class="text-muted mt-2 mb-0">إضافة أعضاء الفريق وتحديد أدوارهم وصلاحياتهم بشكل مرن</p>
    </div>
    @if ($canManageUsers)
        <button type="button" class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="fas fa-user-plus ms-1"></i> إضافة مستخدم
        </button>
    @endif
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <div class="stat-value">{{ $users->count() }}</div>
            <div class="stat-label">إجمالي أعضاء الفريق</div>
        </div>
    </div>
    <div class="col-md-4 mb-3 mb-md-0">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
            <div class="stat-value">{{ $users->where('is_active', true)->count() }}</div>
            <div class="stat-label">المستخدمون النشطون</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-user-cog"></i></div>
            <div class="stat-value">{{ $roles->count() }}</div>
            <div class="stat-label">قوالب الأدوار الجاهزة</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">مستخدمو الشركة</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>البريد</th>
                        <th>الموظف / الفرع</th>
                        <th>الأدوار</th>
                        <th>صلاحيات إضافية</th>
                        <th>الحالة</th>
                        <th>آخر دخول</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $managedUser)
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $managedUser->full_name }}</div>
                                <small class="text-muted">{{ $managedUser->role_label }}</small>
                            </td>
                            <td>{{ $managedUser->email }}</td>
                            <td>
                                @if ($managedUser->employee)
                                    <div class="fw-semibold">{{ $managedUser->employee->full_name }}</div>
                                    <small class="text-muted">{{ $managedUser->employee->branch?->name ?? 'بدون فرع' }}</small>
                                @else
                                    <span class="text-muted">غير مرتبط</span>
                                @endif
                            </td>
                            <td>
                                @forelse ($managedUser->roles as $role)
                                    <span class="badge bg-primary-subtle text-primary border me-1">{{ $role->display_name }}</span>
                                @empty
                                    <span class="badge bg-secondary">بدون دور مخصص</span>
                                @endforelse
                            </td>
                            <td>{{ $managedUser->permissions->count() }}</td>
                            <td>
                                <span class="badge bg-{{ $managedUser->is_active ? 'success' : 'secondary' }}">{{ $managedUser->is_active ? 'نشط' : 'معطل' }}</span>
                                @if ($managedUser->must_change_password)
                                    <span class="badge bg-warning text-dark ms-1">بانتظار تغيير كلمة المرور</span>
                                @endif
                            </td>
                            <td>{{ $managedUser->last_login?->format('Y-m-d H:i') ?? 'لم يسجل دخول بعد' }}</td>
                            <td>
                                @if ($canManageUsers)
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editUserModal{{ $managedUser->id }}"
                                    >
                                        <i class="fas fa-edit"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4">لا يوجد مستخدمون ضمن هذه الشركة</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@php
    $groupTitles = [
        'team' => 'إدارة الفريق',
        'settings' => 'الإعدادات',
        'reports' => 'التقارير',
        'accounting' => 'المحاسبة',
        'sales' => 'المبيعات',
        'procurement' => 'المشتريات',
        'inventory' => 'المخزون',
        'hr' => 'الموارد البشرية',
    ];
@endphp

@if ($canManageUsers)
    @include('users.partials.form_modal', [
        'modalId' => 'createUserModal',
        'modalTitle' => 'إضافة مستخدم جديد',
        'action' => route('users.store'),
        'userModel' => null,
        'roles' => $roles,
        'permissions' => $permissions,
        'employees' => $employees,
        'groupTitles' => $groupTitles,
    ])
@endif

@if ($canManageUsers)
    @foreach ($users as $managedUser)
        @include('users.partials.form_modal', [
            'modalId' => 'editUserModal' . $managedUser->id,
            'modalTitle' => 'تعديل المستخدم: ' . $managedUser->full_name,
            'action' => route('users.update', $managedUser),
            'userModel' => $managedUser,
            'roles' => $roles,
            'permissions' => $permissions,
            'employees' => $employees,
            'groupTitles' => $groupTitles,
        ])
    @endforeach
@endif
@endsection

@push('scripts')
    @if ($errors->any() && old('user_modal'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalElement = document.getElementById(@json(old('user_modal')));

                if (!modalElement) {
                    return;
                }

                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            });
        </script>
    @endif
@endpush
