@php
    $isEditingEmployee = (bool) $employee;
    $activeEmployeeModalId = old('employee_modal');
    $useOldEmployeeInput = $activeEmployeeModalId === $modalId;
    $addressValue = $useOldEmployeeInput ? old('address', $employee?->address) : $employee?->address;
    $branchValue = $useOldEmployeeInput ? old('branch_id', $employee?->branch_id) : $employee?->branch_id;
    $departmentValue = $useOldEmployeeInput ? old('department', $employee?->department) : $employee?->department;
    $emailValue = $useOldEmployeeInput ? old('email', $employee?->email) : $employee?->email;
    $employmentTypeValue = $useOldEmployeeInput ? old('employment_type', $employee?->employment_type ?? 'full_time') : ($employee?->employment_type ?? 'full_time');
    $firstNameValue = $useOldEmployeeInput ? old('first_name', $employee?->first_name) : $employee?->first_name;
    $hireDateValue = $useOldEmployeeInput ? old('hire_date', optional($employee?->hire_date)->format('Y-m-d')) : optional($employee?->hire_date)->format('Y-m-d');
    $lastNameValue = $useOldEmployeeInput ? old('last_name', $employee?->last_name) : $employee?->last_name;
    $notesValue = $useOldEmployeeInput ? old('notes', $employee?->notes) : $employee?->notes;
    $phoneValue = $useOldEmployeeInput ? old('phone', $employee?->phone) : $employee?->phone;
    $positionValue = $useOldEmployeeInput ? old('position', $employee?->position) : $employee?->position;
    $salaryValue = $useOldEmployeeInput ? old('salary', $employee?->salary ?? 0) : ($employee?->salary ?? 0);
    $statusValue = $useOldEmployeeInput ? old('status', $employee?->status ?? 'active') : ($employee?->status ?? 'active');
    $terminationDateValue = $useOldEmployeeInput ? old('termination_date', optional($employee?->termination_date)->format('Y-m-d')) : optional($employee?->termination_date)->format('Y-m-d');
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}">
                @csrf
                @if ($isEditingEmployee)
                    @method('PUT')
                @endif
                <input type="hidden" name="employee_modal" value="{{ $modalId }}">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $modalTitle }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">الاسم الأول</label>
                            <input type="text" name="first_name" class="form-control" value="{{ $firstNameValue }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الاسم الأخير</label>
                            <input type="text" name="last_name" class="form-control" value="{{ $lastNameValue }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الفرع</label>
                            <select name="branch_id" class="form-select" required>
                                <option value="">اختر الفرع</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) $branchValue === (string) $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الحالة</label>
                            <select name="status" class="form-select" required>
                                @foreach ($statusLabels as $value => $label)
                                    <option value="{{ $value }}" @selected($statusValue === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control" value="{{ $emailValue }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">الهاتف</label>
                            <input type="text" name="phone" class="form-control" value="{{ $phoneValue }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">المنصب</label>
                            <input type="text" name="position" class="form-control" value="{{ $positionValue }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">القسم</label>
                            <input type="text" name="department" class="form-control" value="{{ $departmentValue }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">نوع التوظيف</label>
                            <select name="employment_type" class="form-select">
                                @foreach ($employmentTypeLabels as $value => $label)
                                    <option value="{{ $value }}" @selected($employmentTypeValue === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">الراتب</label>
                            <input type="number" name="salary" class="form-control" min="0" step="0.01" value="{{ $salaryValue }}" lang="en" dir="ltr">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">تاريخ التوظيف</label>
                            <input type="date" name="hire_date" class="form-control" value="{{ $hireDateValue }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">تاريخ إنهاء الخدمة</label>
                            <input type="date" name="termination_date" class="form-control" value="{{ $terminationDateValue }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">العنوان</label>
                            <input type="text" name="address" class="form-control" value="{{ $addressValue }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="3">{{ $notesValue }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">{{ $isEditingEmployee ? 'حفظ التعديلات' : 'إضافة الموظف' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
