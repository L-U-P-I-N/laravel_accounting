<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Support\AccessControl;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'language',
        'is_active',
        'must_change_password',
        'company_id',
        'employee_id',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function requiresPasswordChange(): bool
    {
        return $this->must_change_password;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(AccessControl::ROLE_OWNER) || $this->hasRole(AccessControl::ROLE_ADMIN)
            || in_array($this->role, ['admin', 'owner'], true);
    }

    public function isAccountant(): bool
    {
        return $this->hasRole(AccessControl::ROLE_ACCOUNTANT) || $this->role === 'accountant';
    }

    public function canAccessCompany($companyId): bool
    {
        return $this->company_id === $companyId || $this->isAdmin();
    }

    public function hasRole(string $roleName): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn (Role $role) => $role->name === $roleName);
        }

        return $this->roles()->where('name', $roleName)->exists();
    }

    public function hasPermission(string $permissionName): bool
    {
        if ($this->hasRole(AccessControl::ROLE_OWNER)) {
            return true;
        }

        if ($this->hasDirectPermission($permissionName) || $this->hasPermissionViaRole($permissionName)) {
            return true;
        }

        return in_array($permissionName, $this->legacyPermissions(), true);
    }

    public function getRoleLabelAttribute(): string
    {
        $roles = $this->relationLoaded('roles') ? $this->roles : $this->roles()->get();

        if ($roles->isNotEmpty()) {
            return $roles->pluck('display_name')->join('، ');
        }

        return match ($this->role) {
            'owner' => 'مالك الشركة',
            'admin' => 'مدير',
            'accountant' => 'محاسب',
            'hr' => 'موارد بشرية',
            'sales' => 'مبيعات',
            'viewer' => 'مشاهد',
            default => 'مستخدم',
        };
    }

    private function hasDirectPermission(string $permissionName): bool
    {
        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains(fn (Permission $permission) => $permission->name === $permissionName);
        }

        return $this->permissions()->where('name', $permissionName)->exists();
    }

    private function hasPermissionViaRole(string $permissionName): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(function (Role $role) use ($permissionName) {
                $permissions = $role->relationLoaded('permissions') ? $role->permissions : $role->permissions()->get();

                return $permissions->contains(fn (Permission $permission) => $permission->name === $permissionName);
            });
        }

        return $this->roles()->whereHas('permissions', fn ($query) => $query->where('name', $permissionName))->exists();
    }

    private function legacyPermissions(): array
    {
        return match ($this->role) {
            'owner' => array_column(AccessControl::permissions(), 'name'),
            'admin' => [
                'manage_users',
                'view_reports',
                'manage_settings',
                'manage_accounts',
                'manage_journal_entries',
                'manage_invoices',
                'manage_customers',
                'manage_purchases',
                'manage_suppliers',
                'manage_products',
                'manage_employees',
                'manage_payroll',
            ],
            'accountant' => [
                'view_reports',
                'manage_accounts',
                'manage_journal_entries',
                'manage_invoices',
                'manage_customers',
                'manage_purchases',
                'manage_suppliers',
            ],
            'hr' => ['manage_employees', 'manage_payroll', 'view_reports'],
            'sales' => ['manage_invoices', 'manage_customers', 'view_reports'],
            'viewer' => ['view_reports'],
            default => [],
        };
    }
}
