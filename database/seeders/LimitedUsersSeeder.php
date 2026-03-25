<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LimitedUsersSeeder extends Seeder
{
    public function run(): void
    {
        AccessControl::ensureSeeded();

        $company = Company::query()->first();

        if (!$company) {
            $this->command?->warn('No company found. Run AdminSeeder first to create the sample company.');

            return;
        }

        $roles = Role::query()->pluck('id', 'name');
        $permissions = Permission::query()->pluck('id', 'name');

        $users = [
            [
                'email' => 'accountant@test.com',
                'first_name' => 'سارة',
                'last_name' => 'المحاسبة',
                'role' => AccessControl::ROLE_ACCOUNTANT,
                'role_ids' => [AccessControl::ROLE_ACCOUNTANT],
                'permission_ids' => [],
                'description' => 'محاسب بصلاحيات الحسابات والقيود والفواتير والعملاء والموردين والتقارير',
            ],
            [
                'email' => 'hr@test.com',
                'first_name' => 'نورة',
                'last_name' => 'الموارد',
                'role' => AccessControl::ROLE_HR,
                'role_ids' => [AccessControl::ROLE_HR],
                'permission_ids' => [],
                'description' => 'موظف موارد بشرية بصلاحيات الموظفين والرواتب والتقارير',
            ],
            [
                'email' => 'sales@test.com',
                'first_name' => 'خالد',
                'last_name' => 'المبيعات',
                'role' => AccessControl::ROLE_SALES,
                'role_ids' => [AccessControl::ROLE_SALES],
                'permission_ids' => [],
                'description' => 'موظف مبيعات بصلاحيات العملاء والفواتير والتقارير',
            ],
            [
                'email' => 'ops@test.com',
                'first_name' => 'ريم',
                'last_name' => 'التشغيل',
                'role' => AccessControl::ROLE_VIEWER,
                'role_ids' => [AccessControl::ROLE_VIEWER],
                'permission_ids' => ['manage_customers', 'manage_suppliers'],
                'description' => 'مستخدم محدود بصلاحيات مخصصة مباشرة من المالك لإدارة العملاء والموردين فقط',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => trim($userData['first_name'] . ' ' . $userData['last_name']),
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
                    'password' => Hash::make('password'),
                    'role' => $userData['role'],
                    'must_change_password' => true,
                    'company_id' => $company->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $roleIds = collect($userData['role_ids'])
                ->map(fn (string $roleName) => $roles[$roleName] ?? null)
                ->filter()
                ->values()
                ->all();

            $permissionIds = collect($userData['permission_ids'])
                ->map(fn (string $permissionName) => $permissions[$permissionName] ?? null)
                ->filter()
                ->values()
                ->all();

            $user->roles()->sync($roleIds);
            $user->permissions()->sync($permissionIds);

            $this->command?->info(sprintf('%s / password - %s', $userData['email'], $userData['description']));
        }
    }
}
