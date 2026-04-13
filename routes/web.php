<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountingPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserManagementController;

// Authentication Routes
Route::get('/', [AuthController::class, 'showLanding'])->name('landing');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.store');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');
Route::middleware('auth')->group(function () {
    Route::get('/password/change', [AuthController::class, 'showPasswordChange'])->name('password.change');
    Route::post('/password/change', [AuthController::class, 'updatePassword'])->name('password.change.update');
});

// Dashboard Routes
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->name('dashboard')
    ->middleware(['auth', 'password.change', 'company']);

Route::middleware(['auth', 'password.change', 'company'])->group(function () {
    Route::middleware('permission:manage_invoices')->group(function () {
        Route::get('/invoices', [AccountingPageController::class, 'invoices'])->name('invoices');
        Route::get('/invoices/create', [AccountingPageController::class, 'invoiceCreate'])->name('invoices.create');
        Route::get('/invoices/{invoice}/attachment', [AccountingPageController::class, 'showInvoiceAttachment'])->name('invoices.attachment');
        Route::get('/invoices/{invoice}/edit', [AccountingPageController::class, 'invoiceEdit'])->name('invoices.edit');
        Route::post('/invoices', [AccountingPageController::class, 'storeInvoice'])->name('invoices.store');
        Route::put('/invoices/{invoice}', [AccountingPageController::class, 'updateInvoice'])->name('invoices.update');
        Route::delete('/invoices/{invoice}', [AccountingPageController::class, 'destroyInvoice'])->name('invoices.destroy');
        Route::patch('/invoices/{invoice}/send', [AccountingPageController::class, 'sendInvoice'])->name('invoices.send');
        Route::get('/invoices/{invoice}', [AccountingPageController::class, 'invoiceShow'])->name('invoices.show');

        Route::get('/sales-channels', [AccountingPageController::class, 'salesChannels'])->name('sales_channels.index');
        Route::post('/sales-channels', [AccountingPageController::class, 'storeSalesChannel'])->name('sales_channels.store');
        Route::put('/sales-channels/{salesChannel}', [AccountingPageController::class, 'updateSalesChannel'])->name('sales_channels.update');
        Route::delete('/sales-channels/{salesChannel}', [AccountingPageController::class, 'destroySalesChannel'])->name('sales_channels.destroy');

    });

    Route::middleware('permission:manage_purchases')->group(function () {
        Route::get('/purchases', [AccountingPageController::class, 'purchases'])->name('purchases');
        Route::get('/purchases/create', [AccountingPageController::class, 'createPurchase'])->name('purchases.create');
        Route::get('/purchases/{purchase}/edit', [AccountingPageController::class, 'editPurchase'])->name('purchases.edit');
        Route::get('/purchases/{purchase}/attachment', [AccountingPageController::class, 'showPurchaseAttachment'])->name('purchases.attachment');
        Route::post('/purchases', [AccountingPageController::class, 'storePurchase'])->name('purchases.store');
        Route::post('/purchases/{purchase}/payments', [AccountingPageController::class, 'storePurchasePayment'])->name('purchases.payments.store');
        Route::put('/purchases/{purchase}', [AccountingPageController::class, 'updatePurchase'])->name('purchases.update');
        Route::patch('/purchases/{purchase}/approve', [AccountingPageController::class, 'approvePurchase'])->name('purchases.approve');
        Route::delete('/purchases/{purchase}', [AccountingPageController::class, 'destroyPurchase'])->name('purchases.destroy');
    });

    Route::middleware('permission:manage_customers')->group(function () {
        Route::get('/customers', [AccountingPageController::class, 'customers'])->name('customers');
        Route::get('/customers/{customer}', [AccountingPageController::class, 'showCustomer'])->name('customers.show');
        Route::post('/customers', [AccountingPageController::class, 'storeCustomer'])->name('customers.store');
        Route::put('/customers/{customer}', [AccountingPageController::class, 'updateCustomer'])->name('customers.update');
        Route::delete('/customers/{customer}', [AccountingPageController::class, 'destroyCustomer'])->name('customers.destroy');
    });

    Route::middleware('permission:manage_suppliers')->group(function () {
        Route::get('/suppliers', [AccountingPageController::class, 'suppliers'])->name('suppliers');
        Route::post('/suppliers', [AccountingPageController::class, 'storeSupplier'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}', [AccountingPageController::class, 'showSupplier'])->name('suppliers.show');
        Route::post('/suppliers/{supplier}/payments', [AccountingPageController::class, 'storeSupplierPayment'])->name('suppliers.payments.store');
        Route::put('/suppliers/{supplier}', [AccountingPageController::class, 'updateSupplier'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [AccountingPageController::class, 'destroySupplier'])->name('suppliers.destroy');
    });

    Route::middleware('permission:manage_products')->group(function () {
        Route::get('/products', [AccountingPageController::class, 'products'])->name('products');
        Route::post('/products', [AccountingPageController::class, 'storeProduct'])->name('products.store');
        Route::put('/products/{product}', [AccountingPageController::class, 'updateProduct'])->name('products.update');
        Route::delete('/products/{product}', [AccountingPageController::class, 'destroyProduct'])->name('products.destroy');
    });

    Route::middleware('permission:manage_accounts')->group(function () {
        Route::get('/chart-of-accounts', [AccountingPageController::class, 'chartOfAccounts'])->name('chart_of_accounts');
        Route::get('/chart-of-accounts/print', [AccountingPageController::class, 'printChartOfAccounts'])->name('chart_of_accounts.print');
        Route::get('/chart-of-accounts/export', [AccountingPageController::class, 'exportChartOfAccounts'])->name('chart_of_accounts.export');
        Route::get('/chart-of-accounts/{account}', [AccountingPageController::class, 'showAccount'])->name('chart_of_accounts.show');
        Route::post('/chart-of-accounts', [AccountingPageController::class, 'storeAccount'])->name('chart_of_accounts.store');
        Route::post('/chart-of-accounts/resync', [AccountingPageController::class, 'resyncCompanyAccounting'])->name('chart_of_accounts.resync');
        Route::get('/expenses', [AccountingPageController::class, 'expenses'])->name('expenses');
        Route::post('/expenses', [AccountingPageController::class, 'storeExpense'])->name('expenses.store');
        Route::delete('/expenses/{expense}', [AccountingPageController::class, 'destroyExpense'])->name('expenses.destroy');
    });

    Route::middleware('permission:manage_journal_entries')->group(function () {
        Route::get('/journal-entries', [AccountingPageController::class, 'journalEntries'])->name('journal_entries');
        Route::get('/journal-entries/create', [AccountingPageController::class, 'journalEntryCreate'])->name('journal_entries.create');
        Route::post('/journal-entries', [AccountingPageController::class, 'storeJournalEntry'])->name('journal_entries.store');
        Route::get('/journal-entries/{journalEntry}', [AccountingPageController::class, 'journalEntryShow'])->name('journal_entries.show');
    });

    Route::middleware('permission:view_reports')->group(function () {
        Route::get('/reports', [AccountingPageController::class, 'reports'])->name('reports');
        Route::get('/reports/operations-activity', [AccountingPageController::class, 'operationsActivityReport'])->name('reports.operations_activity');
        Route::get('/reports/view/{report}', [AccountingPageController::class, 'reportShow'])->name('reports.show');
        Route::get('/reports/inventory', [AccountingPageController::class, 'inventoryReport'])->name('reports.inventory');
        Route::get('/reports/financial', [AccountingPageController::class, 'financialReport'])->name('reports.financial');
    });

    Route::middleware(['auth', 'company'])->prefix('{locale?}')->group(function () {
        Route::get('/reports/product-tracking', [ProductReportController::class, 'productTrackingReport'])->name('reports.product-tracking');
        Route::get('/reports/product-performance', [ProductReportController::class, 'productPerformanceReport'])->name('reports.product-performance');
        Route::get('/reports/product-performance/print', [ProductReportController::class, 'productPerformancePrint'])->name('reports.product-performance.print');
        Route::get('/reports/product-movements', [ProductReportController::class, 'productMovementsReport'])->name('reports.product-movements');
        Route::get('/reports/product-movements/print', [ProductReportController::class, 'productMovementsPrint'])->name('reports.product-movements.print');
    });

    Route::middleware('permission:manage_employees')->group(function () {
        Route::get('/branches', [AccountingPageController::class, 'branches'])->name('branches.index');
        Route::post('/branches', [AccountingPageController::class, 'storeBranch'])->name('branches.store');
        Route::put('/branches/{branch}', [AccountingPageController::class, 'updateBranch'])->name('branches.update');
        Route::delete('/branches/{branch}', [AccountingPageController::class, 'destroyBranch'])->name('branches.destroy');

        Route::get('/hr', [AccountingPageController::class, 'hr'])->name('hr');
        Route::get('/employees', [AccountingPageController::class, 'hr'])->name('employees.index');
        Route::post('/employees', [AccountingPageController::class, 'storeEmployee'])->name('employees.store');
        Route::put('/employees/{employee}', [AccountingPageController::class, 'updateEmployee'])->name('employees.update');
        Route::delete('/employees/{employee}', [AccountingPageController::class, 'destroyEmployee'])->name('employees.destroy');
    });

    Route::middleware('permission:manage_settings')->group(function () {
        Route::get('/settings', [AccountingPageController::class, 'settings'])->name('settings');
        Route::put('/settings/company', [AccountingPageController::class, 'updateCompanySettings'])->name('settings.company.update');
        Route::put('/settings/taxes', [AccountingPageController::class, 'updateTaxSettings'])->name('settings.taxes.update');
    });

    Route::middleware('permission:manage_users')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    });
});

// Company setup middleware
Route::middleware(['auth', 'password.change'])->group(function () {
    Route::get('/setup/company', function () {
        return view('setup.company');
    })->name('setup.company');
});
