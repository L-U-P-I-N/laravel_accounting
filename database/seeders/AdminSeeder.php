<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\User;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Employee;
use App\Models\Role;
use App\Support\AccessControl;
use App\Support\ChartOfAccountsSynchronizer;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        AccessControl::ensureSeeded();

        // Create or update the sample company so the seeder can be rerun safely.
        $company = Company::query()->updateOrCreate([
            'email' => 'info@advanced-tech.com',
        ], [
            'name' => 'شركة التقنية المتقدمة',
            'email' => 'info@advanced-tech.com',
            'phone' => '+966501234567',
            'address' => 'الرياض، المملكة العربية السعودية',
            'city' => 'الرياض',
            'country_code' => 'SA',
            'currency' => 'SAR',
            'tax_number' => '300123456700003',
            'subscription_plan' => 'professional',
            'subscription_status' => 'active',
            'subscription_start' => now(),
            'subscription_end' => now()->addMonth(),
        ]);

        // Create or update the sample owner account.
        $admin = User::query()->updateOrCreate([
            'email' => 'admin@test.com',
        ], [
            'name' => 'أحمد محمد',
            'first_name' => 'أحمد',
            'last_name' => 'محمد',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'must_change_password' => false,
            'company_id' => $company->id,
            'email_verified_at' => now(),
        ]);

        // Create or update the basic read-only user.
        $user = User::query()->updateOrCreate([
            'email' => 'user@test.com',
        ], [
            'name' => 'محمد علي',
            'first_name' => 'محمد',
            'last_name' => 'علي',
            'email' => 'user@test.com',
            'password' => Hash::make('password'),
            'role' => 'viewer',
            'must_change_password' => true,
            'company_id' => $company->id,
            'email_verified_at' => now(),
        ]);

        $ownerRole = Role::query()->where('name', AccessControl::ROLE_OWNER)->first();
        $viewerRole = Role::query()->where('name', AccessControl::ROLE_VIEWER)->first();

        if ($ownerRole) {
            $admin->roles()->sync([$ownerRole->id]);
        }

        if ($viewerRole) {
            $user->roles()->sync([$viewerRole->id]);
        }

        app(ChartOfAccountsSynchronizer::class)->ensureBaseAccounts($company);

        // Create sample customers
        $customers = [
            ['name' => 'شركة الأمل للتجارة', 'email' => 'info@amal.com', 'phone' => '+966501234570'],
            ['name' => 'مؤسسة النور', 'email' => 'info@noor.com', 'phone' => '+966501234571'],
            ['name' => 'شركة الرائد', 'email' => 'info@raed.com', 'phone' => '+966501234572'],
        ];

        $customerIds = [];

        foreach ($customers as $customerData) {
            $customer = Customer::query()->updateOrCreate([
                'company_id' => $company->id,
                'email' => $customerData['email'],
            ], [
                'name' => $customerData['name'],
                'phone' => $customerData['phone'],
                'address' => 'الرياض، المملكة العربية السعودية',
                'city' => 'الرياض',
                'country' => 'SA',
                'is_active' => true,
            ]);

            $customerIds[] = $customer->id;
        }

        // Create sample suppliers
        $suppliers = [
            ['name' => 'مورد التقنية', 'email' => 'supply@tech.com', 'phone' => '+966501234580'],
            ['name' => 'شركة المواد الخام', 'email' => 'supply@raw.com', 'phone' => '+966501234581'],
            ['name' => 'مورد الخدمات', 'email' => 'supply@service.com', 'phone' => '+966501234582'],
        ];

        $supplierIds = [];

        foreach ($suppliers as $supplierData) {
            $supplier = Supplier::query()->updateOrCreate([
                'company_id' => $company->id,
                'email' => $supplierData['email'],
            ], [
                'name' => $supplierData['name'],
                'phone' => $supplierData['phone'],
                'address' => 'جدة، المملكة العربية السعودية',
                'city' => 'جدة',
                'country' => 'SA',
                'is_active' => true,
            ]);

            $supplierIds[] = $supplier->id;
        }

        // Create sample invoices
        $invoices = [
            ['invoice_number' => 'INV-2024-001', 'total' => 15000.00, 'status' => 'paid'],
            ['invoice_number' => 'INV-2024-002', 'total' => 8500.00, 'status' => 'partial'],
            ['invoice_number' => 'INV-2024-003', 'total' => 12300.00, 'status' => 'sent'],
        ];

        foreach ($invoices as $index => $invoiceData) {
            Invoice::query()->updateOrCreate([
                'company_id' => $company->id,
                'invoice_number' => $invoiceData['invoice_number'],
            ], [
                'customer_id' => $customerIds[$index] ?? Customer::query()->where('company_id', $company->id)->value('id'),
                'invoice_date' => now()->subDays($index * 5),
                'due_date' => now()->addDays(30 - $index * 5),
                'subtotal' => $invoiceData['total'] * 0.85,
                'tax_amount' => $invoiceData['total'] * 0.15,
                'total' => $invoiceData['total'],
                'paid_amount' => $invoiceData['status'] === 'paid' ? $invoiceData['total'] : ($invoiceData['status'] === 'partial' ? $invoiceData['total'] * 0.5 : 0),
                'balance_due' => $invoiceData['status'] === 'paid' ? 0 : ($invoiceData['status'] === 'partial' ? $invoiceData['total'] * 0.5 : $invoiceData['total']),
                'status' => $invoiceData['status'],
                'payment_status' => $invoiceData['status'] === 'paid' ? 'paid' : ($invoiceData['status'] === 'partial' ? 'partial' : 'pending'),
                'currency' => 'SAR',
                'exchange_rate' => 1.0000,
            ]);
        }

        // Create sample purchases
        $purchases = [
            ['purchase_number' => 'PUR-2024-001', 'total' => 8000.00, 'status' => 'approved'],
            ['purchase_number' => 'PUR-2024-002', 'total' => 5200.00, 'status' => 'partial'],
            ['purchase_number' => 'PUR-2024-003', 'total' => 9800.00, 'status' => 'paid'],
        ];

        foreach ($purchases as $index => $purchaseData) {
            Purchase::query()->updateOrCreate([
                'company_id' => $company->id,
                'purchase_number' => $purchaseData['purchase_number'],
            ], [
                'supplier_id' => $supplierIds[$index] ?? Supplier::query()->where('company_id', $company->id)->value('id'),
                'purchase_date' => now()->subDays($index * 3),
                'due_date' => now()->addDays(15 - $index * 3),
                'subtotal' => $purchaseData['total'] * 0.85,
                'tax_amount' => $purchaseData['total'] * 0.15,
                'total' => $purchaseData['total'],
                'paid_amount' => $purchaseData['status'] === 'paid' ? $purchaseData['total'] : ($purchaseData['status'] === 'partial' ? $purchaseData['total'] * 0.3 : 0),
                'balance_due' => $purchaseData['status'] === 'paid' ? 0 : ($purchaseData['status'] === 'partial' ? $purchaseData['total'] * 0.7 : $purchaseData['total']),
                'status' => $purchaseData['status'],
                'payment_status' => $purchaseData['status'] === 'paid' ? 'paid' : ($purchaseData['status'] === 'partial' ? 'partial' : 'pending'),
                'currency' => 'SAR',
                'exchange_rate' => 1.0000,
            ]);
        }

        // Create sample employees
        $employees = [
            ['first_name' => 'محمد', 'last_name' => 'الأحمد', 'position' => 'مدير مالي', 'department' => 'المالية', 'salary' => 15000.00],
            ['first_name' => 'فاطمة', 'last_name' => 'العلي', 'position' => 'محاسب', 'department' => 'المالية', 'salary' => 8000.00],
            ['first_name' => 'علي', 'last_name' => 'المحمد', 'position' => 'مدير موارد بشرية', 'department' => 'الموارد البشرية', 'salary' => 12000.00],
        ];

        foreach ($employees as $index => $employeeData) {
            Employee::query()->updateOrCreate([
                'employee_number' => 'EMP-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'company_id' => $company->id,
            ], [
                'first_name' => $employeeData['first_name'],
                'last_name' => $employeeData['last_name'],
                'email' => strtolower($employeeData['first_name'] . '.' . $employeeData['last_name']) . '@company.com',
                'phone' => '+96650123460' . ($index + 1),
                'address' => 'الرياض، المملكة العربية السعودية',
                'hire_date' => now()->subMonths(rand(1, 12)),
                'position' => $employeeData['position'],
                'department' => $employeeData['department'],
                'salary' => $employeeData['salary'],
                'status' => 'active',
                'employment_type' => 'full_time',
            ]);
        }

        $this->command->info('✅ Admin user created successfully!');
        $this->command->info('📧 Email: admin@test.com');
        $this->command->info('🔑 Password: password');
        $this->command->info('👤 Regular user: user@test.com / password');
        $this->command->info('🏢 Company: شركة التقنية المتقدمة');
        $this->command->info('👥 Created 3 customers, 3 suppliers, 3 invoices, 3 purchases, and 3 employees');
    }
}
