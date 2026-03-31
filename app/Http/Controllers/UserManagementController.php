<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        AccessControl::ensureSeeded();

        $company = $request->user()->company;
        $users = User::query()
            ->with(['roles', 'permissions', 'employee.branch'])
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->get();
        $employees = Employee::query()
            ->with('branch')
            ->where('company_id', $company->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $roles = Role::query()->with('permissions')->orderBy('id')->get();
        $permissions = Permission::query()->orderBy('group')->orderBy('id')->get()->groupBy('group');
        $canManageUsers = $request->user()->hasPermission('manage_users');

        return view('users.index', compact('company', 'users', 'roles', 'permissions', 'employees', 'canManageUsers'));
    }

    public function store(Request $request): RedirectResponse
    {
        AccessControl::ensureSeeded();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'language' => ['nullable', 'string', 'max:5'],
            'is_active' => ['nullable', 'boolean'],
            'must_change_password' => ['nullable', 'boolean'],
            'employee_id' => [
                'nullable',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('company_id', $request->user()->company_id)),
                Rule::unique('users', 'employee_id'),
            ],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $roleIds = collect($validated['role_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $permissionIds = collect($validated['permission_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        if ($roleIds->isEmpty() && $permissionIds->isEmpty()) {
            return back()->withErrors(['role_ids' => 'اختر دورًا واحدًا على الأقل أو صلاحية واحدة على الأقل'])->withInput();
        }

        $user = User::create([
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $this->primaryRoleName($roleIds),
            'language' => $validated['language'] ?? 'ar',
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'must_change_password' => $request->boolean('must_change_password', true),
            'company_id' => $request->user()->company_id,
            'employee_id' => $validated['employee_id'] ?? null,
        ]);

        $user->roles()->sync($roleIds->all());
        $user->permissions()->sync($permissionIds->all());

        return redirect()->route('users.index')->with('success', 'تمت إضافة المستخدم بنجاح');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        AccessControl::ensureSeeded();

        abort_if((int) $user->company_id !== (int) $request->user()->company_id, 404);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'language' => ['nullable', 'string', 'max:5'],
            'is_active' => ['nullable', 'boolean'],
            'must_change_password' => ['nullable', 'boolean'],
            'employee_id' => [
                'nullable',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('company_id', $request->user()->company_id)),
                Rule::unique('users', 'employee_id')->ignore($user->id),
            ],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $roleIds = collect($validated['role_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $permissionIds = collect($validated['permission_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        if ($roleIds->isEmpty() && $permissionIds->isEmpty()) {
            return back()->withErrors(['role_ids' => 'اختر دورًا واحدًا على الأقل أو صلاحية واحدة على الأقل'])->withInput();
        }

        $ownerRole = Role::query()->where('name', AccessControl::ROLE_OWNER)->first();
        $isRemovingOwner = $ownerRole && $user->roles->contains('id', $ownerRole->id) && !$roleIds->contains($ownerRole->id);

        if ($isRemovingOwner && !$this->companyHasAnotherOwner($request->user()->company_id, $user->id)) {
            return back()->withErrors(['role_ids' => 'لا يمكن إزالة آخر مالك للشركة'])->withInput();
        }

        $payload = [
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'role' => $this->primaryRoleName($roleIds),
            'language' => $validated['language'] ?? $user->language,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'must_change_password' => $request->boolean('must_change_password'),
            'employee_id' => $validated['employee_id'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);
        $user->roles()->sync($roleIds->all());
        $user->permissions()->sync($permissionIds->all());

        return redirect()->route('users.index')->with('success', 'تم تحديث المستخدم بنجاح');
    }

    private function primaryRoleName($roleIds): string
    {
        $role = Role::query()->find($roleIds instanceof \Illuminate\Support\Collection ? $roleIds->first() : null);

        return $role?->name ?? 'user';
    }

    private function companyHasAnotherOwner(int $companyId, int $ignoredUserId): bool
    {
        $ownerRole = Role::query()->where('name', AccessControl::ROLE_OWNER)->first();

        if (!$ownerRole) {
            return false;
        }

        return User::query()
            ->where('company_id', $companyId)
            ->where('id', '!=', $ignoredUserId)
            ->whereHas('roles', fn ($query) => $query->where('roles.id', $ownerRole->id))
            ->exists();
    }
}
