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
        Route::post('/invoices', [AccountingPageController::class, 'storeInvoice'])->name('invoices.store');
        Route::patch('/invoices/{invoice}/send', [AccountingPageController::class, 'sendInvoice'])->name('invoices.send');
        Route::get('/invoices/{invoice}', [AccountingPageController::class, 'invoiceShow'])->name('invoices.show');
        Route::get('/invoices/{invoice}/pdf-preview', [AccountingPageController::class, 'invoicePdf'])->name('invoices.pdf');
    });

    Route::middleware('permission:manage_purchases')->group(function () {
        Route::get('/purchases', [AccountingPageController::class, 'purchases'])->name('purchases');
        Route::post('/purchases', [AccountingPageController::class, 'storePurchase'])->name('purchases.store');
        Route::put('/purchases/{purchase}', [AccountingPageController::class, 'updatePurchase'])->name('purchases.update');
        Route::patch('/purchases/{purchase}/approve', [AccountingPageController::class, 'approvePurchase'])->name('purchases.approve');
        Route::delete('/purchases/{purchase}', [AccountingPageController::class, 'destroyPurchase'])->name('purchases.destroy');
    });

    Route::middleware('permission:manage_customers')->group(function () {
        Route::get('/customers', [AccountingPageController::class, 'customers'])->name('customers');
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
        Route::post('/chart-of-accounts', [AccountingPageController::class, 'storeAccount'])->name('chart_of_accounts.store');
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
    });

    Route::middleware('permission:manage_employees')->group(function () {
        Route::get('/hr', [AccountingPageController::class, 'hr'])->name('hr');
    });

    Route::middleware('permission:manage_settings')->group(function () {
        Route::get('/settings', [AccountingPageController::class, 'settings'])->name('settings');
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
