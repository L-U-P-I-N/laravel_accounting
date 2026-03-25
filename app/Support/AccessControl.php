<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;

class AccessControl
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_ACCOUNTANT = 'accountant';
    public const ROLE_HR = 'hr';
    public const ROLE_SALES = 'sales';
    public const ROLE_VIEWER = 'viewer';

    public static function permissions(): array
    {
        return [
            ['name' => 'manage_users', 'display_name' => 'إدارة المستخدمين', 'group' => 'team', 'description' => 'إنشاء المستخدمين وتعديلهم وتعطيلهم'],
            ['name' => 'manage_roles', 'display_name' => 'إدارة الأدوار', 'group' => 'team', 'description' => 'تعديل الأدوار والصلاحيات'],
            ['name' => 'manage_settings', 'display_name' => 'إدارة الإعدادات', 'group' => 'settings', 'description' => 'تعديل إعدادات الشركة'],
            ['name' => 'manage_subscription', 'display_name' => 'إدارة الاشتراك', 'group' => 'settings', 'description' => 'إدارة الباقة والفوترة'],
            ['name' => 'view_reports', 'display_name' => 'عرض التقارير', 'group' => 'reports', 'description' => 'الوصول إلى التقارير المالية'],
            ['name' => 'manage_accounts', 'display_name' => 'إدارة شجرة الحسابات', 'group' => 'accounting', 'description' => 'إدارة الحسابات المحاسبية'],
            ['name' => 'manage_journal_entries', 'display_name' => 'إدارة القيود', 'group' => 'accounting', 'description' => 'إنشاء وتعديل القيود المحاسبية'],
            ['name' => 'manage_invoices', 'display_name' => 'إدارة الفواتير', 'group' => 'sales', 'description' => 'إنشاء وتعديل فواتير المبيعات'],
            ['name' => 'manage_customers', 'display_name' => 'إدارة العملاء', 'group' => 'sales', 'description' => 'إدارة العملاء'],
            ['name' => 'manage_purchases', 'display_name' => 'إدارة المشتريات', 'group' => 'procurement', 'description' => 'إنشاء وتعديل المشتريات'],
            ['name' => 'manage_suppliers', 'display_name' => 'إدارة الموردين', 'group' => 'procurement', 'description' => 'إدارة الموردين'],
            ['name' => 'manage_products', 'display_name' => 'إدارة المنتجات', 'group' => 'inventory', 'description' => 'إدارة المنتجات والمخزون'],
            ['name' => 'manage_employees', 'display_name' => 'إدارة الموظفين', 'group' => 'hr', 'description' => 'إدارة ملفات الموظفين'],
            ['name' => 'manage_payroll', 'display_name' => 'إدارة الرواتب', 'group' => 'hr', 'description' => 'تشغيل الرواتب وإدارة التعويضات'],
        ];
    }

    public static function roles(): array
    {
        return [
            [
                'name' => self::ROLE_OWNER,
                'display_name' => 'مالك الشركة',
                'description' => 'وصول كامل إلى النظام وإدارة الفريق والاشتراك',
                'permissions' => array_column(self::permissions(), 'name'),
            ],
            [
                'name' => self::ROLE_ADMIN,
                'display_name' => 'مدير',
                'description' => 'إدارة التشغيل اليومي للشركة',
                'permissions' => [
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
            ],
            [
                'name' => self::ROLE_ACCOUNTANT,
                'display_name' => 'محاسب',
                'description' => 'إدارة العمليات المحاسبية والفواتير والتقارير',
                'permissions' => [
                    'view_reports',
                    'manage_accounts',
                    'manage_journal_entries',
                    'manage_invoices',
                    'manage_customers',
                    'manage_purchases',
                    'manage_suppliers',
                ],
            ],
            [
                'name' => self::ROLE_HR,
                'display_name' => 'موارد بشرية',
                'description' => 'إدارة الموظفين والرواتب',
                'permissions' => [
                    'manage_employees',
                    'manage_payroll',
                    'view_reports',
                ],
            ],
            [
                'name' => self::ROLE_SALES,
                'display_name' => 'مبيعات',
                'description' => 'إدارة العملاء والفواتير',
                'permissions' => [
                    'manage_invoices',
                    'manage_customers',
                    'view_reports',
                ],
            ],
            [
                'name' => self::ROLE_VIEWER,
                'display_name' => 'مشاهد',
                'description' => 'وصول للقراءة فقط',
                'permissions' => [
                    'view_reports',
                ],
            ],
        ];
    }

    public static function ensureSeeded(): void
    {
        $permissions = [];

        foreach (self::permissions() as $permissionDefinition) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionDefinition['name']],
                $permissionDefinition
            );

            $permissions[$permission->name] = $permission->id;
        }

        foreach (self::roles() as $roleDefinition) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleDefinition['name']],
                [
                    'display_name' => $roleDefinition['display_name'],
                    'description' => $roleDefinition['description'],
                ]
            );

            $role->permissions()->sync(
                collect($roleDefinition['permissions'])
                    ->map(fn (string $permissionName) => $permissions[$permissionName])
                    ->values()
                    ->all()
            );
        }
    }
}
