@php
    $isEditing = (bool) $userModel;
    $activeModalId = old('user_modal');
    $useOldInput = $activeModalId === $modalId;
    $assignedRoleIds = $userModel?->roles->pluck('id')->all() ?? [];
    $assignedPermissionIds = $userModel?->permissions->pluck('id')->all() ?? [];
    $currentRoleIds = collect($useOldInput ? old('role_ids', $assignedRoleIds) : $assignedRoleIds)
        ->map(fn ($id) => (int) $id)
        ->all();
    $currentPermissionIds = collect($useOldInput ? old('permission_ids', $assignedPermissionIds) : $assignedPermissionIds)
        ->map(fn ($id) => (int) $id)
        ->all();
    $selectedRoles = $roles->whereIn('id', $currentRoleIds);
    $selectedPermissionsCount = count($currentPermissionIds);
    $employeeIdValue = $useOldInput ? old('employee_id', $userModel?->employee_id) : $userModel?->employee_id;
    $initials = $isEditing
        ? mb_strtoupper(mb_substr($userModel->first_name ?? '', 0, 1) . mb_substr($userModel->last_name ?? '', 0, 1))
        : 'NU';
    $errorFields = $useOldInput ? $errors->keys() : [];
    $securityFields = ['password', 'password_confirmation', 'must_change_password'];
    $roleFields = ['role_ids', 'role_ids.*'];
    $permissionFields = ['permission_ids', 'permission_ids.*'];
    $activeTab = 'profile';

    if (collect($errorFields)->contains(fn ($field) => in_array($field, $securityFields, true))) {
        $activeTab = 'security';
    } elseif (collect($errorFields)->contains(fn ($field) => in_array($field, $roleFields, true))) {
        $activeTab = 'roles';
    } elseif (collect($errorFields)->contains(fn ($field) => in_array($field, $permissionFields, true))) {
        $activeTab = 'permissions';
    }
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-lg-down modal-dialog-scrollable user-editor-dialog">
        <div class="modal-content user-editor-modal">
            <div class="modal-header user-editor-header">
                <div class="user-editor-heading">
                    <div class="user-editor-avatar">{{ $initials }}</div>
                    <div>
                        <div class="user-editor-eyebrow">{{ $isEditing ? 'تحديث بيانات عضو الفريق' : 'إضافة عضو جديد إلى الفريق' }}</div>
                        <h5 class="modal-title mb-1">{{ $modalTitle }}</h5>
                        <p class="user-editor-subtitle mb-0">
                            {{ $isEditing ? 'عدّل البيانات الأساسية، حالة الحساب، والأذونات من نافذة واحدة واضحة.' : 'أنشئ حسابًا جديدًا مع أدوار جاهزة وصلاحيات تفصيلية تناسب مسؤولياته.' }}
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ $action }}" class="user-editor-form" data-user-editor-form data-initial-dirty="{{ $useOldInput && $errors->any() ? 'true' : 'false' }}">
                @csrf
                @if ($userModel)
                    @method('PUT')
                @endif
                <input type="hidden" name="user_modal" value="{{ $modalId }}">
                <div class="modal-body user-editor-body">
                    <div class="user-editor-overview">
                        <div class="user-editor-overview-item">
                            <span class="user-editor-overview-label">الوضع</span>
                            <strong>{{ $isEditing ? 'تعديل مستخدم قائم' : 'إنشاء مستخدم جديد' }}</strong>
                        </div>
                        <div class="user-editor-overview-item">
                            <span class="user-editor-overview-label">الأدوار المحددة</span>
                            <strong>{{ $selectedRoles->count() }}</strong>
                        </div>
                        <div class="user-editor-overview-item">
                            <span class="user-editor-overview-label">الصلاحيات الإضافية</span>
                            <strong>{{ $selectedPermissionsCount }}</strong>
                        </div>
                        <div class="user-editor-overview-item">
                            <span class="user-editor-overview-label">حالة الحساب</span>
                            <strong>{{ ($useOldInput ? old('is_active', $userModel?->is_active ?? true) : ($userModel?->is_active ?? true)) ? 'نشط' : 'معطل' }}</strong>
                        </div>
                    </div>

                    <div class="user-editor-tabs-shell">
                        <div class="nav user-editor-tabs" role="tablist" aria-label="تبويبات إدارة المستخدم">
                            <button class="user-editor-tab {{ $activeTab === 'profile' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#{{ $modalId }}-profile" type="button" role="tab" aria-selected="{{ $activeTab === 'profile' ? 'true' : 'false' }}">
                                <i class="fas fa-id-card"></i>
                                <span>البيانات الأساسية</span>
                            </button>
                            <button class="user-editor-tab {{ $activeTab === 'security' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#{{ $modalId }}-security" type="button" role="tab" aria-selected="{{ $activeTab === 'security' ? 'true' : 'false' }}">
                                <i class="fas fa-lock"></i>
                                <span>الأمان والوصول</span>
                            </button>
                            <button class="user-editor-tab {{ $activeTab === 'roles' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#{{ $modalId }}-roles" type="button" role="tab" aria-selected="{{ $activeTab === 'roles' ? 'true' : 'false' }}">
                                <i class="fas fa-user-tag"></i>
                                <span>الأدوار</span>
                            </button>
                            <button class="user-editor-tab {{ $activeTab === 'permissions' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#{{ $modalId }}-permissions" type="button" role="tab" aria-selected="{{ $activeTab === 'permissions' ? 'true' : 'false' }}">
                                <i class="fas fa-sliders"></i>
                                <span>الصلاحيات</span>
                            </button>
                        </div>

                        <div class="tab-content user-editor-tab-content">
                            <div class="tab-pane fade {{ $activeTab === 'profile' ? 'show active' : '' }}" id="{{ $modalId }}-profile" role="tabpanel" tabindex="0">
                                <section class="user-editor-panel user-editor-panel-highlight">
                                    <div class="user-editor-panel-head">
                                        <div>
                                            <h6 class="mb-1">الملف الأساسي</h6>
                                            <p class="mb-0">المعلومات التعريفية التي تظهر داخل النظام وتحدد طريقة عرض المستخدم.</p>
                                        </div>
                                        <span class="user-editor-panel-icon"><i class="fas fa-id-badge"></i></span>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">الاسم الأول</label>
                                            <input type="text" name="first_name" class="form-control" value="{{ $useOldInput ? old('first_name', $userModel?->first_name ?? '') : ($userModel?->first_name ?? '') }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">الاسم الأخير</label>
                                            <input type="text" name="last_name" class="form-control" value="{{ $useOldInput ? old('last_name', $userModel?->last_name ?? '') : ($userModel?->last_name ?? '') }}" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">البريد الإلكتروني</label>
                                            <input type="email" name="email" class="form-control" value="{{ $useOldInput ? old('email', $userModel?->email ?? '') : ($userModel?->email ?? '') }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">اللغة</label>
                                            <select name="language" class="form-select">
                                                <option value="ar" @selected(($useOldInput ? old('language', $userModel?->language ?? 'ar') : ($userModel?->language ?? 'ar')) === 'ar')>العربية</option>
                                                <option value="en" @selected(($useOldInput ? old('language', $userModel?->language ?? 'ar') : ($userModel?->language ?? 'ar')) === 'en')>English</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">ربط بموظف</label>
                                            <select name="employee_id" class="form-select">
                                                <option value="">بدون ربط</option>
                                                @foreach ($employees as $employee)
                                                    <option value="{{ $employee->id }}" @selected((string) $employeeIdValue === (string) $employee->id)>
                                                        {{ $employee->full_name }}{{ $employee->branch?->name ? ' - ' . $employee->branch->name : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="user-editor-toggle-card">
                                                <div>
                                                    <div class="user-editor-toggle-title">تنشيط الحساب</div>
                                                    <div class="user-editor-toggle-text">يسمح له بالدخول واستخدام الشاشات المصرح بها.</div>
                                                </div>
                                                <div class="form-check form-switch m-0">
                                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive{{ $modalId }}" @checked($useOldInput ? old('is_active', $userModel?->is_active ?? true) : ($userModel?->is_active ?? true))>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <div class="tab-pane fade {{ $activeTab === 'security' ? 'show active' : '' }}" id="{{ $modalId }}-security" role="tabpanel" tabindex="0">
                                <section class="user-editor-panel">
                                    <div class="user-editor-panel-head">
                                        <div>
                                            <h6 class="mb-1">الوصول والأمان</h6>
                                            <p class="mb-0">اضبط كلمة المرور ومتطلبات أول تسجيل دخول وإعدادات الاعتماد.</p>
                                        </div>
                                        <span class="user-editor-panel-icon"><i class="fas fa-shield-halved"></i></span>
                                    </div>

                                    <div class="user-editor-security-grid">
                                        <div class="user-editor-toggle-card user-editor-toggle-card-wide">
                                            <div>
                                                <div class="user-editor-toggle-title">إلزام تغيير كلمة المرور</div>
                                                <div class="user-editor-toggle-text">مفيد عند إنشاء المستخدم من قبل الإدارة لضمان سرية كلمة المرور النهائية.</div>
                                            </div>
                                            <div class="form-check form-switch m-0">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="must_change_password"
                                                    value="1"
                                                    id="mustChangePassword{{ $modalId }}"
                                                    @checked($useOldInput ? old('must_change_password', $userModel?->must_change_password ?? true) : ($userModel?->must_change_password ?? true))
                                                >
                                            </div>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">{{ $userModel ? 'كلمة مرور جديدة' : 'كلمة المرور' }}</label>
                                                <input type="password" name="password" class="form-control" {{ $userModel ? '' : 'required' }}>
                                                @if ($userModel)
                                                    <small class="text-muted d-block mt-2">اترك الحقل فارغًا إذا لم تكن بحاجة إلى تغييرها الآن.</small>
                                                @endif
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">تأكيد كلمة المرور</label>
                                                <input type="password" name="password_confirmation" class="form-control" {{ $userModel ? '' : 'required' }}>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <div class="tab-pane fade {{ $activeTab === 'roles' ? 'show active' : '' }}" id="{{ $modalId }}-roles" role="tabpanel" tabindex="0">
                                <section class="user-editor-panel">
                                    <div class="user-editor-panel-head">
                                        <div>
                                            <h6 class="mb-1">الأدوار الجاهزة</h6>
                                            <p class="mb-0">اختر أدوارًا تمنح حزمة صلاحيات متوقعة بسرعة مع وصف واضح لكل دور.</p>
                                        </div>
                                        <span class="user-editor-panel-icon"><i class="fas fa-layer-group"></i></span>
                                    </div>

                                    <div class="user-editor-role-list">
                                        @foreach ($roles as $role)
                                            <label class="user-editor-choice-card user-editor-role-card" for="role{{ $modalId }}{{ $role->id }}">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="role_ids[]"
                                                    value="{{ $role->id }}"
                                                    id="role{{ $modalId }}{{ $role->id }}"
                                                    @checked(in_array($role->id, $currentRoleIds, true))
                                                >
                                                <span class="user-editor-choice-body">
                                                    <span class="user-editor-choice-title">{{ $role->display_name }}</span>
                                                    <span class="user-editor-choice-text">{{ $role->description }}</span>
                                                    <span class="user-editor-choice-meta">{{ $role->permissions->count() }} صلاحيات افتراضية</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </section>
                            </div>

                            <div class="tab-pane fade {{ $activeTab === 'permissions' ? 'show active' : '' }}" id="{{ $modalId }}-permissions" role="tabpanel" tabindex="0">
                                <section class="user-editor-panel">
                                    <div class="user-editor-panel-head">
                                        <div>
                                            <h6 class="mb-1">صلاحيات إضافية مباشرة</h6>
                                            <p class="mb-0">أضف استثناءات دقيقة فوق الأدوار بدون إطالة النموذج في شاشة واحدة.</p>
                                        </div>
                                        <span class="user-editor-panel-icon"><i class="fas fa-sliders"></i></span>
                                    </div>

                                    <div class="user-editor-permissions-wrap">
                                        @foreach ($permissions as $group => $groupPermissions)
                                            <div class="user-editor-permission-group">
                                                <div class="user-editor-group-title-row">
                                                    <h6 class="fw-bold mb-0">{{ $groupTitles[$group] ?? $group }}</h6>
                                                    <span class="user-editor-group-count">{{ $groupPermissions->count() }} صلاحيات</span>
                                                </div>
                                                <div class="row g-3 mt-1">
                                                    @foreach ($groupPermissions as $permission)
                                                        <div class="col-md-6">
                                                            <label class="user-editor-choice-card user-editor-permission-card" for="permission{{ $modalId }}{{ $permission->id }}">
                                                                <input
                                                                    class="form-check-input"
                                                                    type="checkbox"
                                                                    name="permission_ids[]"
                                                                    value="{{ $permission->id }}"
                                                                    id="permission{{ $modalId }}{{ $permission->id }}"
                                                                    @checked(in_array($permission->id, $currentPermissionIds, true))
                                                                >
                                                                <span class="user-editor-choice-body">
                                                                    <span class="user-editor-choice-title">{{ $permission->display_name }}</span>
                                                                    <span class="user-editor-choice-text">{{ $permission->description }}</span>
                                                                </span>
                                                            </label>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </section>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer user-editor-footer">
                    <div class="user-editor-footer-note">
                        <i class="fas fa-circle-info"></i>
                        يتم تطبيق الأدوار أولًا ثم تُضاف الصلاحيات المباشرة فوقها.
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light user-editor-cancel" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary user-editor-submit">{{ $userModel ? 'حفظ التعديلات' : 'إنشاء المستخدم' }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
