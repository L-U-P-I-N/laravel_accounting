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
use App\Support\DocumentNumberGenerator;
use App\Support\ReferenceGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountingPageController extends Controller
{
    public function __construct(
        private readonly AccountingService $accountingService,
        private readonly DocumentNumberGenerator $documentNumberGenerator,
        private readonly ReferenceGenerator $referenceGenerator,
    )
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

        $customers = Customer::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('invoices', compact('company', 'invoices', 'statusFilter', 'tabs', 'customers'));
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
                'invoice_number' => $this->documentNumberGenerator->nextInvoiceNumber($company->id),
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
                'purchase_number' => $this->documentNumberGenerator->nextPurchaseNumber($company->id),
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

        $suggestedPaymentReference = $this->referenceGenerator->nextSupplierPaymentReference($company->id);

        return view('supplier_show', compact('company', 'supplier', 'suggestedPaymentReference'));
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
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ], [
            'payment_amount.max' => 'مبلغ الدفع أكبر من الرصيد المستحق على المورد.',
        ]);

        if ($outstandingBalance <= 0) {
            return redirect()->route('suppliers.show', $supplier)->with('error', 'لا يوجد رصيد مستحق على هذا المورد حالياً.');
        }

        $paymentAmount = round((float) $validated['payment_amount'], 2);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $paymentReference = trim((string) ($validated['payment_reference'] ?? ''));

        if ($paymentReference === '') {
            $paymentReference = $this->referenceGenerator->nextSupplierPaymentReference($company->id);
        }

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
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'expense_account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'expense_id' => [
                'nullable',
                Rule::exists('expenses', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
        ]);

        $expenses = $this->expenseReportQuery($company->id, $filters)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $expenseAccounts = Account::where('company_id', $company->id)
            ->where('account_type', 'expense')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $expenseTargets = Expense::where('company_id', $company->id)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get(['id', 'name', 'reference', 'expense_date']);

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

        $suggestedExpenseReference = $this->referenceGenerator->nextExpenseReference($company->id);

        return view('expenses', [
            'company' => $company,
            'expenses' => $expenses,
            'expenseAccounts' => $expenseAccounts,
            'paymentAccounts' => $paymentAccounts,
            'suggestedExpenseReference' => $suggestedExpenseReference,
            'expenseTargets' => $expenseTargets,
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'date_from' => (string) ($filters['date_from'] ?? ''),
                'date_to' => (string) ($filters['date_to'] ?? ''),
                'expense_account_id' => isset($filters['expense_account_id']) ? (int) $filters['expense_account_id'] : null,
                'expense_id' => isset($filters['expense_id']) ? (int) $filters['expense_id'] : null,
            ],
        ]);
    }

    public function expensesReport(Request $request): View
    {
        $company = $this->company($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'expense_account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'expense_id' => [
                'nullable',
                Rule::exists('expenses', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'print' => ['nullable', 'boolean'],
        ]);

        $expenses = $this->expenseReportQuery($company->id, $filters)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        $summary = [
            'count' => $expenses->count(),
            'total' => (float) $expenses->sum('total'),
            'tax' => (float) $expenses->sum('tax_amount'),
            'average' => $expenses->isNotEmpty() ? (float) $expenses->avg('total') : 0.0,
        ];

        $filterSummary = array_values(array_filter([
            ! empty($filters['search']) ? 'بحث: ' . $filters['search'] : null,
            ! empty($filters['date_from']) ? 'من: ' . $filters['date_from'] : null,
            ! empty($filters['date_to']) ? 'إلى: ' . $filters['date_to'] : null,
            ! empty($filters['expense_account_id']) ? 'حساب المصروف: ' . optional(Account::find($filters['expense_account_id']))->name : null,
            ! empty($filters['expense_id']) ? 'مصروف محدد: ' . optional(Expense::find($filters['expense_id']))->name : null,
        ]));

        return view('expenses_report', [
            'company' => $company,
            'expenses' => $expenses,
            'summary' => $summary,
            'filterSummary' => $filterSummary,
            'printMode' => $request->boolean('print'),
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateExpenseData($request, $company->id);

        /** @var \App\Models\User $user */
        $user = $request->user();

        DB::transaction(function () use ($validated, $company, $user) {
            $expenseNumber = $this->documentNumberGenerator->nextExpenseNumber($company->id);
            $amount = round((float) $validated['amount'], 2);
            $taxRate = round((float) ($validated['tax_rate'] ?? 0), 2);
            $taxAmount = round($amount * ($taxRate / 100), 2);
            $total = round($amount + $taxAmount, 2);
            $reference = trim((string) ($validated['reference'] ?? ''));

            if ($reference === '') {
                $reference = $this->referenceGenerator->fromIdentifier($expenseNumber);
            }

            $expense = Expense::create([
                'expense_number' => $expenseNumber,
                'company_id' => $company->id,
                'expense_account_id' => $validated['expense_account_id'],
                'payment_account_id' => $validated['payment_account_id'],
                'created_by' => $user->id,
                'expense_date' => $validated['expense_date'],
                'name' => $validated['name'],
                'reference' => $reference,
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
        $allAccounts = Account::query()
            ->where('company_id', $company->id)
            ->orderBy('code')
            ->get();

        $accountFilters = [
            'search' => trim($request->string('search')->toString()),
            'account_type' => $request->string('account_type')->toString(),
            'min_balance' => $request->string('min_balance')->toString(),
            'max_balance' => $request->string('max_balance')->toString(),
        ];

        $hasAccountFilters = $this->hasAccountFilters($accountFilters);
        $matchingAccounts = $this->filterAccounts($allAccounts, $accountFilters);
        $accounts = $hasAccountFilters
            ? $this->buildFilteredAccountTree($allAccounts, $matchingAccounts)
            : $this->buildAccountTree($allAccounts);
        $accountStats = $hasAccountFilters ? $matchingAccounts : $allAccounts;

        $parentOptions = $allAccounts->map(fn (Account $account) => [
            'id' => $account->id,
            'code' => $account->code,
            'label' => $account->code . ' - ' . $account->full_name,
            'type' => $account->account_type,
        ])->values();

        $suggestedParentIds = $this->suggestedParentIds($allAccounts);

        return view('chart_of_accounts', compact(
            'company',
            'accounts',
            'accountStats',
            'accountFilters',
            'hasAccountFilters',
            'matchingAccounts',
            'parentOptions',
            'suggestedParentIds'
        ));
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateAccountData($request, $company->id);

        $suggestedParentId = $this->suggestedParentIdForType($company->id, $validated['account_type']);
        $parentId = $validated['parent_id'] ?? $suggestedParentId;

        if ($parentId) {
            $parent = Account::query()
                ->where('company_id', $company->id)
                ->find($parentId);

            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_id' => 'الحساب الأب المحدد غير موجود.',
                ]);
            }

            if ($parent->id === ($validated['id'] ?? null)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'لا يمكن ربط الحساب بنفسه.',
                ]);
            }
        }

        Account::create([
            'company_id' => $company->id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'name_ar' => $validated['name_ar'] ?? null,
            'account_type' => $validated['account_type'],
            'parent_id' => $parentId,
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'is_system' => false,
            'balance' => 0,
        ]);

        return redirect()->route('chart_of_accounts')->with('status', 'تمت إضافة الحساب بنجاح.');
    }

    public function journalEntries(Request $request): View
    {
        $company = $this->company($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['draft', 'posted', 'reversed'])],
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $entries = JournalEntry::with(['lines.account'])
            ->where('company_id', $company->id)
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = trim((string) $filters['search']);

                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery->where('entry_number', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('reference', 'like', '%' . $search . '%');
                });
            })
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('entry_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('entry_date', '<=', $filters['date_to']))
            ->when(! empty($filters['account_id']), function ($query) use ($filters) {
                $query->whereHas('lines', fn ($linesQuery) => $linesQuery->where('account_id', $filters['account_id']));
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        $accounts = Account::where('company_id', $company->id)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'name_ar']);

        return view('journal_entries', [
            'company' => $company,
            'entries' => $entries,
            'accounts' => $accounts,
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'account_id' => isset($filters['account_id']) ? (int) $filters['account_id'] : null,
                'date_from' => (string) ($filters['date_from'] ?? ''),
                'date_to' => (string) ($filters['date_to'] ?? ''),
            ],
        ]);
    }

    public function journalEntryCreate(Request $request): View
    {
        $company = $this->company($request);
        $accounts = Account::where('company_id', $company->id)->orderBy('code')->get();
        $nextEntryNumber = $this->documentNumberGenerator->nextJournalEntryNumber($company->id);
        $suggestedJournalReference = $this->referenceGenerator->nextJournalReference($company->id);

        return view('journal_entry_form', compact('company', 'accounts', 'nextEntryNumber', 'suggestedJournalReference'));
    }

    public function storeJournalEntry(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateJournalEntryData($request, $company->id);
        $lines = $this->normalizeJournalLines($validated, $company->id);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $entry = DB::transaction(function () use ($company, $validated, $lines, $user) {
            $reference = trim((string) ($validated['reference'] ?? ''));

            if ($reference === '') {
                $reference = $this->referenceGenerator->nextJournalReference($company->id);
            }

            return $this->accountingService->createManualJournalEntry($company->id, $user, [
                'entry_date' => $validated['entry_date'],
                'reference' => $reference,
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

        $reportTypes = [
            'income_statement' => [
                'label' => 'قائمة الدخل',
                'description' => 'مقارنة الإيرادات بالمشتريات والمصروفات للوصول إلى صافي الربح.',
                'focus' => null,
            ],
            'account_balances' => [
                'label' => 'أرصدة الحسابات',
                'description' => 'عرض حركة وأرصدة شجرة الحسابات أو حساب محدد.',
                'focus' => 'account',
            ],
            'product_sales' => [
                'label' => 'مبيعات المنتجات',
                'description' => 'تحليل مبيعات كل المنتجات أو منتج محدد خلال فترة معينة.',
                'focus' => 'product',
            ],
            'expense_details' => [
                'label' => 'تفاصيل المصروفات',
                'description' => 'تقرير بالمصروفات المسجلة أو مصروف محدد بالتفصيل.',
                'focus' => 'expense',
            ],
            'receivables' => [
                'label' => 'الذمم المدينة',
                'description' => 'أرصدة العملاء المستحقة أو عميل محدد.',
                'focus' => 'customer',
            ],
            'payables' => [
                'label' => 'الذمم الدائنة',
                'description' => 'أرصدة الموردين المستحقة أو مورد محدد.',
                'focus' => 'supplier',
            ],
        ];

        $periodOptions = [
            'monthly' => 'شهري',
            'quarterly' => 'ربع سنوي',
            'yearly' => 'سنوي',
            'custom' => 'مخصص',
        ];

        $validated = $request->validate([
            'report_type' => ['nullable', Rule::in(array_keys($reportTypes))],
            'period' => ['nullable', Rule::in(array_keys($periodOptions))],
            'date_from' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => $request->input('period') === 'custom'),
            ],
            'date_to' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => $request->input('period') === 'custom'),
                'after_or_equal:date_from',
            ],
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'expense_id' => [
                'nullable',
                Rule::exists('expenses', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'customer_id' => [
                'nullable',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'supplier_id' => [
                'nullable',
                Rule::exists('suppliers', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'print' => ['nullable', 'boolean'],
        ]);

        $selectedReportType = $validated['report_type'] ?? 'income_statement';
        $selectedPeriod = $validated['period'] ?? config('accounting.reports.default_period', 'monthly');
        [$dateFrom, $dateTo] = $this->resolveReportRange(
            $selectedPeriod,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );

        $accounts = Account::where('company_id', $company->id)->orderBy('code')->get(['id', 'code', 'name', 'account_type']);
        $products = Product::forCompany($company->id)->active()->orderBy('name')->get(['id', 'name', 'code']);
        $expenses = Expense::where('company_id', $company->id)
            ->orderByDesc('expense_date')
            ->get(['id', 'name', 'reference', 'expense_date', 'total']);
        $customers = Customer::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);
        $suppliers = Supplier::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);

        $stats = $this->reportSummaryStats($company->id, $dateFrom, $dateTo);
        $report = $this->buildReportData($company, $selectedReportType, $validated, $dateFrom, $dateTo, $reportTypes);
        $reportRows = $report['rows'];

        $viewData = [
            'company' => $company,
            'stats' => $stats,
            'reportRows' => $reportRows,
            'report' => $report,
            'reportTypes' => $reportTypes,
            'periodOptions' => $periodOptions,
            'selectedReportType' => $selectedReportType,
            'selectedPeriod' => $selectedPeriod,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'accounts' => $accounts,
            'products' => $products,
            'expenses' => $expenses,
            'customers' => $customers,
            'suppliers' => $suppliers,
            'selectedAccountId' => isset($validated['account_id']) ? (int) $validated['account_id'] : null,
            'selectedProductId' => isset($validated['product_id']) ? (int) $validated['product_id'] : null,
            'selectedExpenseId' => isset($validated['expense_id']) ? (int) $validated['expense_id'] : null,
            'selectedCustomerId' => isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
            'selectedSupplierId' => isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null,
            'printMode' => $request->boolean('print'),
        ];

        if ($request->boolean('print')) {
            return view('reports_print', $viewData);
        }

        return view('reports', $viewData);
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

    private function resolveReportRange(string $period, ?string $dateFrom, ?string $dateTo): array
    {
        return match ($period) {
            'quarterly' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'yearly' => [now()->startOfYear(), now()->endOfYear()],
            'custom' => [Carbon::parse((string) $dateFrom)->startOfDay(), Carbon::parse((string) $dateTo)->endOfDay()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    private function expenseReportQuery(int $companyId, array $filters)
    {
        return Expense::with(['expenseAccount', 'paymentAccount', 'creator'])
            ->where('company_id', $companyId)
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                $search = trim((string) $filters['search']);

                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('reference', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('expense_number', 'like', '%' . $search . '%');
                });
            })
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('expense_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('expense_date', '<=', $filters['date_to']))
            ->when(! empty($filters['expense_account_id']), fn ($query) => $query->where('expense_account_id', $filters['expense_account_id']))
            ->when(! empty($filters['expense_id']), fn ($query) => $query->where('id', $filters['expense_id']));
    }

    private function reportSummaryStats(int $companyId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $revenueQuery = Invoice::where('company_id', $companyId)
            ->whereIn('status', ['sent', 'partial', 'paid'])
            ->whereBetween('invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);
        $purchaseQuery = Purchase::where('company_id', $companyId)
            ->whereIn('status', ['approved', 'partial', 'paid'])
            ->whereBetween('purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);
        $expenseQuery = Expense::where('company_id', $companyId)
            ->whereBetween('expense_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        $totalRevenue = (float) $revenueQuery->sum('total');
        $totalExpenses = (float) $purchaseQuery->sum('total') + (float) $expenseQuery->sum('total');

        return [
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_profit' => $totalRevenue - $totalExpenses,
            'cash_flow' => $totalRevenue - $totalExpenses,
            'avg_order_value' => (float) Invoice::where('company_id', $companyId)
                ->whereBetween('invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->avg('total'),
            'total_customers' => Customer::where('company_id', $companyId)->count(),
            'inventory_value' => (float) Product::forCompany($companyId)->sum(DB::raw('stock_quantity * cost_price')),
            'outstanding_receivables' => (float) Invoice::where('company_id', $companyId)->sum('balance_due'),
        ];
    }

    private function buildReportData(Company $company, string $reportType, array $filters, Carbon $dateFrom, Carbon $dateTo, array $reportTypes): array
    {
        $report = match ($reportType) {
            'account_balances' => $this->accountBalancesReport($company, $filters, $dateFrom, $dateTo),
            'product_sales' => $this->productSalesReport($company, $filters, $dateFrom, $dateTo),
            'expense_details' => $this->expenseDetailsReport($company, $filters, $dateFrom, $dateTo),
            'receivables' => $this->receivablesReport($company, $filters, $dateFrom, $dateTo),
            'payables' => $this->payablesReport($company, $filters, $dateFrom, $dateTo),
            default => $this->incomeStatementReport($company, $dateFrom, $dateTo),
        };

        $report['type'] = $reportType;
        $report['description'] = $reportTypes[$reportType]['description'];
        $report['date_range_label'] = sprintf('من %s إلى %s', $dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d'));

        return $report;
    }

    private function incomeStatementReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $revenue = (float) Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partial', 'paid'])
            ->whereBetween('invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('total');
        $purchases = (float) Purchase::where('company_id', $company->id)
            ->whereIn('status', ['approved', 'partial', 'paid'])
            ->whereBetween('purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('total');
        $expenses = (float) Expense::where('company_id', $company->id)
            ->whereBetween('expense_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('total');
        $netProfit = $revenue - $purchases - $expenses;

        $rows = collect([
            ['label' => 'إجمالي الإيرادات', 'value' => $revenue, 'meta' => 'فواتير المبيعات المعتمدة'],
            ['label' => 'إجمالي المشتريات', 'value' => $purchases, 'meta' => 'المشتريات المعتمدة خلال الفترة'],
            ['label' => 'إجمالي المصروفات', 'value' => $expenses, 'meta' => 'المصروفات المسجلة خلال الفترة'],
            ['label' => 'صافي الربح', 'value' => $netProfit, 'meta' => 'الإيرادات - المشتريات - المصروفات'],
        ]);

        return [
            'title' => 'قائمة الدخل',
            'rows' => $rows,
            'chart' => [
                'type' => 'bar',
                'labels' => $rows->pluck('label')->values(),
                'values' => $rows->pluck('value')->map(fn ($value) => round((float) $value, 2))->values(),
            ],
            'highlights' => [
                ['label' => 'الإيرادات', 'value' => $revenue],
                ['label' => 'المصروفات الكلية', 'value' => $purchases + $expenses],
                ['label' => 'صافي الربح', 'value' => $netProfit],
            ],
            'empty_message' => 'لا توجد بيانات كافية للفترة المختارة.',
        ];
    }

    private function accountBalancesReport(Company $company, array $filters, Carbon $dateFrom, Carbon $dateTo): array
    {
        $selectedAccountId = isset($filters['account_id']) ? (int) $filters['account_id'] : null;
        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.company_id', $company->id)
            ->whereBetween('journal_entries.entry_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($selectedAccountId, fn ($query) => $query->where('accounts.id', $selectedAccountId))
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.account_type')
            ->selectRaw('accounts.id, accounts.code, accounts.name, accounts.account_type, SUM(journal_lines.debit) as debit_total, SUM(journal_lines.credit) as credit_total')
            ->orderBy('accounts.code')
            ->get()
            ->map(function ($row) {
                $isDebitAccount = in_array($row->account_type, ['asset', 'expense', 'cogs'], true);
                $balance = $isDebitAccount
                    ? ((float) $row->debit_total - (float) $row->credit_total)
                    : ((float) $row->credit_total - (float) $row->debit_total);

                return [
                    'label' => trim($row->code . ' - ' . $row->name),
                    'value' => $balance,
                    'meta' => sprintf('مدين: %s | دائن: %s', number_format((float) $row->debit_total, 2), number_format((float) $row->credit_total, 2)),
                ];
            });

        return [
            'title' => $selectedAccountId ? 'تقرير حساب محدد' : 'أرصدة الحسابات',
            'rows' => $rows,
            'chart' => [
                'type' => 'bar',
                'labels' => $rows->pluck('label')->take(8)->values(),
                'values' => $rows->pluck('value')->take(8)->map(fn ($value) => round((float) $value, 2))->values(),
            ],
            'highlights' => [
                ['label' => 'عدد الحسابات', 'value' => $rows->count()],
                ['label' => 'إجمالي الأرصدة', 'value' => $rows->sum('value')],
            ],
            'empty_message' => 'لا توجد حركات حسابات خلال الفترة المحددة.',
        ];
    }

    private function productSalesReport(Company $company, array $filters, Carbon $dateFrom, Carbon $dateTo): array
    {
        $selectedProductId = isset($filters['product_id']) ? (int) $filters['product_id'] : null;
        $rows = Invoice::query()
            ->join('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->where('invoices.company_id', $company->id)
            ->whereIn('invoices.status', ['sent', 'partial', 'paid'])
            ->whereBetween('invoices.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($selectedProductId, fn ($query) => $query->where('products.id', $selectedProductId))
            ->groupBy('products.id', 'products.name', 'invoice_items.description')
            ->selectRaw('products.id, COALESCE(products.name, invoice_items.description) as label, SUM(invoice_items.quantity) as quantity_sold, SUM(invoice_items.total) as total_sales')
            ->orderByDesc('total_sales')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'value' => (float) $row->total_sales,
                'meta' => 'الكمية المباعة: ' . number_format((float) $row->quantity_sold, 2),
            ]);

        return [
            'title' => $selectedProductId ? 'تقرير منتج محدد' : 'مبيعات المنتجات',
            'rows' => $rows,
            'chart' => [
                'type' => 'bar',
                'labels' => $rows->pluck('label')->take(8)->values(),
                'values' => $rows->pluck('value')->take(8)->map(fn ($value) => round((float) $value, 2))->values(),
            ],
            'highlights' => [
                ['label' => 'عدد المنتجات', 'value' => $rows->count()],
                ['label' => 'إجمالي المبيعات', 'value' => $rows->sum('value')],
            ],
            'empty_message' => 'لا توجد مبيعات منتجات وفق المعايير المختارة.',
        ];
    }

    private function expenseDetailsReport(Company $company, array $filters, Carbon $dateFrom, Carbon $dateTo): array
    {
        $selectedExpenseId = isset($filters['expense_id']) ? (int) $filters['expense_id'] : null;
        $rows = Expense::with('expenseAccount')
            ->where('company_id', $company->id)
            ->whereBetween('expense_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->when($selectedExpenseId, fn ($query) => $query->where('id', $selectedExpenseId))
            ->orderByDesc('expense_date')
            ->get()
            ->map(fn (Expense $expense) => [
                'label' => $expense->name ?: ($expense->reference ?: 'مصروف #' . $expense->id),
                'value' => (float) $expense->total,
                'meta' => trim(implode(' | ', array_filter([
                    $expense->expense_date?->format('Y-m-d'),
                    $expense->reference,
                    $expense->expenseAccount?->name,
                ]))),
            ]);

        return [
            'title' => $selectedExpenseId ? 'تفاصيل مصروف محدد' : 'تفاصيل المصروفات',
            'rows' => $rows,
            'chart' => [
                'type' => 'bar',
                'labels' => $rows->pluck('label')->take(8)->values(),
                'values' => $rows->pluck('value')->take(8)->map(fn ($value) => round((float) $value, 2))->values(),
            ],
            'highlights' => [
                ['label' => 'عدد المصروفات', 'value' => $rows->count()],
                ['label' => 'إجمالي المصروفات', 'value' => $rows->sum('value')],
            ],
            'empty_message' => 'لا توجد مصروفات مطابقة للمعايير المختارة.',
        ];
    }

    private function receivablesReport(Company $company, array $filters, Carbon $dateFrom, Carbon $dateTo): array
    {
        $selectedCustomerId = isset($filters['customer_id']) ? (int) $filters['customer_id'] : null;
        $rows = Invoice::query()
            ->join('customers', 'customers.id', '=', 'invoices.customer_id')
            ->where('invoices.company_id', $company->id)
            ->whereBetween('invoices.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('invoices.balance_due', '>', 0)
            ->when($selectedCustomerId, fn ($query) => $query->where('customers.id', $selectedCustomerId))
            ->groupBy('customers.id', 'customers.name')
            ->selectRaw('customers.name as label, SUM(invoices.balance_due) as balance_due, COUNT(invoices.id) as invoice_count')
            ->orderByDesc('balance_due')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'value' => (float) $row->balance_due,
                'meta' => 'عدد الفواتير المفتوحة: ' . $row->invoice_count,
            ]);

        return [
            'title' => $selectedCustomerId ? 'ذمم عميل محدد' : 'الذمم المدينة',
            'rows' => $rows,
            'chart' => [
                'type' => 'bar',
                'labels' => $rows->pluck('label')->take(8)->values(),
                'values' => $rows->pluck('value')->take(8)->map(fn ($value) => round((float) $value, 2))->values(),
            ],
            'highlights' => [
                ['label' => 'عدد العملاء', 'value' => $rows->count()],
                ['label' => 'إجمالي الذمم', 'value' => $rows->sum('value')],
            ],
            'empty_message' => 'لا توجد ذمم مدينة ضمن المعايير المختارة.',
        ];
    }

    private function payablesReport(Company $company, array $filters, Carbon $dateFrom, Carbon $dateTo): array
    {
        $selectedSupplierId = isset($filters['supplier_id']) ? (int) $filters['supplier_id'] : null;
        $rows = Purchase::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->where('purchases.company_id', $company->id)
            ->whereBetween('purchases.purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('purchases.balance_due', '>', 0)
            ->when($selectedSupplierId, fn ($query) => $query->where('suppliers.id', $selectedSupplierId))
            ->groupBy('suppliers.id', 'suppliers.name')
            ->selectRaw('suppliers.name as label, SUM(purchases.balance_due) as balance_due, COUNT(purchases.id) as purchase_count')
            ->orderByDesc('balance_due')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'value' => (float) $row->balance_due,
                'meta' => 'عدد المشتريات المفتوحة: ' . $row->purchase_count,
            ]);

        return [
            'title' => $selectedSupplierId ? 'ذمم مورد محدد' : 'الذمم الدائنة',
            'rows' => $rows,
            'chart' => [
                'type' => 'bar',
                'labels' => $rows->pluck('label')->take(8)->values(),
                'values' => $rows->pluck('value')->take(8)->map(fn ($value) => round((float) $value, 2))->values(),
            ],
            'highlights' => [
                ['label' => 'عدد الموردين', 'value' => $rows->count()],
                ['label' => 'إجمالي الذمم', 'value' => $rows->sum('value')],
            ],
            'empty_message' => 'لا توجد ذمم دائنة ضمن المعايير المختارة.',
        ];
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

    private function validateAccountData(Request $request, int $companyId): array
    {
        $uniqueCodeRule = Rule::unique('accounts', 'code')
            ->where(fn ($query) => $query->where('company_id', $companyId));

        return $request->validate([
            'code' => ['required', 'string', 'max:20', $uniqueCodeRule],
            'name' => ['required', 'string', 'max:200'],
            'name_ar' => ['nullable', 'string', 'max:200'],
            'account_type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense', 'cogs'])],
            'parent_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function suggestedParentIds(Collection $accounts): array
    {
        $rootCodes = [
            'asset' => '1000',
            'liability' => '2000',
            'equity' => '3000',
            'revenue' => '4000',
            'cogs' => '5000',
            'expense' => '6000',
        ];

        $accountsByCode = $accounts->keyBy('code');
        $suggestions = [];

        foreach ($rootCodes as $type => $code) {
            $suggestions[$type] = $accountsByCode->get($code)?->id;
        }

        return $suggestions;
    }

    private function hasAccountFilters(array $filters): bool
    {
        return $filters['search'] !== ''
            || $filters['account_type'] !== ''
            || $filters['min_balance'] !== ''
            || $filters['max_balance'] !== '';
    }

    private function filterAccounts(Collection $accounts, array $filters): Collection
    {
        return $accounts->filter(function (Account $account) use ($filters) {
            if ($filters['search'] !== '') {
                $search = mb_strtolower($filters['search']);
                $haystacks = [
                    mb_strtolower($account->code),
                    mb_strtolower($account->name),
                    mb_strtolower((string) $account->name_ar),
                ];

                $matchesSearch = collect($haystacks)->contains(fn (string $value) => str_contains($value, $search));

                if (! $matchesSearch) {
                    return false;
                }
            }

            if ($filters['account_type'] !== '' && $account->account_type !== $filters['account_type']) {
                return false;
            }

            $balance = (float) $account->balance;

            if ($filters['min_balance'] !== '' && $balance < (float) $filters['min_balance']) {
                return false;
            }

            if ($filters['max_balance'] !== '' && $balance > (float) $filters['max_balance']) {
                return false;
            }

            return true;
        })->values();
    }

    private function buildAccountTree(Collection $accounts): Collection
    {
        return $this->nestAccounts($accounts, null);
    }

    private function buildFilteredAccountTree(Collection $allAccounts, Collection $matchingAccounts): Collection
    {
        $includedIds = [];
        $accountsById = $allAccounts->keyBy('id');

        foreach ($matchingAccounts as $account) {
            $current = $account;

            while ($current) {
                $includedIds[$current->id] = true;
                $current = $current->parent_id ? $accountsById->get($current->parent_id) : null;
            }
        }

        return $this->nestAccounts($allAccounts->whereIn('id', array_keys($includedIds))->values(), null);
    }

    private function nestAccounts(Collection $accounts, ?int $parentId): Collection
    {
        return $accounts
            ->where('parent_id', $parentId)
            ->sortBy('code')
            ->values()
            ->map(function (Account $account) use ($accounts) {
                $account->setRelation('children', $this->nestAccounts($accounts, $account->id));

                return $account;
            });
    }

    private function suggestedParentIdForType(int $companyId, string $type): ?int
    {
        $rootCodes = [
            'asset' => '1000',
            'liability' => '2000',
            'equity' => '3000',
            'revenue' => '4000',
            'cogs' => '5000',
            'expense' => '6000',
        ];

        $code = $rootCodes[$type] ?? null;

        if (! $code) {
            return null;
        }

        return Account::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->value('id');
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
