<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\TaxSetting;
use App\Support\AccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountingPageController extends Controller
{
    public function __construct(private readonly AccountingService $accountingService)
    {
    }

    public function invoices(Request $request): View
    {
        $company = $this->company($request);
        $statusFilter = $request->string('status')->toString() ?: 'all';
        $tabs = [
            'all' => ['label' => 'جميع الفواتير', 'icon' => 'fa-list'],
            'draft' => ['label' => 'مسودة', 'icon' => 'fa-edit'],
            'sent' => ['label' => 'مرسلة', 'icon' => 'fa-paper-plane'],
            'paid' => ['label' => 'مدفوعة', 'icon' => 'fa-check-circle'],
            'overdue' => ['label' => 'متأخرة', 'icon' => 'fa-exclamation-triangle'],
        ];

        $invoices = Invoice::with('customer')
            ->where('company_id', $company->id)
            ->when($statusFilter !== 'all', fn ($query) => $query->where('status', $statusFilter))
            ->orderByDesc('invoice_date')
            ->get();

        return view('invoices', compact('company', 'invoices', 'statusFilter', 'tabs'));
    }

    public function invoiceCreate(Request $request): View
    {
        $company = $this->company($request);
        $customers = Customer::where('company_id', $company->id)->orderBy('name')->get();
        $products = Product::forCompany($company->id)->active()->orderBy('name')->get();
        $defaultTaxRate = 15;

        return view('invoice_form', compact('company', 'customers', 'products', 'defaultTaxRate'));
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateInvoiceData($request, $company->id);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $invoice = DB::transaction(function () use ($company, $validated, $user) {
            $totals = $this->calculateInvoiceTotals($validated);

            $invoice = Invoice::create([
                'invoice_number' => $this->nextInvoiceNumber($company->id),
                'customer_id' => $validated['customer_id'],
                'company_id' => $company->id,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'paid_amount' => 0,
                'balance_due' => $totals['total'],
                'status' => $validated['status'] ?? 'sent',
                'payment_status' => 'pending',
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'currency' => $company->currency,
                'exchange_rate' => 1,
            ]);

            $this->syncInvoiceItems($invoice, $validated);
            $this->accountingService->syncInvoiceEntry($invoice->fresh(['items.product', 'customer']), $user);

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)->with('status', 'تم إنشاء الفاتورة وربطها بقيد محاسبي آلي.');
    }

    public function invoiceShow(Request $request, Invoice $invoice): View
    {
        $company = $this->company($request);
        abort_if((int) $invoice->company_id !== (int) $company->id, 404);

        $invoice->load('customer');
        $items = $this->invoiceItems($invoice);
        $journalEntry = JournalEntry::where('company_id', $company->id)
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->latest('id')
            ->first();

        return view('invoice_view', compact('company', 'invoice', 'items', 'journalEntry'));
    }

    public function sendInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $invoice->company_id !== (int) $company->id, 404);

        if ($invoice->status !== 'draft') {
            return redirect()->route('invoices')->with('status', 'الفاتورة ليست في حالة مسودة.');
        }

        $invoice->update(['status' => 'sent']);

        return redirect()->route('invoices')->with('status', 'تم اعتماد الفاتورة وإرسالها بنجاح.');
    }

    public function invoicePdf(Request $request, Invoice $invoice): View
    {
        $company = $this->company($request);
        abort_if((int) $invoice->company_id !== (int) $company->id, 404);

        $invoice->load('customer');
        $items = $this->invoiceItems($invoice);

        return view('invoice_pdf', compact('company', 'invoice', 'items'));
    }

    public function purchases(Request $request): View
    {
        $company = $this->company($request);
        $statusFilter = $request->string('status')->toString();
        $supplierFilter = $request->string('supplier_id')->toString();
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();

        $purchases = Purchase::with(['supplier', 'items.product'])
            ->where('company_id', $company->id)
            ->when($statusFilter !== '', fn ($query) => $query->where('status', $statusFilter))
            ->when($supplierFilter !== '', fn ($query) => $query->where('supplier_id', $supplierFilter))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('purchase_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('purchase_date', '<=', $dateTo))
            ->orderByDesc('purchase_date')
            ->get();

        $suppliers = Supplier::where('company_id', $company->id)->orderBy('name')->get();
        $products = Product::forCompany($company->id)->active()->orderBy('name')->get();

        return view('purchases', [
            'company' => $company,
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'products' => $products,
            'statusFilter' => $statusFilter,
            'supplierFilter' => $supplierFilter,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function storePurchase(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validatePurchaseData($request, $company->id);

        /** @var \App\Models\User $user */
        $user = $request->user();

        DB::transaction(function () use ($company, $validated, $user) {
            $totals = $this->calculatePurchaseTotals($validated);

            $purchase = Purchase::create([
                'purchase_number' => $this->nextPurchaseNumber($company->id),
                'supplier_id' => $validated['supplier_id'],
                'company_id' => $company->id,
                'purchase_date' => $validated['purchase_date'],
                'due_date' => $validated['due_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'paid_amount' => 0,
                'balance_due' => $totals['total'],
                'status' => $validated['status'] ?? 'draft',
                'payment_status' => 'pending',
                'notes' => $validated['notes'] ?? null,
                'currency' => $company->currency,
                'exchange_rate' => 1,
            ]);

            $this->syncPurchaseItems($purchase, $validated);
            $this->accountingService->syncPurchaseEntry($purchase->fresh(['items.product', 'supplier']), $user);
        });

        return redirect()->route('purchases')->with('status', 'تم إنشاء طلب الشراء بنجاح.');
    }

    public function updatePurchase(Request $request, Purchase $purchase): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $purchase->company_id !== (int) $company->id, 404);

        $validated = $this->validatePurchaseData($request, $company->id);

        /** @var \App\Models\User $user */
        $user = $request->user();

        DB::transaction(function () use ($purchase, $validated, $company, $user) {
            $totals = $this->calculatePurchaseTotals($validated);

            $purchase->update([
                'supplier_id' => $validated['supplier_id'],
                'purchase_date' => $validated['purchase_date'],
                'due_date' => $validated['due_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'balance_due' => max($totals['total'] - (float) $purchase->paid_amount, 0),
                'status' => $validated['status'] ?? $purchase->status,
                'payment_status' => (float) $purchase->paid_amount >= $totals['total'] ? 'paid' : ((float) $purchase->paid_amount > 0 ? 'partial' : 'pending'),
                'notes' => $validated['notes'] ?? null,
                'currency' => $company->currency,
            ]);

            $this->syncPurchaseItems($purchase, $validated);
            $this->accountingService->syncPurchaseEntry($purchase->fresh(['items.product', 'supplier']), $user);
        });

        return redirect()->route('purchases')->with('status', 'تم تحديث طلب الشراء بنجاح.');
    }

    public function approvePurchase(Request $request, Purchase $purchase): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $purchase->company_id !== (int) $company->id, 404);

        if (! in_array($purchase->status, ['draft', 'pending'], true)) {
            return redirect()->route('purchases')->with('status', 'لا يمكن اعتماد طلب الشراء في حالته الحالية.');
        }

        $purchase->update([
            'status' => 'approved',
            'payment_status' => (float) $purchase->paid_amount >= (float) $purchase->total ? 'paid' : ((float) $purchase->paid_amount > 0 ? 'partial' : 'pending'),
        ]);

        return redirect()->route('purchases')->with('status', 'تم اعتماد طلب الشراء بنجاح.');
    }

    public function destroyPurchase(Request $request, Purchase $purchase): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $purchase->company_id !== (int) $company->id, 404);

        if ((float) $purchase->paid_amount > 0) {
            return redirect()->route('purchases')->with('status', 'لا يمكن حذف طلب شراء تم تسجيل دفعات عليه.');
        }

        DB::transaction(function () use ($purchase) {
            $this->accountingService->deleteAutomaticEntriesForSource($purchase);
            $purchase->delete();
        });

        return redirect()->route('purchases')->with('status', 'تم حذف طلب الشراء بنجاح.');
    }

    public function customers(Request $request): View
    {
        $company = $this->company($request);

        $customers = Customer::where('company_id', $company->id)
            ->orderBy('name')
            ->get()
            ->map(function (Customer $customer) use ($company) {
                $customer->code = 'CUS-' . str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT);
                $customer->balance = (float) $customer->invoices()->sum('balance_due');
                $customer->credit_limit = (float) $customer->credit_limit;
                $customer->currency = $company->currency;

                return $customer;
            });

        return view('customers', compact('company', 'customers'));
    }

    public function suppliers(Request $request): View
    {
        $company = $this->company($request);

        $suppliers = Supplier::where('company_id', $company->id)
            ->with(['purchases' => function ($query) {
                $query->orderByDesc('purchase_date');
            }])
            ->withCount('products')
            ->orderBy('name')
            ->get()
            ->map(function (Supplier $supplier) {
                $supplier->code = 'SUP-' . str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT);
                $supplier->balance = (float) $supplier->purchases->sum('balance_due');
                $supplier->purchases_total = (float) $supplier->purchases->sum('total');

                return $supplier;
            });

        return view('suppliers', compact('company', 'suppliers'));
    }

    public function showSupplier(Request $request, Supplier $supplier): View
    {
        $company = $this->company($request);
        abort_if((int) $supplier->company_id !== (int) $company->id, 404);

        $supplier->load([
            'purchases' => fn ($query) => $query->orderByDesc('purchase_date'),
            'products' => fn ($query) => $query->orderBy('name'),
        ])->loadCount(['products', 'purchases']);

        $supplier->code = $supplier->code ?: 'SUP-' . str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT);
        $supplier->balance = (float) $supplier->purchases->sum('balance_due');
        $supplier->purchases_total = (float) $supplier->purchases->sum('total');

        return view('supplier_show', compact('company', 'supplier'));
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateSupplierData($request, $company->id);

        $supplier = Supplier::create($this->supplierPayload($validated, $company->id));

        if (! $supplier->code) {
            $supplier->update([
                'code' => 'SUP-' . str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT),
            ]);
        }

        return redirect()->route('suppliers')->with('status', 'تمت إضافة المورد بنجاح.');
    }

    public function updateSupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $supplier->company_id !== (int) $company->id, 404);

        $validated = $this->validateSupplierData($request, $company->id, $supplier);

        $supplier->update($this->supplierPayload($validated, $company->id, $supplier));

        if (! $supplier->code) {
            $supplier->update([
                'code' => 'SUP-' . str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT),
            ]);
        }

        if ($request->input('redirect_to') === 'show') {
            return redirect()->route('suppliers.show', $supplier)->with('status', 'تم تحديث المورد بنجاح.');
        }

        return redirect()->route('suppliers')->with('status', 'تم تحديث المورد بنجاح.');
    }

    public function storeSupplierPayment(Request $request, Supplier $supplier): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $supplier->company_id !== (int) $company->id, 404);

        $supplier->load(['purchases' => fn ($query) => $query->orderBy('purchase_date')]);
        $outstandingBalance = (float) $supplier->purchases->where('balance_due', '>', 0)->sum('balance_due');

        $validated = $request->validate([
            'supplier_action' => ['nullable', 'string'],
            'payment_amount' => ['required', 'numeric', 'min:0.01', 'max:' . max($outstandingBalance, 0.01)],
        ], [
            'payment_amount.max' => 'مبلغ الدفع أكبر من الرصيد المستحق على المورد.',
        ]);

        if ($outstandingBalance <= 0) {
            return redirect()->route('suppliers.show', $supplier)->with('error', 'لا يوجد رصيد مستحق على هذا المورد حالياً.');
        }

        $paymentAmount = round((float) $validated['payment_amount'], 2);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $paymentReference = 'SUP-PMT-' . now()->format('YmdHis');

        DB::transaction(function () use ($supplier, $paymentAmount, $user, $paymentReference) {
            $remainingAmount = $paymentAmount;

            $openPurchases = Purchase::where('company_id', $supplier->company_id)
                ->where('supplier_id', $supplier->id)
                ->where('balance_due', '>', 0)
                ->whereNotIn('status', ['cancelled', 'paid'])
                ->orderBy('purchase_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($openPurchases as $purchase) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $appliedAmount = min($remainingAmount, (float) $purchase->balance_due);
                $updatedPaidAmount = round((float) $purchase->paid_amount + $appliedAmount, 2);
                $updatedBalance = round(max((float) $purchase->total - $updatedPaidAmount, 0), 2);

                $purchase->update([
                    'paid_amount' => $updatedPaidAmount,
                    'balance_due' => $updatedBalance,
                    'payment_status' => $this->purchasePaymentStatus($updatedPaidAmount, (float) $purchase->total),
                    'status' => $this->purchaseStatusAfterPayment($purchase->status, $updatedPaidAmount, $updatedBalance),
                ]);

                $remainingAmount = round($remainingAmount - $appliedAmount, 2);
            }

            $this->accountingService->createSupplierPaymentEntry($supplier, $paymentAmount, $user, $paymentReference);
        });

        return redirect()->route('suppliers.show', $supplier)->with('status', 'تم تسجيل دفعة بمبلغ ' . number_format($paymentAmount, 2) . ' ' . $company->currency . ' وخصمها من الرصيد المستحق.');
    }

    public function destroySupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $supplier->company_id !== (int) $company->id, 404);

        if ($supplier->purchases()->exists()) {
            $redirect = $request->input('redirect_to') === 'show'
                ? redirect()->route('suppliers.show', $supplier)
                : redirect()->route('suppliers');

            return $redirect->with('error', 'لا يمكن حذف المورد لأنه مرتبط بفواتير مشتريات.');
        }

        $supplier->delete();

        return redirect()->route('suppliers')->with('status', 'تم حذف المورد بنجاح.');
    }

    public function products(Request $request): View
    {
        $company = $this->company($request);
        $products = Product::forCompany($company->id)
            ->with('supplier')
            ->orderBy('name')
            ->get();
        $suppliers = Supplier::forCompany($company->id)
            ->orderBy('name')
            ->get();

        return view('products', compact('company', 'products', 'suppliers'));
    }

    public function expenses(Request $request): View
    {
        $company = $this->company($request);
        $expenses = Expense::with(['expenseAccount', 'paymentAccount', 'creator'])
            ->where('company_id', $company->id)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $expenseAccounts = Account::where('company_id', $company->id)
            ->where('account_type', 'expense')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $paymentAccounts = Account::where('company_id', $company->id)
            ->whereIn('account_type', ['asset', 'liability'])
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereIn('code', ['1101', '1102', '1010', '1020', '2000', '2100'])
                    ->orWhere('name', 'like', '%Bank%')
                    ->orWhere('name', 'like', '%Cash%')
                    ->orWhere('name_ar', 'like', '%بنكي%')
                    ->orWhere('name_ar', 'like', '%صندوق%')
                    ->orWhere('name_ar', 'like', '%دائن%');
            })
            ->orderBy('code')
            ->get();

        return view('expenses', compact('company', 'expenses', 'expenseAccounts', 'paymentAccounts'));
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateExpenseData($request, $company->id);

        /** @var \App\Models\User $user */
        $user = $request->user();

        DB::transaction(function () use ($validated, $company, $user) {
            $amount = round((float) $validated['amount'], 2);
            $taxRate = round((float) ($validated['tax_rate'] ?? 0), 2);
            $taxAmount = round($amount * ($taxRate / 100), 2);
            $total = round($amount + $taxAmount, 2);

            $expense = Expense::create([
                'expense_number' => $this->nextExpenseNumber($company->id),
                'company_id' => $company->id,
                'expense_account_id' => $validated['expense_account_id'],
                'payment_account_id' => $validated['payment_account_id'],
                'created_by' => $user->id,
                'expense_date' => $validated['expense_date'],
                'name' => $validated['name'],
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'] ?? null,
                'amount' => $amount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'status' => 'posted',
            ]);

            $this->accountingService->syncExpenseEntry($expense->fresh(['expenseAccount', 'paymentAccount']), $user);
        });

        return redirect()->route('expenses')->with('status', 'تمت إضافة المصروف وربطه بقيد محاسبي آلي.');
    }

    public function destroyExpense(Request $request, Expense $expense): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $expense->company_id !== (int) $company->id, 404);

        DB::transaction(function () use ($expense) {
            $this->accountingService->deleteAutomaticEntriesForSource($expense);
            $expense->delete();
        });

        return redirect()->route('expenses')->with('status', 'تم حذف المصروف وعكس القيد المحاسبي المرتبط به.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $company = $this->company($request);

        $validated = $this->validateProductData($request, $company->id);

        $product = Product::create($this->productPayload($validated, $company->id));

        if (! $product->code) {
            $product->update([
                'code' => 'PRD-' . str_pad((string) $product->id, 4, '0', STR_PAD_LEFT),
            ]);
        }

        return redirect()
            ->route('products')
            ->with('status', 'تمت إضافة المنتج بنجاح.');
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $product->company_id !== (int) $company->id, 404);

        $validated = $this->validateProductData($request, $company->id, $product);

        $product->update($this->productPayload($validated, $company->id));

        if (! $product->code) {
            $product->update([
                'code' => 'PRD-' . str_pad((string) $product->id, 4, '0', STR_PAD_LEFT),
            ]);
        }

        return redirect()
            ->route('products')
            ->with('status', 'تم تحديث المنتج بنجاح.');
    }

    public function destroyProduct(Request $request, Product $product): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $product->company_id !== (int) $company->id, 404);

        $product->delete();

        return redirect()
            ->route('products')
            ->with('status', 'تم حذف المنتج بنجاح.');
    }

    public function chartOfAccounts(Request $request): View
    {
        $company = $this->company($request);
        $accounts = Account::with('children')
            ->where('company_id', $company->id)
            ->orderBy('code')
            ->get();

        return view('chart_of_accounts', compact('company', 'accounts'));
    }

    public function journalEntries(Request $request): View
    {
        $company = $this->company($request);
        $entries = JournalEntry::with('lines')
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->get();

        return view('journal_entries', compact('company', 'entries'));
    }

    public function journalEntryCreate(Request $request): View
    {
        $company = $this->company($request);
        $accounts = Account::where('company_id', $company->id)->orderBy('code')->get();
        $nextEntryNumber = $this->accountingService->nextJournalEntryNumber($company->id);

        return view('journal_entry_form', compact('company', 'accounts', 'nextEntryNumber'));
    }

    public function storeJournalEntry(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateJournalEntryData($request, $company->id);
        $lines = $this->normalizeJournalLines($validated, $company->id);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $entry = DB::transaction(function () use ($company, $validated, $lines, $user) {
            return $this->accountingService->createManualJournalEntry($company->id, $user, [
                'entry_date' => $validated['entry_date'],
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'],
                'lines' => $lines,
            ]);
        });

        return redirect()->route('journal_entries.show', $entry)->with('status', 'تم إنشاء القيد اليدوي وترحيله بنجاح.');
    }

    public function journalEntryShow(Request $request, JournalEntry $journalEntry): View
    {
        $company = $this->company($request);
        abort_if((int) $journalEntry->company_id !== (int) $company->id, 404);

        $journalEntry->load(['lines.account', 'creator', 'poster']);
        $sourceContext = $this->journalEntrySourceContext($journalEntry);

        return view('journal_entry_show', compact('company', 'journalEntry', 'sourceContext'));
    }

    public function reports(Request $request): View
    {
        $company = $this->company($request);

        $totalRevenue = (float) Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partial', 'paid'])
            ->sum('total');
        $totalExpenses = (float) Purchase::where('company_id', $company->id)
            ->whereIn('status', ['approved', 'partial', 'paid'])
            ->sum('total') + (float) Expense::where('company_id', $company->id)->sum('total');

        $stats = [
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_profit' => $totalRevenue - $totalExpenses,
            'cash_flow' => $totalRevenue - $totalExpenses,
            'avg_order_value' => (float) Invoice::where('company_id', $company->id)->avg('total'),
            'total_customers' => Customer::where('company_id', $company->id)->count(),
            'inventory_value' => 0.0,
            'outstanding_receivables' => (float) Invoice::where('company_id', $company->id)->sum('balance_due'),
        ];

        $reportRows = [
            ['label' => 'الإيرادات', 'value' => $stats['total_revenue']],
            ['label' => 'المصروفات', 'value' => $stats['total_expenses']],
            ['label' => 'صافي الربح', 'value' => $stats['net_profit']],
            ['label' => 'الذمم المدينة', 'value' => $stats['outstanding_receivables']],
        ];

        return view('reports', compact('company', 'stats', 'reportRows'));
    }

    public function hr(Request $request): View
    {
        $company = $this->company($request);
        $employees = Employee::where('company_id', $company->id)
            ->orderBy('first_name')
            ->get();

        return view('hr', compact('company', 'employees'));
    }

    public function settings(Request $request): View
    {
        $company = $this->company($request);
        $accounts = Account::where('company_id', $company->id)->orderBy('code')->get();
        $taxSettings = TaxSetting::where('company_id', $company->id)->orderByDesc('is_default')->get();
        $countries = $this->countryConfigs();

        return view('settings', compact('company', 'accounts', 'taxSettings', 'countries'));
    }

    private function company(Request $request): Company
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return $user->company;
    }

    private function invoiceItems(Invoice $invoice): Collection
    {
        if (Schema::hasTable('invoice_items') && Schema::hasColumns('invoice_items', ['invoice_id', 'description', 'quantity', 'unit_price', 'tax_rate', 'total'])) {
            return $invoice->items()->with('product')->get();
        }

        return collect();
    }

    private function validateInvoiceData(Request $request, int $companyId): array
    {
        return $request->validate([
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'status' => ['required', Rule::in(['draft', 'sent'])],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'item_product_id' => ['required', 'array', 'min:1'],
            'item_product_id.*' => [
                'nullable',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'item_description' => ['required', 'array', 'min:1'],
            'item_description.*' => ['required', 'string', 'max:255'],
            'item_quantity' => ['required', 'array', 'min:1'],
            'item_quantity.*' => ['required', 'numeric', 'min:0.01'],
            'item_price' => ['required', 'array', 'min:1'],
            'item_price.*' => ['required', 'numeric', 'min:0'],
            'item_tax_rate' => ['nullable', 'array'],
            'item_tax_rate.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function validateJournalEntryData(Request $request, int $companyId): array
    {
        return $request->validate([
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'line_account' => ['required', 'array', 'min:2'],
            'line_account.*' => ['nullable', Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'line_description' => ['required', 'array', 'min:2'],
            'line_description.*' => ['nullable', 'string', 'max:255'],
            'line_debit' => ['required', 'array', 'min:2'],
            'line_debit.*' => ['nullable', 'numeric', 'min:0'],
            'line_credit' => ['required', 'array', 'min:2'],
            'line_credit.*' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function validateProductData(Request $request, int $companyId, ?Product $product = null): array
    {
        $uniqueCodeRule = Rule::unique('products', 'code')
            ->where(fn ($query) => $query->where('company_id', $companyId));

        if ($product) {
            $uniqueCodeRule = $uniqueCodeRule->ignore($product->id);
        }

        return $request->validate([
            'product_modal' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'supplier_id' => [
                'nullable',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'code' => ['nullable', 'string', 'max:50', $uniqueCodeRule],
            'type' => ['required', Rule::in(['product', 'service'])],
            'unit' => ['nullable', 'string', 'max:50'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);
    }

    private function validateSupplierData(Request $request, int $companyId, ?Supplier $supplier = null): array
    {
        $uniqueCodeRule = Rule::unique('suppliers', 'code')
            ->where(fn ($query) => $query->where('company_id', $companyId));

        $uniqueEmailRule = Rule::unique('suppliers', 'email')
            ->where(fn ($query) => $query->where('company_id', $companyId));

        if ($supplier) {
            $uniqueCodeRule = $uniqueCodeRule->ignore($supplier->id);
            $uniqueEmailRule = $uniqueEmailRule->ignore($supplier->id);
        }

        return $request->validate([
            'supplier_modal' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:200'],
            'name_ar' => ['nullable', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:20', $uniqueCodeRule],
            'email' => ['nullable', 'email', 'max:120', $uniqueEmailRule],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'redirect_to' => ['nullable', 'string'],
        ]);
    }

    private function validateExpenseData(Request $request, int $companyId): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'expense_date' => ['required', 'date'],
            'expense_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('account_type', 'expense')),
            ],
            'payment_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);
    }

    private function supplierPayload(array $validated, int $companyId, ?Supplier $supplier = null): array
    {
        return [
            'company_id' => $companyId,
            'code' => $validated['code'] ?? null,
            'name' => $validated['name'],
            'name_ar' => $validated['name_ar'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $validated['country'] ?? null,
            'tax_number' => $validated['tax_number'] ?? null,
            'credit_limit' => $validated['credit_limit'] ?? 0,
            'balance' => $supplier?->balance ?? 0,
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    private function productPayload(array $validated, int $companyId): array
    {
        return [
            'company_id' => $companyId,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'name' => $validated['name'],
            'name_ar' => $validated['name_ar'] ?? null,
            'code' => $validated['code'] ?? null,
            'type' => $validated['type'],
            'unit' => ($validated['unit'] ?? null) ?: 'وحدة',
            'cost_price' => $validated['cost_price'],
            'sell_price' => $validated['sell_price'],
            'stock_quantity' => $validated['type'] === 'service' ? 0 : ($validated['stock_quantity'] ?? 0),
            'min_stock' => $validated['type'] === 'service' ? 0 : ($validated['min_stock'] ?? 0),
            'tax_rate' => $validated['tax_rate'] ?? 0,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ];
    }

    private function validatePurchaseData(Request $request, int $companyId): array
    {
        $validated = $request->validate([
            'purchase_modal' => ['nullable', 'string'],
            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'purchase_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'status' => ['nullable', Rule::in(['draft', 'pending', 'approved'])],
            'notes' => ['nullable', 'string'],
            'item_product_id' => ['required', 'array', 'min:1'],
            'item_product_id.*' => [
                'nullable',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'item_description' => ['required', 'array', 'min:1'],
            'item_description.*' => ['required', 'string', 'max:255'],
            'item_quantity' => ['required', 'array', 'min:1'],
            'item_quantity.*' => ['required', 'numeric', 'min:0.01'],
            'item_price' => ['required', 'array', 'min:1'],
            'item_price.*' => ['required', 'numeric', 'min:0'],
            'item_tax_rate' => ['nullable', 'array'],
            'item_tax_rate.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        return $validated;
    }

    private function purchasePaymentStatus(float $paidAmount, float $total): string
    {
        if ($paidAmount >= $total) {
            return 'paid';
        }

        if ($paidAmount > 0) {
            return 'partial';
        }

        return 'pending';
    }

    private function purchaseStatusAfterPayment(string $currentStatus, float $paidAmount, float $balanceDue): string
    {
        if ($balanceDue <= 0) {
            return 'paid';
        }

        if ($paidAmount > 0) {
            return 'partial';
        }

        return $currentStatus;
    }

    private function calculatePurchaseTotals(array $validated): array
    {
        $subtotal = 0;
        $taxAmount = 0;

        foreach ($validated['item_quantity'] as $index => $quantity) {
            $unitPrice = (float) ($validated['item_price'][$index] ?? 0);
            $rate = (float) ($validated['item_tax_rate'][$index] ?? 0);
            $lineSubtotal = (float) $quantity * $unitPrice;
            $lineTax = $lineSubtotal * ($rate / 100);

            $subtotal += $lineSubtotal;
            $taxAmount += $lineTax;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total' => round($subtotal + $taxAmount, 2),
        ];
    }

    private function calculateInvoiceTotals(array $validated): array
    {
        $subtotal = 0;
        $taxAmount = 0;

        foreach ($validated['item_quantity'] as $index => $quantity) {
            $unitPrice = (float) ($validated['item_price'][$index] ?? 0);
            $rate = (float) ($validated['item_tax_rate'][$index] ?? 0);
            $lineSubtotal = (float) $quantity * $unitPrice;
            $lineTax = $lineSubtotal * ($rate / 100);

            $subtotal += $lineSubtotal;
            $taxAmount += $lineTax;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total' => round($subtotal + $taxAmount, 2),
        ];
    }

    private function syncInvoiceItems(Invoice $invoice, array $validated): void
    {
        $invoice->items()->delete();

        foreach ($validated['item_description'] as $index => $description) {
            $quantity = (float) ($validated['item_quantity'][$index] ?? 0);
            $unitPrice = (float) ($validated['item_price'][$index] ?? 0);
            $taxRate = (float) ($validated['item_tax_rate'][$index] ?? 0);
            $lineSubtotal = $quantity * $unitPrice;
            $lineTax = $lineSubtotal * ($taxRate / 100);

            $invoice->items()->create([
                'product_id' => $validated['item_product_id'][$index] ?: null,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => round($lineTax, 2),
                'total' => round($lineSubtotal + $lineTax, 2),
            ]);
        }
    }

    private function syncPurchaseItems(Purchase $purchase, array $validated): void
    {
        $purchase->items()->delete();

        foreach ($validated['item_description'] as $index => $description) {
            $quantity = (float) ($validated['item_quantity'][$index] ?? 0);
            $unitPrice = (float) ($validated['item_price'][$index] ?? 0);
            $taxRate = (float) ($validated['item_tax_rate'][$index] ?? 0);
            $lineSubtotal = $quantity * $unitPrice;
            $lineTax = $lineSubtotal * ($taxRate / 100);

            $purchase->items()->create([
                'product_id' => $validated['item_product_id'][$index] ?: null,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => round($lineTax, 2),
                'total' => round($lineSubtotal + $lineTax, 2),
            ]);
        }
    }

    private function nextPurchaseNumber(int $companyId): string
    {
        $count = Purchase::where('company_id', $companyId)->count() + 1;

        return 'PUR-' . now()->format('Y') . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function nextInvoiceNumber(int $companyId): string
    {
        $count = Invoice::where('company_id', $companyId)->count() + 1;

        return 'INV-' . now()->format('Y') . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function nextExpenseNumber(int $companyId): string
    {
        $count = Expense::where('company_id', $companyId)->count() + 1;

        return 'EXP-' . now()->format('Y') . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function normalizeJournalLines(array $validated, int $companyId): array
    {
        $lines = [];

        foreach ($validated['line_account'] as $index => $accountId) {
            $description = trim((string) ($validated['line_description'][$index] ?? ''));
            $debit = round((float) ($validated['line_debit'][$index] ?? 0), 2);
            $credit = round((float) ($validated['line_credit'][$index] ?? 0), 2);
            $hasValue = $accountId || $description !== '' || $debit > 0 || $credit > 0;

            if (! $hasValue) {
                continue;
            }

            if (! $accountId) {
                throw ValidationException::withMessages([
                    'line_account.' . $index => 'يجب اختيار حساب لكل بند يحتوي وصفًا أو مبلغًا.',
                ]);
            }

            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages([
                    'line_debit.' . $index => 'لا يمكن إدخال مدين ودائن في نفس السطر.',
                ]);
            }

            if ($debit <= 0 && $credit <= 0) {
                throw ValidationException::withMessages([
                    'line_debit.' . $index => 'يجب إدخال مبلغ مدين أو دائن لكل سطر مستخدم.',
                ]);
            }

            $lines[] = [
                'account_id' => (int) $accountId,
                'description' => $description !== '' ? $description : null,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'line_account' => 'يجب إدخال سطرين محاسبيين على الأقل.',
            ]);
        }

        return $lines;
    }

    private function journalEntrySourceContext(JournalEntry $journalEntry): array
    {
        $sourceType = $journalEntry->source_type;

        if (! $sourceType) {
            return [
                'label' => 'قيد يدوي',
                'route' => null,
            ];
        }

        return match (str_replace(':payment', '', $sourceType)) {
            Invoice::class => [
                'label' => 'الفاتورة المرتبطة',
                'route' => route('invoices.show', $journalEntry->source_id),
            ],
            Purchase::class => [
                'label' => 'طلب الشراء المرتبط',
                'route' => route('purchases'),
            ],
            Expense::class => [
                'label' => 'سجل المصروف المرتبط',
                'route' => route('expenses'),
            ],
            Supplier::class => [
                'label' => 'المورد المرتبط',
                'route' => route('suppliers.show', $journalEntry->source_id),
            ],
            default => [
                'label' => 'مصدر غير معروف',
                'route' => null,
            ],
        };
    }

    private function countryConfigs(): Collection
    {
        return collect([
            'SA' => ['name_ar' => 'المملكة العربية السعودية', 'currency' => 'SAR'],
            'AE' => ['name_ar' => 'الإمارات العربية المتحدة', 'currency' => 'AED'],
            'US' => ['name_ar' => 'الولايات المتحدة', 'currency' => 'USD'],
            'EG' => ['name_ar' => 'مصر', 'currency' => 'EGP'],
            'JO' => ['name_ar' => 'الأردن', 'currency' => 'JOD'],
        ]);
    }
}
