<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\SalesChannel;
use App\Models\Supplier;
use App\Models\TaxSetting;
use App\Models\User;
use App\Support\AccountingService;
use App\Support\ChartOfAccountsSynchronizer;
use App\Support\DocumentNumberGenerator;
use App\Support\InventoryMovementService;
use App\Support\PaymentSyncService;
use App\Support\ReferenceGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingPageController extends Controller
{
    public function __construct(
        private readonly AccountingService $accountingService,
        private readonly ChartOfAccountsSynchronizer $chartOfAccountsSynchronizer,
        private readonly DocumentNumberGenerator $documentNumberGenerator,
        private readonly InventoryMovementService $inventoryMovementService,
        private readonly PaymentSyncService $paymentSyncService,
        private readonly ReferenceGenerator $referenceGenerator,
    ) {
    }

    public function invoices(Request $request): View
    {
        $company = $this->company($request);
        $statusFilter = $request->string('status')->toString() ?: 'all';
        $sortDirection = $this->sortDirection($request);
        $tabs = [
            'all' => ['label' => 'جميع المبيعات', 'icon' => 'fa-list'],
            'draft' => ['label' => 'مسودة', 'icon' => 'fa-edit'],
            'sent' => ['label' => 'مرسلة', 'icon' => 'fa-paper-plane'],
            'paid' => ['label' => 'مدفوعة', 'icon' => 'fa-check-circle'],
            'overdue' => ['label' => 'متأخرة', 'icon' => 'fa-exclamation-triangle'],
        ];

        $invoices = Invoice::with('customer')
            ->where('company_id', $company->id)
            ->when($statusFilter !== 'all', function ($query) use ($statusFilter) {
                return match ($statusFilter) {
                    'draft' => $query->where('status', 'draft'),
                    'sent' => $query->where('status', 'sent')->whereColumn('paid_amount', '<', 'total'),
                    'paid' => $query->whereColumn('paid_amount', '>=', 'total'),
                    'overdue' => $query->whereDate('due_date', '<', now()->toDateString())
                        ->whereColumn('paid_amount', '<', 'total'),
                    default => $query,
                };
            })
            ->orderBy('invoice_date', $sortDirection)
            ->orderBy('id', $sortDirection)
            ->get();

        $customers = Customer::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('invoices', compact('company', 'invoices', 'statusFilter', 'tabs', 'customers', 'sortDirection'));
    }

    public function invoiceCreate(Request $request): View
    {
        $company = $this->company($request);

        return $this->invoiceFormView($company, $request->user());
    }

    public function invoiceEdit(Request $request, Invoice $invoice): View
    {
        $company = $this->company($request);
        abort_if((int) $invoice->company_id !== (int) $company->id, 404);

        return $this->invoiceFormView($company, $request->user(), $invoice);
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $company = $this->company($request);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $validated = $this->validateInvoiceData($request, $company->id);
        $salesContext = $this->resolveInvoiceSalesContext($user, $company->id);
        $stockRequirements = $this->invoiceStockRequirementsFromValidated($validated);

        $invoice = DB::transaction(function () use ($company, $validated, $stockRequirements, $user, $request, $salesContext) {
            $totals = $this->calculateInvoiceTotals($validated);
            $paymentData = $this->resolveInvoicePaymentData($validated, $totals);
            $attachmentPath = $this->handleInvoiceAttachmentUpload($request);

            $this->ensureInvoiceCanBePosted($validated, $totals);
            $this->ensureInvoiceStockAvailability($company->id, $stockRequirements);

            if ($this->shouldConsumeInvoiceStock($validated['status'] ?? 'sent')) {
                $this->consumeInvoiceStock($company->id, $stockRequirements);
            }

            $invoice = Invoice::create([
                'invoice_number' => $this->documentNumberGenerator->nextInvoiceNumber($company->id),
                'customer_id' => $validated['customer_id'],
                'employee_id' => $salesContext['employee_id'],
                'user_id' => $salesContext['user_id'],
                'company_id' => $company->id,
                'branch_id' => $salesContext['branch_id'],
                'sales_channel_id' => $validated['sales_channel_id'],
                'payment_account_id' => $validated['payment_account_id'] ?? null,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'paid_amount' => $paymentData['paid_amount'],
                'balance_due' => $paymentData['balance_due'],
                'status' => $validated['status'] ?? 'sent',
                'payment_status' => $paymentData['payment_status'],
                'attachment_path' => $attachmentPath,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'currency' => $company->currency,
                'exchange_rate' => 1,
            ]);

            $this->syncInvoiceItems($invoice, $validated);

            $freshInvoice = $invoice->fresh(['items.product', 'customer', 'paymentAccount']);
            $journalEntry = null;

            if (($validated['status'] ?? 'sent') !== 'draft') {
                $journalEntry = $this->accountingService->syncInvoiceEntry($freshInvoice, $user);
                $this->inventoryMovementService->syncInvoice($freshInvoice);
            } else {
                $this->inventoryMovementService->deleteForSource($invoice);
            }

            $this->paymentSyncService->syncInvoicePayment($freshInvoice, $journalEntry);

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)->with('status', 'تم إنشاء الفاتورة وربطها بقيد محاسبي آلي.');
    }

    public function updateInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $invoice->company_id !== (int) $company->id, 404);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $validated = $this->validateInvoiceData($request, $company->id);
        $stockRequirements = $this->invoiceStockRequirementsFromValidated($validated);
        $nextStatus = (string) ($validated['status'] ?? $invoice->status);
        $salesContext = $this->resolveInvoiceOwnershipForUpdate($invoice, $user, $company->id);

        DB::transaction(function () use ($invoice, $validated, $stockRequirements, $nextStatus, $company, $user, $request, $salesContext) {
            $invoice->loadMissing(['items.product', 'customer']);

            if ($this->shouldConsumeInvoiceStock((string) $invoice->status)) {
                $this->restoreInvoiceStock($company->id, $this->invoiceStockRequirementsFromItems($invoice->items));
            }

            $totals = $this->calculateInvoiceTotals($validated);
            $paymentData = $this->resolveInvoicePaymentData($validated, $totals);
            $attachmentPath = $this->handleInvoiceAttachmentUpload($request, $invoice);

            $this->ensureInvoiceCanBePosted($validated, $totals);
            $this->ensureInvoiceStockAvailability($company->id, $stockRequirements);

            if ($this->shouldConsumeInvoiceStock($nextStatus)) {
                $this->consumeInvoiceStock($company->id, $stockRequirements);
            }

            $invoice->update([
                'customer_id' => $validated['customer_id'],
                'employee_id' => $salesContext['employee_id'],
                'user_id' => $salesContext['user_id'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'branch_id' => $salesContext['branch_id'],
                'sales_channel_id' => $validated['sales_channel_id'],
                'payment_account_id' => $validated['payment_account_id'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'paid_amount' => $paymentData['paid_amount'],
                'balance_due' => $paymentData['balance_due'],
                'status' => $nextStatus,
                'payment_status' => $paymentData['payment_status'],
                'attachment_path' => $attachmentPath,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'currency' => $company->currency,
                'exchange_rate' => 1,
            ]);

            $this->syncInvoiceItems($invoice, $validated);

            $freshInvoice = $invoice->fresh(['items.product', 'customer', 'paymentAccount']);
            $journalEntry = null;

            if ($this->shouldConsumeInvoiceStock($nextStatus)) {
                $journalEntry = $this->accountingService->syncInvoiceEntry($freshInvoice, $user);
                $this->inventoryMovementService->syncInvoice($freshInvoice);
            } else {
                $this->accountingService->deleteAutomaticEntriesForSource($invoice);
                $this->inventoryMovementService->deleteForSource($invoice);
            }

            $this->paymentSyncService->syncInvoicePayment($freshInvoice, $journalEntry);
        });

        return redirect()->route('invoices.show', $invoice)->with('status', 'تم تحديث الفاتورة وتعديل المخزون المرتبط بها بنجاح.');
    }

    public function destroyInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $invoice->company_id !== (int) $company->id, 404);

        if ((float) $invoice->paid_amount > 0) {
            return redirect()->route('invoices.show', $invoice)->with('error', 'لا يمكن حذف فاتورة تم تسجيل دفعات عليها.');
        }

        DB::transaction(function () use ($invoice, $company) {
            $invoice->loadMissing('items.product');

            if ($this->shouldConsumeInvoiceStock((string) $invoice->status)) {
                $this->restoreInvoiceStock($company->id, $this->invoiceStockRequirementsFromItems($invoice->items));
            }

            if ($invoice->attachment_path) {
                Storage::disk('public')->delete($invoice->attachment_path);
            }

            $this->paymentSyncService->deleteInvoicePayments($invoice);
            $this->inventoryMovementService->deleteForSource($invoice);
            $this->accountingService->deleteAutomaticEntriesForSource($invoice);
            $invoice->delete();
        });

        return redirect()->route('invoices')->with('status', 'تم حذف الفاتورة وعكس المخزون والقيد المحاسبي المرتبط بها.');
    }

    public function showInvoiceAttachment(Request $request, Invoice $invoice): StreamedResponse
    {
        $company = $this->company($request);
        abort_if((int) $invoice->company_id !== (int) $company->id, 404);
        abort_if(!$invoice->attachment_path, 404);
        abort_if(!Storage::disk('public')->exists($invoice->attachment_path), 404);

        return Storage::disk('public')->response($invoice->attachment_path);
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

    public function invoicePdf(Request $request, Invoice $invoice): View
    {
        return $this->invoiceShow($request, $invoice);
    }

    public function sendInvoice(Request $request, Invoice $invoice): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $invoice->company_id !== (int) $company->id, 404);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($invoice->status !== 'draft') {
            return redirect()->route('invoices')->with('status', 'الفاتورة ليست في حالة مسودة.');
        }

        if ((float) $invoice->total <= 0) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('error', 'لا يمكن اعتماد فاتورة بإجمالي صفر. عدل البنود أولاً.');
        }

        DB::transaction(function () use ($invoice, $company, $user) {
            $invoice->loadMissing('items.product');

            $stockRequirements = $this->invoiceStockRequirementsFromItems($invoice->items);

            $this->ensureInvoiceStockAvailability($company->id, $stockRequirements);
            $this->consumeInvoiceStock($company->id, $stockRequirements);

            $invoice->update(['status' => 'sent']);
            $freshInvoice = $invoice->fresh(['items.product', 'customer', 'paymentAccount']);
            $journalEntry = $this->accountingService->syncInvoiceEntry($freshInvoice, $user);
            $this->inventoryMovementService->syncInvoice($freshInvoice);
            $this->paymentSyncService->syncInvoicePayment($freshInvoice, $journalEntry);
        });

        return redirect()->route('invoices')->with('status', 'تم اعتماد الفاتورة وإرسالها بنجاح.');
    }

    public function purchases(Request $request): View
    {
        $company = $this->company($request);
        $statusFilter = $request->string('status')->toString();
        $supplierFilter = $request->string('supplier_id')->toString();
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();
        $sortDirection = $this->sortDirection($request);
        $purchases = Purchase::with([
            'supplier',
            'items.product',
            'payments' => fn($query) => $query
                ->where('payment_category', 'purchase_payment')
                ->orderBy('payment_date', $sortDirection)
                ->orderBy('id', $sortDirection),
        ])
            ->where('company_id', $company->id)
            ->when($statusFilter !== '', fn($query) => $query->where('status', $statusFilter))
            ->when($supplierFilter !== '', fn($query) => $query->where('supplier_id', $supplierFilter))
            ->when($dateFrom !== '', fn($query) => $query->whereDate('purchase_date', '>=', $dateFrom))
            ->when($dateTo !== '', fn($query) => $query->whereDate('purchase_date', '<=', $dateTo))
            ->orderBy('purchase_date', $sortDirection)
            ->orderBy('id', $sortDirection)
            ->get();

        $suppliers = Supplier::where('company_id', $company->id)->orderBy('name')->get();
        $products = Product::forCompany($company->id)->active()->orderBy('name')->get();
        $paymentAccounts = $this->directPaymentAccounts($company->id);
        $pendingPurchasesCount = $purchases->where('status', 'pending')->count();
        $paidPurchasesCount = $purchases->whereIn('status', ['approved', 'paid'])->count();

        return view('purchases', [
            'company' => $company,
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'products' => $products,
            'paymentAccounts' => $paymentAccounts,
            'statusFilter' => $statusFilter,
            'supplierFilter' => $supplierFilter,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'sortDirection' => $sortDirection,
            'pendingPurchasesCount' => $pendingPurchasesCount,
            'paidPurchasesCount' => $paidPurchasesCount,
        ]);
    }

    public function storePurchase(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validatePurchaseData($request, $company->id);
        $paymentData = $this->resolvePurchasePaymentData($validated, $this->calculatePurchaseTotals($validated));
        $paymentStatus = $paymentData['payment_status'];
        $paymentDate = $paymentStatus === 'pending' ? null : ($validated['payment_date'] ?? null);

        /** @var \App\Models\User $user */
        $user = $request->user();

        DB::transaction(function () use ($company, $validated, $user, $request, $paymentData, $paymentStatus, $paymentDate) {
            $totals = $this->calculatePurchaseTotals($validated);
            $attachmentPath = $this->handlePurchaseAttachmentUpload($request);

            $purchase = Purchase::create([
                'purchase_number' => $this->documentNumberGenerator->nextPurchaseNumber($company->id),
                'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                'attachment_path' => $attachmentPath,
                'supplier_id' => $validated['supplier_id'],
                'company_id' => $company->id,
                'purchase_date' => $validated['purchase_date'],
                'due_date' => $validated['due_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'paid_amount' => $paymentData['paid_amount'],
                'balance_due' => $paymentData['balance_due'],
                'status' => $validated['status'] ?? 'draft',
                'payment_status' => $paymentStatus,
                'payment_account_id' => $validated['payment_account_id'] ?? null,
                'payment_date' => $paymentDate,
                'notes' => $validated['notes'] ?? null,
                'currency' => $company->currency,
                'exchange_rate' => 1,
            ]);

            $this->syncPurchaseItems($purchase, $validated);

            if ($this->shouldReceivePurchaseStock((string) $purchase->status)) {
                $this->applyPurchaseStock($company->id, $this->purchaseStockRequirementsFromValidated($validated));
            }

            $freshPurchase = $purchase->fresh(['items.product', 'supplier', 'paymentAccount']);
            $journalEntry = $this->accountingService->syncPurchaseEntry($freshPurchase, $user);
            $this->paymentSyncService->syncPurchasePayment($freshPurchase, $journalEntry);

            if ($this->shouldReceivePurchaseStock((string) $freshPurchase->status)) {
                $this->inventoryMovementService->syncPurchase($freshPurchase);
            } else {
                $this->inventoryMovementService->deleteForSource($purchase);
            }
        });

        return redirect()->route('purchases')->with('status', 'تم إنشاء طلب الشراء بنجاح.');
    }

    public function createPurchase(Request $request): View
    {
        $company = $this->company($request);
        $suppliers = Supplier::where('company_id', $company->id)->orderBy('name')->get();
        $products = Product::forCompany($company->id)->active()->orderBy('name')->get();
        $paymentAccounts = $this->directPaymentAccounts($company->id);

        return view('purchases.create', [
            'company' => $company,
            'suppliers' => $suppliers,
            'products' => $products,
            'paymentAccounts' => $paymentAccounts,
        ]);
    }

    public function editPurchase(Request $request, Purchase $purchase): View
    {
        $company = $this->company($request);
        abort_if((int) $purchase->company_id !== (int) $company->id, 404);

        $purchase->load(['items.product', 'supplier', 'paymentAccount']);
        $suppliers = Supplier::where('company_id', $company->id)->orderBy('name')->get();
        $products = Product::forCompany($company->id)->active()->orderBy('name')->get();
        $paymentAccounts = $this->directPaymentAccounts($company->id);

        return view('purchases.edit', [
            'company' => $company,
            'purchase' => $purchase,
            'suppliers' => $suppliers,
            'products' => $products,
            'paymentAccounts' => $paymentAccounts,
        ]);
    }

    public function updatePurchase(Request $request, Purchase $purchase): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $purchase->company_id !== (int) $company->id, 404);

        $validated = $this->validatePurchaseData($request, $company->id, $purchase);
        $existingRecordedPayments = $purchase->payments()
            ->where('payment_category', 'purchase_payment')
            ->count();
        $paymentData = $this->resolvePurchasePaymentData(
            $validated,
            $this->calculatePurchaseTotals($validated),
            $existingRecordedPayments > 1 ? (float) $purchase->paid_amount : null,
        );
        $paymentStatus = $paymentData['payment_status'] ?? $purchase->payment_status;
        $paymentDate = $paymentStatus === 'pending' ? null : ($validated['payment_date'] ?? null);

        /** @var \App\Models\User $user */
        $user = $request->user();

        DB::transaction(function () use ($purchase, $validated, $company, $user, $request, $paymentData, $paymentStatus, $paymentDate) {
            $purchase->loadMissing(['items.product']);
            $hadAppliedStock = $this->inventoryMovementService->hasMovementsForSource($purchase);

            if ($this->shouldReceivePurchaseStock((string) $purchase->status) && $hadAppliedStock) {
                $this->reversePurchaseStock($company->id, $this->purchaseStockRequirementsFromItems($purchase->items));
            }

            $totals = $this->calculatePurchaseTotals($validated);
            $attachmentPath = $this->handlePurchaseAttachmentUpload($request, $purchase);

            $purchase->update([
                'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                'attachment_path' => $attachmentPath,
                'supplier_id' => $validated['supplier_id'],
                'purchase_date' => $validated['purchase_date'],
                'due_date' => $validated['due_date'] ?? null,
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'total' => $totals['total'],
                'paid_amount' => $paymentData['paid_amount'],
                'balance_due' => $paymentData['balance_due'],
                'status' => $validated['status'] ?? $purchase->status,
                'payment_status' => $paymentStatus,
                'payment_account_id' => $validated['payment_account_id'] ?? null,
                'payment_date' => $paymentDate,
                'notes' => $validated['notes'] ?? null,
                'currency' => $company->currency,
            ]);

            $this->syncPurchaseItems($purchase, $validated);

            if ($this->shouldReceivePurchaseStock((string) $purchase->status)) {
                $this->applyPurchaseStock($company->id, $this->purchaseStockRequirementsFromValidated($validated));
            }

            $freshPurchase = $purchase->fresh(['items.product', 'supplier', 'paymentAccount']);
            $journalEntry = $this->accountingService->syncPurchaseEntry($freshPurchase, $user);
            $this->paymentSyncService->syncPurchasePayment($freshPurchase, $journalEntry);

            if ($this->shouldReceivePurchaseStock((string) $freshPurchase->status)) {
                $this->inventoryMovementService->syncPurchase($freshPurchase);
            } else {
                $this->inventoryMovementService->deleteForSource($purchase);
            }
        });

        return redirect()->route('purchases')->with('status', 'تم تحديث طلب الشراء بنجاح.');
    }

    public function approvePurchase(Request $request, Purchase $purchase): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $purchase->company_id !== (int) $company->id, 404);

        if (!in_array($purchase->status, ['draft', 'pending'], true)) {
            return redirect()->route('purchases')->with('status', 'لا يمكن اعتماد طلب الشراء في حالته الحالية.');
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        DB::transaction(function () use ($purchase, $user) {
            $purchase->loadMissing(['items.product', 'supplier']);

            if (!$this->inventoryMovementService->hasMovementsForSource($purchase)) {
                $this->applyPurchaseStock((int) $purchase->company_id, $this->purchaseStockRequirementsFromItems($purchase->items));
            }

            $purchase->update([
                'status' => 'approved',
            ]);

            $freshPurchase = $purchase->fresh(['items.product', 'supplier', 'paymentAccount']);
            $journalEntry = $this->accountingService->syncPurchaseEntry($freshPurchase, $user);
            $this->paymentSyncService->syncPurchasePayment($freshPurchase, $journalEntry);
            $this->inventoryMovementService->syncPurchase($freshPurchase);
        });

        return redirect()->route('purchases')->with('status', 'تم اعتماد طلب الشراء بنجاح.');
    }

    public function storePurchasePayment(Request $request, Purchase $purchase): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $purchase->company_id !== (int) $company->id, 404);

        if (in_array((string) $purchase->status, ['draft', 'cancelled'], true)) {
            return redirect()->route('purchases')->with('error', 'لا يمكن تسجيل دفعة على طلب شراء مسودة أو ملغي.');
        }

        if ((float) $purchase->balance_due <= 0) {
            return redirect()->route('purchases')->with('error', 'لا يوجد رصيد متبق على طلب الشراء المحدد.');
        }

        $validated = $request->validate([
            'purchase_modal' => ['nullable', 'string'],
            'payment_amount' => ['required', 'numeric', 'min:0.01', 'max:' . max((float) $purchase->balance_due, 0.01)],
            'payment_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn($query) => $query
                    ->where('company_id', $company->id)
                    ->where('allows_direct_transactions', true)
                    ->where('is_active', true)),
            ],
            'payment_date' => ['required', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'payment_amount.max' => 'مبلغ الدفعة أكبر من الرصيد المتبقي على طلب الشراء.',
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $paymentAmount = round((float) $validated['payment_amount'], 2);
        $paymentDate = Carbon::parse($validated['payment_date'])->toDateString();
        $paymentReference = trim((string) ($validated['payment_reference'] ?? ''));

        if ($paymentReference === '') {
            $paymentReference = $this->referenceGenerator->nextPurchasePaymentReference($company->id);
        }

        DB::transaction(function () use ($purchase, $paymentAmount, $paymentDate, $paymentReference, $validated, $user) {
            $lockedPurchase = Purchase::query()
                ->with('supplier')
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();

            $paymentAccount = Account::query()
                ->where('company_id', $lockedPurchase->company_id)
                ->where('allows_direct_transactions', true)
                ->where('is_active', true)
                ->findOrFail($validated['payment_account_id']);

            $appliedAmount = min($paymentAmount, round((float) $lockedPurchase->balance_due, 2));
            $updatedPaidAmount = round((float) $lockedPurchase->paid_amount + $appliedAmount, 2);
            $updatedBalance = round(max((float) $lockedPurchase->total - $updatedPaidAmount, 0), 2);

            $lockedPurchase->update([
                'paid_amount' => $updatedPaidAmount,
                'balance_due' => $updatedBalance,
                'payment_status' => $this->purchasePaymentStatus($updatedPaidAmount, (float) $lockedPurchase->total),
                'status' => $this->purchaseStatusAfterPayment((string) $lockedPurchase->status, $updatedPaidAmount, $updatedBalance),
                'payment_account_id' => (int) $paymentAccount->id,
                'payment_date' => $paymentDate,
            ]);

            $journalEntry = $this->accountingService->createSupplierPaymentEntry(
                $lockedPurchase->supplier,
                $appliedAmount,
                $user,
                $paymentReference,
                $paymentAccount,
                $paymentDate,
            );

            $this->paymentSyncService->recordPurchasePayment(
                purchase: $lockedPurchase,
                amount: $appliedAmount,
                paymentDate: $paymentDate,
                reference: $paymentReference,
                entry: $journalEntry,
                paymentAccountId: (int) $paymentAccount->id,
                notes: trim((string) ($validated['payment_notes'] ?? '')) ?: null,
            );
        });

        return redirect()->route('purchases')->with('status', 'تم تسجيل دفعة شراء بمبلغ ' . number_format($paymentAmount, 2) . ' ' . $company->currency . '.');
    }

    public function destroyPurchase(Request $request, Purchase $purchase): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $purchase->company_id !== (int) $company->id, 404);

        if ((float) $purchase->paid_amount > 0) {
            return redirect()->route('purchases')->with('status', 'لا يمكن حذف طلب شراء تم تسجيل دفعات عليه.');
        }

        DB::transaction(function () use ($purchase, $company) {
            $purchase->loadMissing(['items.product']);

            if ($this->shouldReceivePurchaseStock((string) $purchase->status) && $this->inventoryMovementService->hasMovementsForSource($purchase)) {
                $this->reversePurchaseStock($company->id, $this->purchaseStockRequirementsFromItems($purchase->items));
            }

            $this->paymentSyncService->deletePurchasePayments($purchase);
            $this->inventoryMovementService->deleteForSource($purchase);
            $this->accountingService->deleteAutomaticEntriesForSource($purchase);

            if ($purchase->attachment_path) {
                Storage::disk('public')->delete($purchase->attachment_path);
            }

            $purchase->delete();
        });

        return redirect()->route('purchases')->with('status', 'تم حذف طلب الشراء بنجاح.');
    }

    public function purchasePrint(Request $request, Purchase $purchase): View
    {
        $company = $this->company($request);
        abort_if((int) $purchase->company_id !== (int) $company->id, 404);

        $purchase->loadMissing(['supplier', 'items.product']);
        $supplier = $purchase->supplier;
        $companyCountry = $this->countryLabel($company->country_code);
        $supplierCountry = $supplier?->country ?: ($supplier ? $companyCountry : '-');

        return view('purchase_print', [
            'company' => $company,
            'companyCountry' => $companyCountry,
            'supplier' => $supplier,
            'supplierCountry' => $supplierCountry,
            'purchase' => $purchase,
        ]);
    }

    public function showPurchaseAttachment(Request $request, Purchase $purchase): StreamedResponse
    {
        $company = $this->company($request);
        abort_if((int) $purchase->company_id !== (int) $company->id, 404);
        abort_if(!$purchase->attachment_path, 404);
        abort_if(!Storage::disk('public')->exists($purchase->attachment_path), 404);

        return Storage::disk('public')->response($purchase->attachment_path);
    }

    public function customers(Request $request): View
    {
        $company = $this->company($request);
        $companyCountry = $this->countryConfigForCompany($company);
        $companyCities = collect($companyCountry['cities'] ?? []);

        $cityFilter = trim($request->string('city')->toString());
        $statusFilter = $request->string('status')->toString();
        $baseCustomersQuery = Customer::where('company_id', $company->id);
        $customersQuery = (clone $baseCustomersQuery);

        if ($cityFilter !== '') {
            $customersQuery->where('city', $cityFilter);
        }

        if (in_array($statusFilter, ['active', 'inactive'], true)) {
            $customersQuery->where('is_active', $statusFilter === 'active');
        }

        $customers = $customersQuery
            ->orderBy('name')
            ->get()
            ->map(function (Customer $customer) use ($company) {
                $customer->code = 'CUS-' . str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT);
                $customer->balance = (float) $customer->invoices()->sum('balance_due');
                $customer->credit_limit = (float) $customer->credit_limit;
                $customer->currency = $company->currency;

                return $customer;
            });

        $reportCustomers = $baseCustomersQuery->orderBy('name')->get(['id', 'name']);
        $customerFilters = [
            'city' => $cityFilter,
            'status' => $statusFilter,
            'shown' => $customers->count(),
            'total' => (clone $baseCustomersQuery)->count(),
        ];

        return view('customers', compact('company', 'customers', 'companyCountry', 'companyCities', 'reportCustomers', 'customerFilters'));
    }

    public function showCustomer(Request $request, Customer $customer): View
    {
        $company = $this->company($request);
        abort_if((int) $customer->company_id !== (int) $company->id, 404);
        $companyCountry = $this->countryConfigForCompany($company);
        $sortDirection = $this->sortDirection($request);

        $customer->load([
            'invoices' => fn($query) => $query->orderBy('invoice_date', $sortDirection)->orderBy('id', $sortDirection),
        ]);

        $customer->code = $customer->code ?: 'CUS-' . str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT);
        $customer->balance = (float) $customer->invoices->sum('balance_due');
        $customer->invoices_total = (float) $customer->invoices->sum('total');

        return view('customer_show', compact('company', 'customer', 'companyCountry', 'sortDirection'));
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateCustomerData($request, $company->id);

        DB::transaction(function () use ($validated, $company) {
            $customer = Customer::create($this->customerPayload($validated, $company));

            if (!$customer->code) {
                $customer->update([
                    'code' => 'CUS-' . str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT),
                ]);
            }

            $this->chartOfAccountsSynchronizer->syncCustomerAccount($customer->fresh());
        });

        return redirect()->route('customers')->with('status', 'تمت إضافة العميل بنجاح.');
    }

    public function updateCustomer(Request $request, Customer $customer): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $customer->company_id !== (int) $company->id, 404);

        $validated = $this->validateCustomerData($request, $company->id, $customer);

        DB::transaction(function () use ($customer, $validated, $company) {
            $customer->update($this->customerPayload($validated, $company, $customer));

            if (!$customer->code) {
                $customer->update([
                    'code' => 'CUS-' . str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT),
                ]);
            }

            $this->chartOfAccountsSynchronizer->syncCustomerAccount($customer->fresh());
        });

        return redirect()->route('customers')->with('status', 'تم تحديث العميل بنجاح.');
    }

    public function destroyCustomer(Request $request, Customer $customer): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $customer->company_id !== (int) $company->id, 404);

        if ($customer->invoices()->exists()) {
            return redirect()->route('customers')->with('error', 'لا يمكن حذف عميل مرتبط بفواتير.');
        }

        $customer->delete();

        return redirect()->route('customers')->with('status', 'تم حذف العميل بنجاح.');
    }

    public function suppliers(Request $request): View
    {
        $company = $this->company($request);
        $companyCountry = $this->countryConfigForCompany($company);
        $companyCities = collect($companyCountry['cities'] ?? []);

        $suppliers = Supplier::where('company_id', $company->id)
            ->with([
                'purchases' => function ($query) {
                    $query->orderByDesc('purchase_date');
                }
            ])
            ->withCount('products')
            ->orderBy('name')
            ->get()
            ->map(function (Supplier $supplier) {
                $supplier->code = 'SUP-' . str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT);
                $supplier->balance = (float) $supplier->purchases->sum('balance_due');
                $supplier->purchases_total = (float) $supplier->purchases->sum('total');

                return $supplier;
            });

        return view('suppliers', compact('company', 'suppliers', 'companyCountry', 'companyCities'));
    }

    public function showSupplier(Request $request, Supplier $supplier): View
    {
        $company = $this->company($request);
        abort_if((int) $supplier->company_id !== (int) $company->id, 404);
        $companyCountry = $this->countryConfigForCompany($company);
        $sortDirection = $this->sortDirection($request);

        $supplier->load([
            'purchases' => fn($query) => $query->orderBy('purchase_date', $sortDirection)->orderBy('id', $sortDirection),
            'products' => fn($query) => $query->orderBy('name'),
        ])->loadCount(['products', 'purchases']);

        $supplier->code = $supplier->code ?: 'SUP-' . str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT);
        $supplier->balance = (float) $supplier->purchases->sum('balance_due');
        $supplier->purchases_total = (float) $supplier->purchases->sum('total');

        $suggestedPaymentReference = $this->referenceGenerator->nextSupplierPaymentReference($company->id);
        $paymentAccounts = $this->directPaymentAccounts($company->id);

        return view('supplier_show', compact('company', 'supplier', 'suggestedPaymentReference', 'companyCountry', 'sortDirection', 'paymentAccounts'));
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateSupplierData($request, $company->id);

        DB::transaction(function () use ($validated, $company) {
            $supplier = Supplier::create($this->supplierPayload($validated, $company));

            if (!$supplier->code) {
                $supplier->update([
                    'code' => 'SUP-' . str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT),
                ]);
            }

            $this->chartOfAccountsSynchronizer->syncSupplierAccount($supplier->fresh());
        });

        return redirect()->route('suppliers')->with('status', 'تمت إضافة المورد بنجاح.');
    }

    public function updateSupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $supplier->company_id !== (int) $company->id, 404);

        $validated = $this->validateSupplierData($request, $company->id, $supplier);

        DB::transaction(function () use ($supplier, $validated, $company) {
            $supplier->update($this->supplierPayload($validated, $company, $supplier));

            if (!$supplier->code) {
                $supplier->update([
                    'code' => 'SUP-' . str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT),
                ]);
            }

            $this->chartOfAccountsSynchronizer->syncSupplierAccount($supplier->fresh());
        });

        if ($request->input('redirect_to') === 'show') {
            return redirect()->route('suppliers.show', $supplier)->with('status', 'تم تحديث المورد بنجاح.');
        }

        return redirect()->route('suppliers')->with('status', 'تم تحديث المورد بنجاح.');
    }

    public function storeSupplierPayment(Request $request, Supplier $supplier): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $supplier->company_id !== (int) $company->id, 404);

        $supplier->load(['purchases' => fn($query) => $query->orderBy('purchase_date')]);
        $outstandingBalance = (float) $supplier->purchases->where('balance_due', '>', 0)->sum('balance_due');

        $validated = $request->validate([
            'supplier_action' => ['nullable', 'string'],
            'payment_amount' => ['required', 'numeric', 'min:0.01', 'max:' . max($outstandingBalance, 0.01)],
            'payment_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn($query) => $query
                    ->where('company_id', $company->id)
                    ->where('allows_direct_transactions', true)
                    ->where('is_active', true)),
            ],
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

        DB::transaction(function () use ($supplier, $paymentAmount, $user, $paymentReference, $validated) {
            $remainingAmount = $paymentAmount;
            $paymentAccount = Account::query()
                ->where('company_id', $supplier->company_id)
                ->where('allows_direct_transactions', true)
                ->where('is_active', true)
                ->findOrFail($validated['payment_account_id']);

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

            $journalEntry = $this->accountingService->createSupplierPaymentEntry($supplier, $paymentAmount, $user, $paymentReference, $paymentAccount);
            $this->paymentSyncService->recordSupplierPayment(
                supplier: $supplier,
                amount: $paymentAmount,
                paymentDate: now()->toDateString(),
                reference: $paymentReference,
                entry: $journalEntry,
                paymentAccountId: (int) $paymentAccount->id,
            );
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
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'expense_account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn($query) => $query->where('company_id', $company->id)),
            ],
            'expense_id' => [
                'nullable',
                Rule::exists('expenses', 'id')->where(fn($query) => $query->where('company_id', $company->id)),
            ],
        ]);

        $sortDirection = $filters['sort_direction'] ?? 'desc';

        $expenses = $this->expenseReportQuery($company->id, $filters)
            ->orderBy('expense_date', $sortDirection)
            ->orderBy('id', $sortDirection)
            ->get();

        $expenseAccounts = Account::where('company_id', $company->id)
            ->where('account_type', 'expense')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $expenseTargets = Expense::where('company_id', $company->id)
            ->orderBy('expense_date', $sortDirection)
            ->orderBy('id', $sortDirection)
            ->get(['id', 'name', 'reference', 'expense_date']);

        $paymentAccounts = Account::where('company_id', $company->id)
            ->where('allows_direct_transactions', true)
            ->where('is_active', true)
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
                'sort_direction' => $sortDirection,
                'expense_account_id' => isset($filters['expense_account_id']) ? (int) $filters['expense_account_id'] : null,
                'expense_id' => isset($filters['expense_id']) ? (int) $filters['expense_id'] : null,
            ],
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

        DB::transaction(function () use ($validated, $company) {
            $product = Product::create($this->productPayload($validated, $company->id));

            if (!$product->code) {
                $product->update([
                    'code' => 'PRD-' . str_pad((string) $product->id, 4, '0', STR_PAD_LEFT),
                ]);
            }

            $this->chartOfAccountsSynchronizer->syncProductAccounts($product->fresh());
        });

        return redirect()
            ->route('products')
            ->with('status', 'تمت إضافة المنتج بنجاح.');
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $product->company_id !== (int) $company->id, 404);

        $validated = $this->validateProductData($request, $company->id, $product);

        DB::transaction(function () use ($product, $validated, $company) {
            $product->update($this->productPayload($validated, $company->id));

            if (!$product->code) {
                $product->update([
                    'code' => 'PRD-' . str_pad((string) $product->id, 4, '0', STR_PAD_LEFT),
                ]);
            }

            $this->chartOfAccountsSynchronizer->syncProductAccounts($product->fresh());
        });

        return redirect()
            ->route('products')
            ->with('status', 'تم تحديث المنتج بنجاح.');
    }

    public function resyncCompanyAccounting(Request $request): RedirectResponse
    {
        $company = $this->company($request);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $summary = DB::transaction(function () use ($company, $user) {
            $this->chartOfAccountsSynchronizer->synchronizeCompany($company);

            return $this->accountingService->resyncCompanyTransactions($company, $user);
        });

        return redirect()->route('chart_of_accounts')->with(
            'status',
            'تمت إعادة مزامنة الدليل المحاسبي والقيود الآلية. الفواتير: '
            . $summary['invoices']
            . '، المشتريات: '
            . $summary['purchases']
            . '، المصروفات: '
            . $summary['expenses']
            . '.'
        );
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

        // Fetch all accounts for the company
        $allAccounts = Account::query()
            ->where('company_id', $company->id)
            ->orderBy('code')
            ->get();

        // Fetch aggregated balances from journal_lines to ensure real-time accuracy
        $balances = \App\Models\JournalLine::query()
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.status', 'posted')
            ->select('account_id', \DB::raw('SUM(debit) as total_debit'), \DB::raw('SUM(credit) as total_credit'))
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        // Update balances in the collection for this request
        // حساب قيمة المخزون الحقيقية من المنتجات (الكمية × التكلفة)
        $inventoryValueFromProducts = \App\Models\Product::query()
            ->where('company_id', $company->id)
            ->where('type', 'product')
            ->selectRaw('SUM(stock_quantity * cost_price) as total_value')
            ->value('total_value') ?? 0;

        foreach ($allAccounts as $account) {
            // للحساب الرئيسي للمخزون (1106)، استخدم قيمة المنتجات الحقيقية
            if ($account->code === '1106') {
                $account->balance = (float) $inventoryValueFromProducts;
            } else {
                $balanceData = $balances->get($account->id);
                $debit = $balanceData ? (float) $balanceData->total_debit : 0;
                $credit = $balanceData ? (float) $balanceData->total_credit : 0;

                if (in_array($account->account_type, ['asset', 'expense', 'cogs'])) {
                    $account->balance = $debit - $credit;
                } else {
                    $account->balance = $credit - $debit;
                }
            }
        }

        $includeDynamicAccounts = $request->boolean('include_dynamic');

        // حساب rolled_up_balance من جميع الحسابات (بما فيها الديناميكية) قبل الفلترة
        // حتى يظهر رصيد الحسابات الرئيسية بشكل صحيح حتى لو كانت الحسابات الفرعية مخفية
        $rolledUpBalances = $this->calculateAllRolledUpBalances($allAccounts);
        foreach ($allAccounts as $account) {
            $account->rolled_up_balance = $rolledUpBalances[$account->id] ?? (float) $account->balance;
        }

        $visibleAccounts = $this->visibleChartAccounts($allAccounts, $includeDynamicAccounts);
        $accountFilters = $this->chartAccountFilters($request);

        $hasAccountFilters = $this->hasAccountFilters($accountFilters);
        $matchingAccounts = $this->filterAccounts($visibleAccounts, $accountFilters);

        // buildAccountTree and buildFilteredAccountTree use nestAccounts internally
        $accounts = $hasAccountFilters
            ? $this->buildFilteredAccountTree($visibleAccounts, $matchingAccounts)
            : $this->buildAccountTree($visibleAccounts);

        $accountStats = $hasAccountFilters ? $matchingAccounts : $visibleAccounts;

        $parentOptions = $visibleAccounts->map(fn(Account $account) => [
            'id' => $account->id,
            'code' => $account->code,
            'label' => $account->code . ' - ' . ($account->name_ar ?: $account->name),
            'type' => $account->account_type,
        ])->values();

        $suggestedParentIds = $this->suggestedParentIds($visibleAccounts);

        return view('chart_of_accounts', compact(
            'company',
            'accounts',
            'accountStats',
            'accountFilters',
            'hasAccountFilters',
            'matchingAccounts',
            'parentOptions',
            'suggestedParentIds',
            'includeDynamicAccounts'
        ));
    }

    public function printChartOfAccounts(Request $request): View
    {
        $company = $this->company($request);
        $allAccounts = Account::query()
            ->where('company_id', $company->id)
            ->orderBy('code')
            ->get();

        $includeDynamicAccounts = $request->boolean('include_dynamic');
        $visibleAccounts = $this->visibleChartAccounts($allAccounts, $includeDynamicAccounts);
        $accountFilters = $this->chartAccountFilters($request);
        $filteredAccounts = $this->filterAccounts($visibleAccounts, $accountFilters);
        $rows = $this->chartAccountRows($filteredAccounts);

        return view('chart_of_accounts_print', compact(
            'company',
            'rows',
            'accountFilters',
            'includeDynamicAccounts'
        ));
    }

    public function exportChartOfAccounts(Request $request): StreamedResponse
    {
        $company = $this->company($request);
        $allAccounts = Account::query()
            ->where('company_id', $company->id)
            ->orderBy('code')
            ->get();

        $includeDynamicAccounts = $request->boolean('include_dynamic');
        $visibleAccounts = $this->visibleChartAccounts($allAccounts, $includeDynamicAccounts);
        $accountFilters = $this->chartAccountFilters($request);
        $rows = $this->chartAccountRows($this->filterAccounts($visibleAccounts, $accountFilters));
        $fileName = 'chart-of-accounts-' . $company->id . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['الرمز', 'اسم الحساب', 'النوع', 'الوصف', 'رقم تعريفي للحساب الأصلي', 'يمكن الدفع والتحصيل بهذا الحساب']);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['code'],
                    $row['name'],
                    $row['display_account_type'],
                    $row['description'],
                    $row['parent_label'],
                    $row['allows_direct_transactions'] ? 'نعم' : 'لا',
                ]);
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function showAccount(Request $request, Account $account): View
    {
        $company = $this->company($request);
        abort_if((int) $account->company_id !== (int) $company->id, 404);

        // Load all accounts for ancestors lookup
        $allAccounts = Account::where('company_id', $company->id)->get();

        $account->load([
            'parent',
            'children' => fn($query) => $query->orderBy('code'),
        ])->loadCount(['children', 'journalLines']);

        // إعادة حساب الأرصدة من القيود المحاسبية مباشرة
        $balances = JournalLine::join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $company->id)
            ->where('journal_entries.status', 'posted')
            ->select('account_id', DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'))
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        // دالة مساعدة لحساب الرصيد
        $calculateBalance = function ($account) use ($balances) {
            $balanceData = $balances->get($account->id);
            $debit = $balanceData ? (float) $balanceData->total_debit : 0;
            $credit = $balanceData ? (float) $balanceData->total_credit : 0;

            if (in_array($account->account_type, ['asset', 'expense', 'cogs'])) {
                return $debit - $credit;
            } else {
                return $credit - $debit;
            }
        };

        // تحديث رصيد الحساب الحالي
        $account->balance = $calculateBalance($account);

        // تحديث رصيد الحسابات الفرعية
        foreach ($account->children as $child) {
            $child->balance = $calculateBalance($child);
        }

        // حساب rolled_up_balance لجميع الحسابات
        $allAccountsForBalances = Account::where('company_id', $company->id)->get();
        foreach ($allAccountsForBalances as $acc) {
            $acc->balance = $calculateBalance($acc);
        }
        $rolledUpBalances = $this->calculateAllRolledUpBalances($allAccountsForBalances);

        // تعيين rolled_up_balance للحساب الحالي والفرعية
        $account->rolled_up_balance = $rolledUpBalances[$account->id] ?? (float) $account->balance;
        foreach ($account->children as $child) {
            $child->rolled_up_balance = $rolledUpBalances[$child->id] ?? (float) $child->balance;
        }

        $ancestors = $this->accountAncestors($account, $allAccounts);
        $recentJournalLines = JournalLine::query()
            ->with(['journalEntry', 'account'])
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', fn($query) => $query->where('company_id', $company->id))
            ->latest('journal_entry_id')
            ->limit(12)
            ->get();

        $hierarchyLabel = match (true) {
            $account->parent_id === null && $account->children_count > 0 => 'حساب جذري أب',
            $account->parent_id === null => 'حساب جذري نهائي',
            $account->children_count > 0 => 'حساب أب فرعي',
            default => 'حساب فرعي نهائي',
        };

        return view('account_show', compact(
            'company',
            'account',
            'ancestors',
            'recentJournalLines',
            'hierarchyLabel',
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

            if (!$parent) {
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
            'allows_direct_transactions' => (bool) ($validated['allows_direct_transactions'] ?? false),
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
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn($query) => $query->where('company_id', $company->id)),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $sortDirection = $filters['sort_direction'] ?? 'desc';

        $entries = JournalEntry::with(['lines.account'])
            ->where('company_id', $company->id)
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $search = trim((string) $filters['search']);

                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery->where('entry_number', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('reference', 'like', '%' . $search . '%');
                });
            })
            ->when(!empty($filters['status']), fn($query) => $query->where('status', $filters['status']))
            ->when(!empty($filters['date_from']), fn($query) => $query->whereDate('entry_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn($query) => $query->whereDate('entry_date', '<=', $filters['date_to']))
            ->when(!empty($filters['account_id']), function ($query) use ($filters) {
                $query->whereHas('lines', fn($linesQuery) => $linesQuery->where('account_id', $filters['account_id']));
            })
            ->orderBy('entry_date', $sortDirection)
            ->orderBy('id', $sortDirection)
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
                'sort_direction' => $sortDirection,
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

        try {
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
        } catch (\RuntimeException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'تعذر إنشاء القيد المحاسبي حالياً. تحقق من الحسابات المختارة ثم أعد المحاولة.');
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'تعذر إنشاء القيد المحاسبي حالياً. تحقق من الحسابات المختارة ثم أعد المحاولة.');
        }

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

    public function operationsActivityReport(Request $request): View
    {
        $company = $this->company($request);
        $validated = $request->validate([
            'group_by' => ['nullable', Rule::in(['day', 'reference'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'reference' => ['nullable', 'string', 'max:100'],
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $groupBy = $validated['group_by'] ?? 'day';
        $dateFrom = $validated['date_from'] ?? now()->subDays(29)->toDateString();
        $dateTo = $validated['date_to'] ?? now()->toDateString();
        $reference = trim((string) ($validated['reference'] ?? ''));
        $sortDirection = $validated['sort_direction'] ?? 'desc';

        $payments = Payment::query()
            ->with(['purchase.supplier', 'invoice.customer', 'supplier'])
            ->where('company_id', $company->id)
            ->whereBetween('payment_date', [$dateFrom, $dateTo])
            ->when($reference !== '', fn($query) => $query->where('reference', 'like', '%' . $reference . '%'))
            ->orderBy('payment_date', $sortDirection)
            ->orderBy('id', $sortDirection)
            ->get();

        $inventoryMovements = InventoryMovement::query()
            ->with('product')
            ->where('company_id', $company->id)
            ->whereBetween('movement_date', [$dateFrom, $dateTo])
            ->when($reference !== '', fn($query) => $query->where('reference_number', 'like', '%' . $reference . '%'))
            ->orderBy('movement_date', $sortDirection)
            ->orderBy('id', $sortDirection)
            ->get();

        $paymentGroups = $payments
            ->groupBy(function (Payment $payment) use ($groupBy) {
                if ($groupBy === 'reference') {
                    return $payment->reference ?: 'بدون مرجع';
                }

                return optional($payment->payment_date)->format('Y-m-d') ?: 'بدون تاريخ';
            })
            ->map(function (Collection $rows, string $label) {
                return [
                    'label' => $label,
                    'count' => $rows->count(),
                    'in_total' => round((float) $rows->where('payment_direction', 'in')->sum('amount'), 2),
                    'out_total' => round((float) $rows->where('payment_direction', 'out')->sum('amount'), 2),
                ];
            })
            ->values();

        $movementGroups = $inventoryMovements
            ->groupBy(function (InventoryMovement $movement) use ($groupBy) {
                if ($groupBy === 'reference') {
                    return $movement->reference_number ?: 'بدون مرجع';
                }

                return optional($movement->movement_date)->format('Y-m-d') ?: 'بدون تاريخ';
            })
            ->map(function (Collection $rows, string $label) {
                return [
                    'label' => $label,
                    'count' => $rows->count(),
                    'incoming_quantity' => round((float) $rows->where('direction', 'in')->sum('quantity'), 2),
                    'outgoing_quantity' => round((float) $rows->where('direction', 'out')->sum('quantity'), 2),
                    'inventory_cost' => round((float) $rows->sum('total_cost'), 2),
                ];
            })
            ->values();

        return view('reports.operations_activity', [
            'company' => $company,
            'groupBy' => $groupBy,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'reference' => $reference,
            'sortDirection' => $sortDirection,
            'payments' => $payments,
            'inventoryMovements' => $inventoryMovements,
            'paymentGroups' => $paymentGroups,
            'movementGroups' => $movementGroups,
            'paymentSummary' => [
                'incoming' => round((float) $payments->where('payment_direction', 'in')->sum('amount'), 2),
                'outgoing' => round((float) $payments->where('payment_direction', 'out')->sum('amount'), 2),
                'count' => $payments->count(),
            ],
            'movementSummary' => [
                'incoming_quantity' => round((float) $inventoryMovements->where('direction', 'in')->sum('quantity'), 2),
                'outgoing_quantity' => round((float) $inventoryMovements->where('direction', 'out')->sum('quantity'), 2),
                'count' => $inventoryMovements->count(),
                'inventory_cost' => round((float) $inventoryMovements->sum('total_cost'), 2),
            ],
        ]);
    }

    public function reports(Request $request): View
    {
        $company = $this->company($request);

        if ($request->boolean('print')) {
            return view('reports_print', $this->legacyReportsViewData($request, $company));
        }

        $sections = $this->interactiveReportSections();
        $catalog = $this->interactiveReportCatalog();
        $periodOptions = $this->interactivePeriodOptions();

        $validated = $request->validate([
            'section' => ['nullable', Rule::in(array_keys($sections))],
            'report' => ['nullable', Rule::in(array_keys($catalog))],
            'report_type' => ['nullable', 'string'],
            'period' => ['nullable', Rule::in(array_keys($periodOptions))],
            'date_from' => [
                'nullable',
                'date',
                Rule::requiredIf(fn() => $request->input('period') === 'custom'),
            ],
            'date_to' => [
                'nullable',
                'date',
                Rule::requiredIf(fn() => $request->input('period') === 'custom'),
                'after_or_equal:date_from',
            ],
        ]);

        $selectedPeriod = $validated['period'] ?? config('accounting.reports.default_period', 'monthly');
        [$dateFrom, $dateTo] = $this->resolveReportRange(
            $selectedPeriod,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );

        $initialReportKey = $validated['report']
            ?? $this->legacyReportTypeToInteractiveKey($validated['report_type'] ?? null)
            ?? 'sales_by_invoice';

        if (!array_key_exists($initialReportKey, $catalog)) {
            $initialReportKey = 'sales_by_invoice';
        }

        $initialSection = $validated['section'] ?? ($catalog[$initialReportKey]['section'] ?? 'sales');

        if (!array_key_exists($initialSection, $sections)) {
            $initialSection = $catalog[$initialReportKey]['section'] ?? 'sales';
        }

        $stats = $this->reportSummaryStats($company->id, $dateFrom, $dateTo);
        $openPayables = (float) Purchase::where('company_id', $company->id)->sum('balance_due');
        $netVat = (float) Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partial', 'paid'])
            ->whereBetween('invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('tax_amount')
            - (
                (float) Purchase::where('company_id', $company->id)
                    ->whereIn('status', ['approved', 'partial', 'paid'])
                    ->whereBetween('purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                    ->sum('tax_amount')
                + (float) Expense::where('company_id', $company->id)
                    ->whereBetween('expense_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                    ->sum('tax_amount')
            );

        $sectionCounts = collect($catalog)
            ->groupBy('section')
            ->map(fn(Collection $items) => $items->count())
            ->all();

        return view('reports', [
            'company' => $company,
            'sections' => $sections,
            'sectionCounts' => $sectionCounts,
            'reportCatalog' => $catalog,
            'periodOptions' => $periodOptions,
            'selectedPeriod' => $selectedPeriod,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'initialSection' => $initialSection,
            'initialReportKey' => $initialReportKey,
            'dashboardStats' => [
                ['label' => 'إيرادات الفترة', 'value' => (float) $stats['total_revenue'], 'icon' => 'fa-sack-dollar', 'tone' => 'primary'],
                ['label' => 'صافي الربح', 'value' => (float) $stats['net_profit'], 'icon' => 'fa-chart-line', 'tone' => 'success'],
                ['label' => 'الذمم الدائنة', 'value' => $openPayables, 'icon' => 'fa-file-invoice-dollar', 'tone' => 'warning'],
                ['label' => 'صافي الضريبة', 'value' => $netVat, 'icon' => 'fa-percent', 'tone' => 'accent'],
            ],
        ]);
    }

    public function reportShow(Request $request, string $report): View
    {
        $company = $this->company($request);
        $catalog = $this->interactiveReportCatalog();
        $periodOptions = $this->interactivePeriodOptions();

        abort_unless(array_key_exists($report, $catalog), 404);

        $validated = $request->validate([
            'period' => ['nullable', Rule::in(array_keys($periodOptions))],
            'date_from' => [
                'nullable',
                'date',
                Rule::requiredIf(fn() => $request->input('period') === 'custom'),
            ],
            'date_to' => [
                'nullable',
                'date',
                Rule::requiredIf(fn() => $request->input('period') === 'custom'),
                'after_or_equal:date_from',
            ],
        ]);

        $selectedPeriod = $validated['period'] ?? config('accounting.reports.default_period', 'monthly');
        [$dateFrom, $dateTo] = $this->resolveReportRange(
            $selectedPeriod,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );

        $reportPayload = $this->buildInteractiveReportResponse($company, $report, $dateFrom, $dateTo);

        if ($request->boolean('print')) {
            return view('reports_show_print', [
                'company' => $company,
                'reportKey' => $report,
                'reportMeta' => $catalog[$report],
                'reportPayload' => $reportPayload,
                'periodOptions' => $periodOptions,
                'selectedPeriod' => $selectedPeriod,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'printMode' => true,
            ]);
        }

        return view('reports_show', [
            'company' => $company,
            'reportKey' => $report,
            'reportMeta' => $catalog[$report],
            'reportPayload' => $reportPayload,
            'printReportType' => $this->interactiveKeyToLegacyReportType($report),
            'periodOptions' => $periodOptions,
            'selectedPeriod' => $selectedPeriod,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function reportQuery(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $catalog = $this->interactiveReportCatalog();
        $periodOptions = $this->interactivePeriodOptions();

        $validated = $request->validate([
            'report' => ['required', Rule::in(array_keys($catalog))],
            'period' => ['nullable', Rule::in(array_keys($periodOptions))],
            'date_from' => [
                'nullable',
                'date',
                Rule::requiredIf(fn() => $request->input('period') === 'custom'),
            ],
            'date_to' => [
                'nullable',
                'date',
                Rule::requiredIf(fn() => $request->input('period') === 'custom'),
                'after_or_equal:date_from',
            ],
        ]);

        $selectedPeriod = $validated['period'] ?? config('accounting.reports.default_period', 'monthly');
        [$dateFrom, $dateTo] = $this->resolveReportRange(
            $selectedPeriod,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );

        return response()->json($this->buildInteractiveReportResponse($company, $validated['report'], $dateFrom, $dateTo));
    }

    private function legacyReportsViewData(Request $request, Company $company): array
    {
        $reportTypes = [
            'income_statement' => ['label' => 'قائمة الدخل', 'description' => 'مقارنة الإيرادات بالمشتريات والمصروفات للوصول إلى صافي الربح.', 'focus' => null],
            'account_balances' => ['label' => 'أرصدة الحسابات', 'description' => 'عرض حركة وأرصدة شجرة الحسابات أو حساب محدد.', 'focus' => 'account'],
            'product_sales' => ['label' => 'مبيعات المنتجات', 'description' => 'تحليل مبيعات كل المنتجات أو منتج محدد خلال فترة معينة.', 'focus' => 'product'],
            'expense_details' => ['label' => 'تفاصيل المصروفات', 'description' => 'تقرير بالمصروفات المسجلة أو مصروف محدد بالتفصيل.', 'focus' => 'expense'],
            'receivables' => ['label' => 'الذمم المدينة', 'description' => 'أرصدة العملاء المستحقة أو عميل محدد.', 'focus' => 'customer'],
            'payables' => ['label' => 'الذمم الدائنة', 'description' => 'أرصدة الموردين المستحقة أو مورد محدد.', 'focus' => 'supplier'],
            'tax_summary' => ['label' => 'تقرير الضرائب', 'description' => 'ملخص ضريبة المخرجات وضريبة المدخلات وصافي الالتزام الضريبي خلال الفترة.', 'focus' => null],
        ];

        $periodOptions = $this->interactivePeriodOptions();

        $validated = $request->validate([
            'report_type' => ['nullable', Rule::in(array_keys($reportTypes))],
            'period' => ['nullable', Rule::in(array_keys($periodOptions))],
            'date_from' => ['nullable', 'date', Rule::requiredIf(fn() => $request->input('period') === 'custom')],
            'date_to' => ['nullable', 'date', Rule::requiredIf(fn() => $request->input('period') === 'custom'), 'after_or_equal:date_from'],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where(fn($query) => $query->where('company_id', $company->id))],
            'product_id' => ['nullable', Rule::exists('products', 'id')->where(fn($query) => $query->where('company_id', $company->id))],
            'expense_id' => ['nullable', Rule::exists('expenses', 'id')->where(fn($query) => $query->where('company_id', $company->id))],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where(fn($query) => $query->where('company_id', $company->id))],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where(fn($query) => $query->where('company_id', $company->id))],
            'print' => ['nullable', 'boolean'],
        ]);

        $selectedReportType = $validated['report_type'] ?? 'income_statement';
        $selectedPeriod = $validated['period'] ?? config('accounting.reports.default_period', 'monthly');
        [$dateFrom, $dateTo] = $this->resolveReportRange($selectedPeriod, $validated['date_from'] ?? null, $validated['date_to'] ?? null);

        $accounts = Account::where('company_id', $company->id)->orderBy('code')->get(['id', 'code', 'name', 'account_type']);
        $products = Product::forCompany($company->id)->active()->orderBy('name')->get(['id', 'name', 'code']);
        $expenses = Expense::where('company_id', $company->id)->orderByDesc('expense_date')->get(['id', 'name', 'reference', 'expense_date', 'total']);
        $customers = Customer::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);
        $suppliers = Supplier::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);

        $stats = $this->reportSummaryStats($company->id, $dateFrom, $dateTo);
        $companyCountry = $this->countryConfigForCompany($company);
        $report = $this->buildReportData($company, $selectedReportType, $validated, $dateFrom, $dateTo, $reportTypes);

        return [
            'company' => $company,
            'companyCountry' => $companyCountry,
            'stats' => $stats,
            'reportRows' => $report['rows'],
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
    }

    private function interactiveReportSections(): array
    {
        return [
            'favorites' => ['label' => 'المفضلة', 'icon' => 'fa-star', 'summary' => 'الوصول السريع إلى التقارير التي تستخدمها أكثر.'],
            'sales' => ['label' => 'المبيعات', 'icon' => 'fa-receipt', 'summary' => 'تقارير الأداء البيعي والفواتير والعملاء وحالات الدفع.'],
            'inventory' => ['label' => 'المخزون', 'icon' => 'fa-boxes-stacked', 'summary' => 'رؤية فورية للمخزون والحركة والمنتجات الأسرع دوراناً.'],
            'taxes' => ['label' => 'الضرائب', 'icon' => 'fa-percent', 'summary' => 'ملخصات الضريبة ومصادرها من المبيعات والمشتريات.'],
            'warehouse' => ['label' => 'المخزن', 'icon' => 'fa-warehouse', 'summary' => 'استلامات الموردين وتغطية الحد الأدنى ومستوى الجاهزية.'],
            'finance' => ['label' => 'المالية', 'icon' => 'fa-wallet', 'summary' => 'قائمة الدخل والذمم والمصروفات وأرصدة الحسابات.'],
        ];
    }

    private function interactivePeriodOptions(): array
    {
        return [
            'monthly' => 'هذا الشهر',
            'quarterly' => 'هذا الربع',
            'yearly' => 'هذه السنة',
            'custom' => 'فترة مخصصة',
        ];
    }

    private function interactiveReportCatalog(): array
    {
        return [
            'sales_by_location' => ['section' => 'sales', 'icon' => 'fa-map-location-dot', 'title' => 'تقرير المبيعات لكل موقع', 'description' => 'تجميع المبيعات حسب الفروع والمواقع التشغيلية الفعلية داخل الشركة.', 'query_preview' => "SELECT b.name AS branch, SUM(s.total_amount) AS total_sales\nFROM sales s\nJOIN branches b ON s.branch_id = b.id\nGROUP BY b.name;"],
            'sales_by_invoice' => ['section' => 'sales', 'icon' => 'fa-file-invoice-dollar', 'title' => 'تقرير المبيعات من كل فاتورة', 'description' => 'عرض تفصيلي لكل عملية بيع على مستوى المستند وقيمتها والضريبة المرتبطة بها.', 'query_preview' => "SELECT sale_number, total_amount, tax_amount, sale_date\nFROM sales\nWHERE company_id = ?\nORDER BY sale_date DESC;"],
            'sales_by_category' => ['section' => 'sales', 'icon' => 'fa-layer-group', 'title' => 'المبيعات حسب الفئات', 'description' => 'تحليل المبيعات حسب فئات المنتجات المعتمدة في قاعدة البيانات.', 'query_preview' => "SELECT c.name, SUM(si.total_amount) AS total_sales\nFROM sales_items si\nJOIN categories c ON si.category_id = c.id\nJOIN sales s ON si.sale_id = s.id\nGROUP BY c.name;"],
            'sales_by_employee' => ['section' => 'sales', 'icon' => 'fa-user-tie', 'title' => 'تقرير المبيعات من كل موظف', 'description' => 'تجميع عمليات البيع حسب الموظف المسؤول عن العملية عند وجود الربط.', 'query_preview' => "SELECT e.first_name, e.last_name, SUM(s.total_amount) AS total_sales\nFROM sales s\nLEFT JOIN employees e ON s.employee_id = e.id\nGROUP BY e.id, e.first_name, e.last_name;"],
            'sales_by_payment_status' => ['section' => 'sales', 'icon' => 'fa-credit-card', 'title' => 'تقرير المبيعات لكل حالة دفع', 'description' => 'تحليل حجم المبيعات حسب حالة السداد الحالية والرصيد المتبقي.', 'query_preview' => "SELECT payment_status, SUM(total_amount)\nFROM sales\nGROUP BY payment_status;"],
            'customer_transactions' => ['section' => 'sales', 'icon' => 'fa-arrows-rotate', 'title' => 'تقرير معاملات العملاء', 'description' => 'عدد المعاملات والإجمالي والمتبقي لكل عميل خلال الفترة المحددة.', 'query_preview' => "SELECT c.name, s.id, s.total_amount, s.sale_date\nFROM sales s\nJOIN customers c ON s.customer_id = c.id;"],
            'sales_by_channel' => ['section' => 'sales', 'icon' => 'fa-store', 'title' => 'تقرير المبيعات من كل قناة بيع', 'description' => 'تجميع الإيرادات حسب قنوات البيع المسجلة على كل عملية.', 'query_preview' => "SELECT sc.name, SUM(s.total_amount)\nFROM sales s\nJOIN sales_channels sc ON s.channel_id = sc.id\nGROUP BY sc.name;"],
            'sales_by_customer' => ['section' => 'sales', 'icon' => 'fa-users', 'title' => 'تقرير المبيعات من كل عميل', 'description' => 'إجمالي مبيعات كل عميل ومتوسط قيمة العملية وعدد العمليات.', 'query_preview' => "SELECT c.name, SUM(s.total_amount)\nFROM sales s\nJOIN customers c ON s.customer_id = c.id\nGROUP BY c.name;"],
            'transactions_by_branch' => ['section' => 'sales', 'icon' => 'fa-building-circle-arrow-right', 'title' => 'تقرير المعاملات في كل موقع', 'description' => 'حصر عدد معاملات البيع المنفذة في كل فرع أو موقع تشغيل.', 'query_preview' => "SELECT b.name, COUNT(s.id) AS total_transactions\nFROM sales s\nJOIN branches b ON s.branch_id = b.id\nGROUP BY b.name;"],
            'customer_product_sales' => ['section' => 'sales', 'icon' => 'fa-bag-shopping', 'title' => 'تقرير المنتجات المباعة للعميل', 'description' => 'تتبع المنتجات والكميات المباعة لكل عميل داخل الفترة المحددة.', 'query_preview' => "SELECT c.name, si.product_id, SUM(si.quantity)\nFROM sales_items si\nJOIN sales s ON si.sale_id = s.id\nJOIN customers c ON s.customer_id = c.id\nGROUP BY c.name, si.product_id;"],
            'sales_by_period' => ['section' => 'sales', 'icon' => 'fa-calendar-days', 'title' => 'تقرير المبيعات حسب الفترة الزمنية', 'description' => 'تجميع المبيعات زمنياً لمراقبة الاتجاهات اليومية داخل الفترة.', 'query_preview' => "SELECT DATE(sale_date) AS day, SUM(total_amount)\nFROM sales\nGROUP BY DATE(sale_date)\nORDER BY DATE(sale_date);"],
            'customer_statement' => ['section' => 'sales', 'icon' => 'fa-file-lines', 'title' => 'كشف حساب مدين', 'description' => 'إجمالي المبيعات والمدفوعات والمتبقي لكل عميل لعرض كشف حساب المدين.', 'query_preview' => "SELECT c.name, s.total_amount, p.amount AS paid, (s.total_amount - COALESCE(p.amount, 0)) AS remaining\nFROM sales s\nLEFT JOIN payments p ON s.id = p.invoice_id\nJOIN customers c ON s.customer_id = c.id;"],
            'product_tracking' => ['section' => 'inventory', 'icon' => 'fa-crosshairs', 'title' => 'استعلامات تتبع المنتج', 'description' => 'مراقبة المنتجات المتتبعة في نظامك من خلال متابعة معلومات مثل الموقع، حالة المنتج، تاريخ الانتهاء وغيرها من المعلومات.', 'query_preview' => "SELECT p.name, p.location, p.status, p.stock_quantity, b.expiry_date\nFROM products p\nLEFT JOIN product_batches b ON b.product_id = p.id\nWHERE p.company_id = ? AND p.type = 'product';"],
            'product_performance' => ['section' => 'inventory', 'icon' => 'fa-chart-line', 'title' => 'تقرير أداء المنتج', 'description' => 'عرض مبيعات كل منتج في النظام على حدة حتى تتمكن من معرفة المنتجات الأفضل أداءاً والأكثر تحقيقاً للأرباح.', 'query_preview' => "SELECT p.name, SUM(ii.quantity) AS qty, SUM(ii.total) AS sales, SUM((ii.price - p.cost_price) * ii.quantity) AS profit\nFROM products p\nJOIN invoice_items ii ON ii.product_id = p.id\nJOIN invoices i ON i.id = ii.invoice_id\nWHERE i.company_id = ? AND i.status = 'completed'\nGROUP BY p.id;"],
            'product_movements' => ['section' => 'inventory', 'icon' => 'fa-dolly', 'title' => 'حركة المنتجات', 'description' => 'تعرف على حركة منتجات مخزونك علماً بانها يتم ادراجها في تقارير المخزون.', 'query_preview' => "SELECT sm.created_at, p.name, sm.type, sm.quantity, sm.reference_type, w.name AS warehouse\nFROM stock_movements sm\nJOIN products p ON p.id = sm.product_id\nLEFT JOIN warehouses w ON w.id = sm.warehouse_id\nWHERE sm.company_id = ?\nORDER BY sm.created_at DESC;"],
            'tax_return' => ['section' => 'taxes', 'icon' => 'fa-file-contract', 'title' => 'الإقرار الضريبي', 'description' => 'ملخص شامل لكل ما يتعلق بالضرائب في النظام، يساعدك على البقاء متوافقا مع متطلبات هيئة الزكاة والدخل والإفصاح عن تقريرك الضريبي بكل سهولة.', 'query_preview' => "SELECT tax_type, SUM(tax_amount) AS tax\nFROM invoices\nWHERE company_id = ? AND status = 'completed'\nGROUP BY tax_type;\n\nSELECT tax_type, SUM(tax_amount) AS tax\nFROM purchases\nWHERE company_id = ? AND status = 'completed'\nGROUP BY tax_type;"],
            'warehouse_supplier_transactions' => ['section' => 'warehouse', 'icon' => 'fa-truck-field', 'title' => 'تقرير معاملات الموردين', 'description' => 'تتبع العمليات التي قام بها الموردون في متجرك.', 'query_preview' => '-- استعلام معاملات الموردين'],
            'warehouse_supplier_payables' => ['section' => 'warehouse', 'icon' => 'fa-file-invoice-dollar', 'title' => 'مستحقات للموردين', 'description' => 'كشف حساب دائن شامل لكل الموردين يشمل العمليات والمبالغ المستحقة لكل فترة.', 'query_preview' => '-- استعلام مستحقات الموردين'],
            'warehouse_inventory_audit' => ['section' => 'warehouse', 'icon' => 'fa-clipboard-check', 'title' => 'تقرير ملخص جرد المخزون', 'description' => 'إحصائيات عن عمليات جرد المخزون وتأثيرها على الكميات في المخزن.', 'query_preview' => '-- استعلام جرد المخزون'],
            'warehouse_purchase_summary' => ['section' => 'warehouse', 'icon' => 'fa-cart-shopping', 'title' => 'ملخص فواتير المشتريات', 'description' => 'ملخص لعمليات المشتريات وقيمتها وكمياتها لتسهيل القرارات المستقبلية.', 'query_preview' => '-- استعلام ملخص المشتريات'],
            'warehouse_purchase_details' => ['section' => 'warehouse', 'icon' => 'fa-receipt', 'title' => 'تفاصيل فاتورة الشراء', 'description' => 'تقرير تفصيلي يشمل التاريخ والكميات وبيانات تدعم اتخاذ قرار الشراء.', 'query_preview' => '-- استعلام تفاصيل الشراء'],
            'warehouse_supplier_summary' => ['section' => 'warehouse', 'icon' => 'fa-address-book', 'title' => 'تقرير ملخص معاملات الموردين', 'description' => 'نظرة عامة على الموردين، بيانات التواصل، وإجمالي المعاملات والكميات.', 'query_preview' => '-- استعلام ملخص الموردين'],
            'warehouse_supplier_receivables' => ['section' => 'warehouse', 'icon' => 'fa-hand-holding-dollar', 'title' => 'مستحقات من الموردين', 'description' => 'كشف حساب مدين شامل لكل الموردين يشمل العمليات والمبالغ المستحقة.', 'query_preview' => '-- استعلام مستحقات من الموردين'],
            'warehouse_cost_change' => ['section' => 'warehouse', 'icon' => 'fa-chart-line', 'title' => 'التغير في سعر التكلفة', 'description' => 'ملخص لتغييرات تكلفة المنتج عبر الزمن وتأثيرها على صافي الربح.', 'query_preview' => '-- استعلام تغيير التكلفة'],
            'finance_balance_sheet' => ['section' => 'finance', 'icon' => 'fa-scale-balanced', 'title' => 'قائمة المركز المالي', 'description' => 'هي قائمة تلخص أصول، وخصوم، وحقوق الملكية للمنشأة في تاريخ معين.', 'query_preview' => '-- استعلام قائمة المركز المالي'],
            'finance_trial_balance' => ['section' => 'finance', 'icon' => 'fa-scale-unbalanced', 'title' => 'ميزان المراجعة', 'description' => 'هو كشف تظهر فيه مجاميع الأطراف المدينة ومجموع الأطراف الدائنة للحسابات.', 'query_preview' => '-- استعلام ميزان المراجعة'],
            'finance_expense_categories' => ['section' => 'finance', 'icon' => 'fa-chart-pie', 'title' => 'تقرير فئات المصروفات', 'description' => 'تقرير أعلى المصروفات حسب الفئات خلال فترة زمنية محددة.', 'query_preview' => '-- استعلام فئات المصروفات'],
            'finance_income_statement' => ['section' => 'finance', 'icon' => 'fa-file-invoice-dollar', 'title' => 'قائمة الدخل', 'description' => 'هي قائمة تظهر ربحية الشركة خلال فترة زمنية معينة، وتتكون من إيرادات ومصروفات المنشأة.', 'query_preview' => '-- استعلام قائمة الدخل'],
            'finance_account_statements' => ['section' => 'finance', 'icon' => 'fa-file-lines', 'title' => 'كشوف الحسابات', 'description' => 'هو عبارة عن تقرير وميزان مالي يوضح وضع الحساب. فإما أن يكون دائنًا أو مدينًا.', 'query_preview' => '-- استعلام كشوف الحسابات'],
            'finance_general_ledger' => ['section' => 'finance', 'icon' => 'fa-book', 'title' => 'دفتر الأستاذ العام', 'description' => 'سجل محاسبي يشتمل على جميع تفاصيل حسابات الشركة وحركاتها المالية.', 'query_preview' => '-- استعلام دفتر الأستاذ العام'],
            'finance_wallet_transactions' => ['section' => 'finance', 'icon' => 'fa-wallet', 'title' => 'معاملات المحفظة', 'description' => 'توفير معلومات عن التغيرات التاريخية في النقد ومعدلات التدفقات النقدية.', 'query_preview' => '-- استعلام معاملات المحفظة'],
        ];
    }

    private function legacyReportTypeToInteractiveKey(?string $legacyReportType): ?string
    {
        return match ($legacyReportType) {
            'income_statement' => 'finance_income_statement',
            'account_balances' => 'finance_general_ledger',
            'product_sales' => 'sales_by_category',
            'expense_details' => 'finance_expense_categories',
            'receivables' => 'customer_statement',
            'payables' => 'warehouse_supplier_payables',
            'tax_summary' => 'tax_summary_live',
            default => null,
        };
    }

    private function interactiveKeyToLegacyReportType(string $interactiveKey): string
    {
        return match ($interactiveKey) {
            'finance_income_statement' => 'income_statement',
            'finance_general_ledger' => 'account_balances',
            'finance_expense_categories' => 'expense_details',
            'finance_wallet_transactions' => 'cash_flow',
            'finance_balance_sheet' => 'balance_sheet',
            'finance_trial_balance' => 'trial_balance',
            'finance_account_statements' => 'receivables',
            'sales_by_category', 'sales_by_location', 'sales_by_invoice', 'sales_by_customer', 'sales_by_payment_status', 'customer_transactions', 'sales_by_employee', 'sales_by_channel', 'transactions_by_branch', 'customer_product_sales', 'sales_by_period' => 'product_sales',
            'customer_statement' => 'receivables',
            'warehouse_supplier_payables', 'warehouse_purchase_summary', 'warehouse_purchase_details' => 'payables',
            default => 'income_statement',
        };
    }

    private function buildInteractiveReportResponse(Company $company, string $reportKey, Carbon $dateFrom, Carbon $dateTo): array
    {
        $catalog = $this->interactiveReportCatalog();
        $meta = $catalog[$reportKey];

        $report = match ($reportKey) {
            'sales_by_location' => $this->salesByLocationInteractiveReport($company, $dateFrom, $dateTo),
            'sales_by_invoice' => $this->salesByInvoiceInteractiveReport($company, $dateFrom, $dateTo),
            'sales_by_category' => $this->salesByCategoryInteractiveReport($company, $dateFrom, $dateTo),
            'sales_by_employee' => $this->salesByEmployeeInteractiveReport($company, $dateFrom, $dateTo),
            'sales_by_payment_status' => $this->salesByPaymentStatusInteractiveReport($company, $dateFrom, $dateTo),
            'customer_transactions' => $this->customerTransactionsInteractiveReport($company, $dateFrom, $dateTo),
            'sales_by_channel' => $this->salesByChannelInteractiveReport($company, $dateFrom, $dateTo),
            'sales_by_customer' => $this->salesByCustomerInteractiveReport($company, $dateFrom, $dateTo),
            'transactions_by_branch' => $this->transactionsByBranchInteractiveReport($company, $dateFrom, $dateTo),
            'customer_product_sales' => $this->customerProductSalesInteractiveReport($company, $dateFrom, $dateTo),
            'sales_by_period' => $this->salesByPeriodInteractiveReport($company, $dateFrom, $dateTo),
            'customer_statement' => $this->customerStatementInteractiveReport($company, $dateFrom, $dateTo),
            'tax_return' => $this->taxReturnInteractiveReport($company, $dateFrom, $dateTo),
            'warehouse_coverage' => $this->warehouseCoverageInteractiveReport($company),
            'warehouse_incoming' => $this->warehouseIncomingInteractiveReport($company, $dateFrom, $dateTo),
            'warehouse_suppliers' => $this->warehouseSuppliersInteractiveReport($company, $dateFrom, $dateTo),
            'finance_income_statement' => $this->interactiveFromLegacy($this->incomeStatementReport($company, $dateFrom, $dateTo)),
            'finance_receivables' => $this->interactiveFromLegacy($this->receivablesReport($company, [], $dateFrom, $dateTo)),
            'finance_payables' => $this->interactiveFromLegacy($this->payablesReport($company, [], $dateFrom, $dateTo)),
            'finance_expenses' => $this->interactiveFromLegacy($this->expenseDetailsReport($company, [], $dateFrom, $dateTo)),
            default => $this->interactiveFromLegacy($this->accountBalancesReport($company, [], $dateFrom, $dateTo)),
        };

        return array_merge($report, [
            'key' => $reportKey,
            'section' => $meta['section'],
            'title' => $meta['title'],
            'description' => $meta['description'],
            'query_preview' => $meta['query_preview'],
            'value_format' => $report['value_format'] ?? 'currency',
            'date_range_label' => sprintf('من %s إلى %s', $dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')),
            'columns' => $report['columns'] ?? [
                ['key' => 'label', 'label' => 'البند'],
                ['key' => 'meta', 'label' => 'التفاصيل'],
                ['key' => 'value', 'label' => 'القيمة', 'format' => 'currency'],
            ],
        ]);
    }

    private function interactiveFromLegacy(array $report): array
    {
        return [
            'supported' => true,
            'rows' => $report['rows'],
            'chart' => $report['chart'],
            'highlights' => $report['highlights'],
            'value_format' => 'currency',
            'empty_message' => $report['empty_message'],
            'insight' => $report['rows']->isNotEmpty() ? 'أعلى بند حالياً: ' . $report['rows']->first()['label'] : $report['empty_message'],
        ];
    }

    private function unsupportedInteractiveReport(string $message): array
    {
        return [
            'supported' => false,
            'rows' => collect(),
            'chart' => ['type' => 'bar', 'labels' => [], 'values' => []],
            'highlights' => [
                ['label' => 'عدد النتائج', 'value' => 0, 'format' => 'number'],
                ['label' => 'الإجمالي', 'value' => 0, 'format' => 'currency'],
                ['label' => 'أعلى نتيجة', 'value' => 0, 'format' => 'currency'],
            ],
            'value_format' => 'currency',
            'empty_message' => $message,
            'insight' => $message,
        ];
    }

    private function salesByLocationInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = DB::table('invoices as i')
            ->leftJoin('branches as b', 'b.id', '=', 'i.branch_id')
            ->leftJoin('invoice_items as ii', 'ii.invoice_id', '=', 'i.id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->where('i.company_id', $company->id)
            ->whereIn('i.status', ['sent', 'partial', 'paid'])
            ->whereBetween('i.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw("COALESCE(NULLIF(b.name, ''), 'غير محدد') as location_name, COALESCE(SUM(ii.total - ii.tax_amount), 0) as net_sales, COALESCE(SUM(ii.tax_amount), 0) as tax_amount, COALESCE(SUM(ii.total), 0) as gross_sales, COALESCE(SUM(ii.quantity), 0) as sold_qty, COALESCE(SUM(COALESCE(p.cost_price, 0) * ii.quantity), 0) as cogs")
            ->groupBy('location_name')
            ->orderByDesc('gross_sales')
            ->get()
            ->map(function ($row) {
                $profit = (float) $row->net_sales - (float) $row->cogs;

                return [
                    'location' => $row->location_name,
                    'sales' => (float) $row->net_sales,
                    'cogs' => (float) $row->cogs,
                    'profit' => $profit,
                    'sales_tax' => (float) $row->tax_amount,
                    'sales_with_tax' => (float) $row->gross_sales,
                    'sold_quantity' => (float) $row->sold_qty,
                    'returned_quantity' => 0,
                    'label' => $row->location_name,
                    'value' => (float) $row->gross_sales,
                    'format' => 'currency',
                ];
            });

        $report = $this->interactiveCollectionReport($rows, 'لا توجد بيانات مبيعات موزعة على مواقع خلال الفترة المحددة.');
        $report['columns'] = [
            ['key' => 'location', 'label' => 'الموقع', 'format' => 'text'],
            ['key' => 'sales', 'label' => 'المبيعات', 'format' => 'currency'],
            ['key' => 'cogs', 'label' => 'تكلفة البضاعة المباعة', 'format' => 'currency'],
            ['key' => 'profit', 'label' => 'قيمة الربح', 'format' => 'currency'],
            ['key' => 'sales_tax', 'label' => 'ضريبة المبيعات', 'format' => 'currency'],
            ['key' => 'sales_with_tax', 'label' => 'المبيعات (شاملة الضريبة)', 'format' => 'currency'],
            ['key' => 'sold_quantity', 'label' => 'الكمية المباعة', 'format' => 'number'],
            ['key' => 'returned_quantity', 'label' => 'الكمية المرتجعة', 'format' => 'number'],
        ];

        return $report;
    }

    private function salesByInvoiceInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $summary = DB::table('invoices as i')
            ->leftJoin('invoice_items as ii', 'ii.invoice_id', '=', 'i.id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->where('i.company_id', $company->id)
            ->whereIn('i.status', ['sent', 'partial', 'paid'])
            ->whereBetween('i.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('COUNT(DISTINCT i.id) as invoice_count, COALESCE(SUM(i.subtotal), 0) as total_sales, COALESCE(AVG(i.subtotal), 0) as average_invoice_sales, COALESCE(SUM(i.total), 0) as sales_with_tax, COALESCE(SUM(COALESCE(p.cost_price, 0) * ii.quantity), 0) as cogs, COALESCE(SUM(ii.quantity), 0) as sold_qty, COALESCE(SUM(ii.tax_amount), 0) as total_tax')
            ->first();

        $rows = collect();

        if ($summary && (int) $summary->invoice_count > 0) {
            $profit = (float) $summary->total_sales - (float) $summary->cogs;
            $rows->push([
                'total_sales' => (float) $summary->total_sales,
                'total_invoices' => (int) $summary->invoice_count,
                'average_invoice_sales' => (float) $summary->average_invoice_sales,
                'sales_with_tax' => (float) $summary->sales_with_tax,
                'total_cogs' => (float) $summary->cogs,
                'total_profit' => $profit,
                'total_sold_qty' => (float) $summary->sold_qty,
                'total_returned_qty' => 0,
                'vat_15' => (float) $summary->total_tax,
                'vat_100' => 0,
                'other_taxes' => 0,
                'total_tax' => (float) $summary->total_tax,
                'label' => 'ملخص الفواتير',
                'value' => (float) $summary->sales_with_tax,
                'format' => 'currency',
            ]);
        }

        $report = $this->interactiveCollectionReport($rows, 'لا توجد فواتير مبيعات ضمن الفترة المحددة.');
        $report['columns'] = [
            ['key' => 'total_sales', 'label' => 'إجمالي المبيعات', 'format' => 'currency'],
            ['key' => 'total_invoices', 'label' => 'إجمالي الفواتير', 'format' => 'number'],
            ['key' => 'average_invoice_sales', 'label' => 'متوسط مبيعات الفواتير', 'format' => 'currency'],
            ['key' => 'sales_with_tax', 'label' => 'المبيعات (شاملة الضريبة)', 'format' => 'currency'],
            ['key' => 'total_cogs', 'label' => 'إجمالي تكلفة البضاعة المباعة', 'format' => 'currency'],
            ['key' => 'total_profit', 'label' => 'إجمالي قيمة الربح', 'format' => 'currency'],
            ['key' => 'total_sold_qty', 'label' => 'إجمالي الكميات المباعة', 'format' => 'number'],
            ['key' => 'total_returned_qty', 'label' => 'إجمالي الكميات المرتجعة', 'format' => 'number'],
            ['key' => 'vat_15', 'label' => 'ضريبة المبيعات %15', 'format' => 'currency'],
            ['key' => 'vat_100', 'label' => 'ضريبة المبيعات %100', 'format' => 'currency'],
            ['key' => 'other_taxes', 'label' => 'الضرائب الأخرى', 'format' => 'currency'],
            ['key' => 'total_tax', 'label' => 'إجمالي الضريبة', 'format' => 'currency'],
        ];

        return $report;
    }

    private function salesByCategoryInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $summary = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->leftJoin('categories as c', 'c.id', '=', 'ii.category_id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->where('i.company_id', $company->id)
            ->whereIn('i.status', ['sent', 'partial', 'paid'])
            ->whereBetween('i.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw('COUNT(DISTINCT c.id) as category_count, COALESCE(SUM(ii.total - ii.tax_amount), 0) as total_sales, COALESCE(SUM(ii.total), 0) as sales_with_tax, COALESCE(SUM(ii.quantity), 0) as sold_qty, COALESCE(SUM(COALESCE(p.cost_price, 0) * ii.quantity), 0) as cogs')
            ->first();

        $rows = collect();

        if ($summary && ((int) $summary->category_count > 0 || (float) $summary->total_sales > 0)) {
            $averageCategorySales = (int) $summary->category_count > 0
                ? round((float) $summary->total_sales / (int) $summary->category_count, 2)
                : 0;

            $rows->push([
                'total_sales' => (float) $summary->total_sales,
                'total_categories' => (int) $summary->category_count,
                'average_category_sales' => $averageCategorySales,
                'sales_with_tax' => (float) $summary->sales_with_tax,
                'total_sold_qty' => (float) $summary->sold_qty,
                'total_returned_qty' => 0,
                'total_cogs' => (float) $summary->cogs,
                'total_profit' => round((float) $summary->total_sales - (float) $summary->cogs, 2),
                'label' => 'ملخص الفئات',
                'value' => (float) $summary->sales_with_tax,
                'format' => 'currency',
            ]);
        }

        $report = $this->interactiveCollectionReport($rows, 'لا توجد بيانات مبيعات حسب الفئات خلال الفترة المحددة.');
        $report['columns'] = [
            ['key' => 'total_sales', 'label' => 'إجمالي المبيعات', 'format' => 'currency'],
            ['key' => 'total_categories', 'label' => 'إجمالي الفئات', 'format' => 'number'],
            ['key' => 'average_category_sales', 'label' => 'متوسط مبيعات الفئات', 'format' => 'currency'],
            ['key' => 'sales_with_tax', 'label' => 'إجمالي المبيعات (شاملة الضريبة)', 'format' => 'currency'],
            ['key' => 'total_sold_qty', 'label' => 'إجمالي الكميات المباعة', 'format' => 'number'],
            ['key' => 'total_returned_qty', 'label' => 'إجمالي الكميات المرتجعة', 'format' => 'number'],
            ['key' => 'total_cogs', 'label' => 'إجمالي تكلفة البضاعة المباعة', 'format' => 'currency'],
            ['key' => 'total_profit', 'label' => 'إجمالي قيمة الربح', 'format' => 'currency'],
        ];

        return $report;
    }

    private function salesByEmployeeInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = DB::table('invoices as i')
            ->leftJoin('employees', 'employees.id', '=', 'i.employee_id')
            ->leftJoin('branches as b', 'b.id', '=', 'i.branch_id')
            ->leftJoin('invoice_items as ii', 'ii.invoice_id', '=', 'i.id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->where('i.company_id', $company->id)
            ->whereIn('i.status', ['sent', 'partial', 'paid'])
            ->whereBetween('i.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'employees.position', 'b.name')
            ->selectRaw("employees.first_name, employees.last_name, employees.position, COALESCE(NULLIF(b.name, ''), 'غير محدد') as branch_name, COALESCE(SUM(ii.total - ii.tax_amount), 0) as net_sales, COALESCE(SUM(COALESCE(p.cost_price, 0) * ii.quantity), 0) as cogs, COALESCE(SUM(ii.total), 0) as gross_sales, COALESCE(SUM(ii.quantity), 0) as sold_qty")
            ->orderByDesc('gross_sales')
            ->get()
            ->map(function ($row) {
                $fullName = trim(implode(' / ', array_filter([
                    trim(implode(' ', array_filter([$row->first_name, $row->last_name]))),
                    $row->position,
                ])));

                return [
                    'employee' => $fullName !== '' ? $fullName : 'غير محدد',
                    'location' => $row->branch_name,
                    'sales' => (float) $row->net_sales,
                    'cogs' => (float) $row->cogs,
                    'profit' => round((float) $row->net_sales - (float) $row->cogs, 2),
                    'sales_with_tax' => (float) $row->gross_sales,
                    'sold_quantity' => (float) $row->sold_qty,
                    'returned_quantity' => 0,
                    'label' => $fullName !== '' ? $fullName : 'غير محدد',
                    'value' => (float) $row->gross_sales,
                    'format' => 'currency',
                ];
            });

        $report = $this->interactiveCollectionReport($rows, 'لا توجد مبيعات مرتبطة بموظفين خلال الفترة المحددة.');
        $report['columns'] = [
            ['key' => 'employee', 'label' => 'المستخدم / الوظيفة', 'format' => 'text'],
            ['key' => 'location', 'label' => 'الموقع', 'format' => 'text'],
            ['key' => 'sales', 'label' => 'المبيعات', 'format' => 'currency'],
            ['key' => 'cogs', 'label' => 'تكلفة البضاعة المباعة', 'format' => 'currency'],
            ['key' => 'profit', 'label' => 'قيمة الربح', 'format' => 'currency'],
            ['key' => 'sales_with_tax', 'label' => 'المبيعات (شاملة الضريبة)', 'format' => 'currency'],
            ['key' => 'sold_quantity', 'label' => 'الكمية المباعة', 'format' => 'number'],
            ['key' => 'returned_quantity', 'label' => 'الكمية المرتجعة', 'format' => 'number'],
        ];

        return $report;
    }

    private function salesByPaymentStatusInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $labels = ['full' => 'كامل', 'partial' => 'جزئي', 'deferred' => 'آجل', 'paid' => 'كامل', 'pending' => 'آجل'];

        $rows = DB::table('invoices as i')
            ->leftJoin('branches as b', 'b.id', '=', 'i.branch_id')
            ->leftJoin('invoice_items as ii', 'ii.invoice_id', '=', 'i.id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->where('i.company_id', $company->id)
            ->whereIn('i.status', ['sent', 'partial', 'paid'])
            ->whereBetween('i.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw("i.payment_status as status_key, COALESCE(NULLIF(b.name, ''), 'غير محدد') as branch_name, COALESCE(SUM(ii.total - ii.tax_amount), 0) as net_sales, COALESCE(SUM(COALESCE(p.cost_price, 0) * ii.quantity), 0) as cogs, COALESCE(SUM(ii.total), 0) as gross_sales, COALESCE(SUM(ii.quantity), 0) as sold_qty")
            ->groupBy('status_key', 'branch_name')
            ->orderByDesc('gross_sales')
            ->get()
            ->map(fn($row) => [
                'payment_status' => $labels[$row->status_key] ?? $row->status_key,
                'location' => $row->branch_name,
                'sales' => (float) $row->net_sales,
                'cogs' => (float) $row->cogs,
                'profit' => round((float) $row->net_sales - (float) $row->cogs, 2),
                'sales_with_tax' => (float) $row->gross_sales,
                'sold_quantity' => (float) $row->sold_qty,
                'returned_quantity' => 0,
                'label' => $labels[$row->status_key] ?? $row->status_key,
                'value' => (float) $row->gross_sales,
                'format' => 'currency',
            ]);

        $report = $this->interactiveCollectionReport($rows, 'لا توجد حالات دفع ضمن الفترة المحددة.');
        $report['columns'] = [
            ['key' => 'payment_status', 'label' => 'حالة الدفع', 'format' => 'text'],
            ['key' => 'location', 'label' => 'الموقع', 'format' => 'text'],
            ['key' => 'sales', 'label' => 'المبيعات', 'format' => 'currency'],
            ['key' => 'cogs', 'label' => 'تكلفة البضاعة المباعة', 'format' => 'currency'],
            ['key' => 'profit', 'label' => 'قيمة الربح', 'format' => 'currency'],
            ['key' => 'sales_with_tax', 'label' => 'المبيعات (شاملة الضريبة)', 'format' => 'currency'],
            ['key' => 'sold_quantity', 'label' => 'الكمية المباعة', 'format' => 'number'],
            ['key' => 'returned_quantity', 'label' => 'الكمية المرتجعة', 'format' => 'number'],
        ];

        return $report;
    }

    private function customerTransactionsInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = DB::table('invoices as i')
            ->join('customers', 'customers.id', '=', 'i.customer_id')
            ->leftJoin('branches as b', 'b.id', '=', 'i.branch_id')
            ->leftJoin('sales_channels as sc', 'sc.id', '=', 'i.sales_channel_id')
            ->leftJoin('employees', 'employees.id', '=', 'i.employee_id')
            ->leftJoin('invoice_items as ii', 'ii.invoice_id', '=', 'i.id')
            ->where('i.company_id', $company->id)
            ->whereIn('i.status', ['sent', 'partial', 'paid'])
            ->whereBetween('i.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->groupBy('i.id', 'customers.name', 'customers.phone', 'b.name', 'i.invoice_number', 'i.invoice_date', 'i.paid_amount', 'i.total', 'sc.name', 'employees.first_name', 'employees.last_name')
            ->selectRaw("customers.name as customer_name, COALESCE(customers.phone, '') as customer_phone, COALESCE(NULLIF(b.name, ''), 'غير محدد') as branch_name, i.invoice_number, i.invoice_date, COALESCE(SUM(ii.quantity), 0) as qty_per_transaction, i.paid_amount, i.total, COALESCE(NULLIF(sc.name, ''), 'غير محدد') as sales_channel_name, employees.first_name, employees.last_name")
            ->orderByDesc('i.invoice_date')
            ->get()
            ->map(function ($row) {
                $employeeName = trim(implode(' ', array_filter([$row->first_name, $row->last_name])));

                return [
                    'customer_name' => $row->customer_name,
                    'customer_phone' => $row->customer_phone,
                    'location' => $row->branch_name,
                    'transaction_reference' => $row->invoice_number,
                    'transaction_type' => 'بيع',
                    'transaction_date' => $row->invoice_date,
                    'quantity_per_transaction' => (float) $row->qty_per_transaction,
                    'paid_amount' => (float) $row->paid_amount,
                    'received_amount' => (float) $row->total,
                    'sales_channel' => $row->sales_channel_name,
                    'user_name' => $employeeName !== '' ? $employeeName : 'غير محدد',
                    'label' => $row->customer_name,
                    'value' => (float) $row->total,
                    'format' => 'currency',
                ];
            });

        $report = $this->interactiveCollectionReport($rows, 'لا توجد معاملات عملاء خلال الفترة المحددة.');
        $report['columns'] = [
            ['key' => 'customer_name', 'label' => 'اسم العميل', 'format' => 'text'],
            ['key' => 'customer_phone', 'label' => 'رقم هاتف العميل', 'format' => 'text'],
            ['key' => 'location', 'label' => 'الموقع', 'format' => 'text'],
            ['key' => 'transaction_reference', 'label' => 'مرجع العملية', 'format' => 'text'],
            ['key' => 'transaction_type', 'label' => 'نوع العملية', 'format' => 'text'],
            ['key' => 'transaction_date', 'label' => 'تاريخ العملية', 'format' => 'date'],
            ['key' => 'quantity_per_transaction', 'label' => 'الكمية لكل عملية', 'format' => 'number'],
            ['key' => 'paid_amount', 'label' => 'المبلغ المدفوع', 'format' => 'currency'],
            ['key' => 'received_amount', 'label' => 'المستلم', 'format' => 'currency'],
            ['key' => 'sales_channel', 'label' => 'قناة البيع', 'format' => 'text'],
            ['key' => 'user_name', 'label' => 'المستخدم', 'format' => 'text'],
        ];

        return $report;
    }

    private function salesByChannelInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = DB::table('invoices as i')
            ->leftJoin('sales_channels as sc', 'sc.id', '=', 'i.sales_channel_id')
            ->leftJoin('branches as b', 'b.id', '=', 'i.branch_id')
            ->leftJoin('invoice_items as ii', 'ii.invoice_id', '=', 'i.id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->where('i.company_id', $company->id)
            ->whereIn('i.status', ['sent', 'partial', 'paid'])
            ->whereBetween('i.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw("COALESCE(NULLIF(sc.name, ''), 'غير محدد') as channel_name, COALESCE(NULLIF(b.name, ''), 'غير محدد') as branch_name, COALESCE(SUM(ii.total - ii.tax_amount), 0) as net_sales, COALESCE(SUM(COALESCE(p.cost_price, 0) * ii.quantity), 0) as cogs, COALESCE(SUM(ii.total), 0) as gross_sales, COALESCE(SUM(ii.quantity), 0) as sold_qty")
            ->groupBy('channel_name', 'branch_name')
            ->orderByDesc('gross_sales')
            ->get()
            ->map(fn($row) => [
                'sales_channel' => $row->channel_name,
                'location' => $row->branch_name,
                'sales' => (float) $row->net_sales,
                'cogs' => (float) $row->cogs,
                'profit' => round((float) $row->net_sales - (float) $row->cogs, 2),
                'sales_with_tax' => (float) $row->gross_sales,
                'sold_quantity' => (float) $row->sold_qty,
                'returned_quantity' => 0,
                'label' => $row->channel_name,
                'value' => (float) $row->gross_sales,
                'format' => 'currency',
            ]);

        $report = $this->interactiveCollectionReport($rows, 'لا توجد قنوات بيع مسجلة خلال الفترة المحددة.');
        $report['columns'] = [
            ['key' => 'sales_channel', 'label' => 'قناة البيع', 'format' => 'text'],
            ['key' => 'location', 'label' => 'الموقع', 'format' => 'text'],
            ['key' => 'sales', 'label' => 'المبيعات', 'format' => 'currency'],
            ['key' => 'cogs', 'label' => 'تكلفة البضاعة المباعة', 'format' => 'currency'],
            ['key' => 'profit', 'label' => 'قيمة الربح', 'format' => 'currency'],
            ['key' => 'sales_with_tax', 'label' => 'المبيعات (شاملة الضريبة)', 'format' => 'currency'],
            ['key' => 'sold_quantity', 'label' => 'الكمية المباعة', 'format' => 'number'],
            ['key' => 'returned_quantity', 'label' => 'الكمية المرتجعة', 'format' => 'number'],
        ];

        return $report;
    }

    private function salesByCustomerInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = DB::table('invoices as i')
            ->join('customers', 'customers.id', '=', 'i.customer_id')
            ->leftJoin('branches as b', 'b.id', '=', 'i.branch_id')
            ->leftJoin('invoice_items as ii', 'ii.invoice_id', '=', 'i.id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->where('i.company_id', $company->id)
            ->whereIn('i.status', ['sent', 'partial', 'paid'])
            ->whereBetween('i.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw("customers.name as customer_name, COALESCE(customers.phone, '') as customer_phone, COALESCE(NULLIF(b.name, ''), 'غير محدد') as branch_name, COALESCE(SUM(ii.total - ii.tax_amount), 0) as net_sales, COALESCE(SUM(COALESCE(p.cost_price, 0) * ii.quantity), 0) as cogs, COALESCE(SUM(ii.total), 0) as gross_sales, COALESCE(SUM(ii.quantity), 0) as sold_qty")
            ->groupBy('customers.id', 'customers.name', 'customers.phone', 'branch_name')
            ->orderByDesc('gross_sales')
            ->get()
            ->map(fn($row) => [
                'customer_name' => $row->customer_name,
                'customer_phone' => $row->customer_phone,
                'location' => $row->branch_name,
                'sales' => (float) $row->net_sales,
                'cogs' => (float) $row->cogs,
                'profit' => round((float) $row->net_sales - (float) $row->cogs, 2),
                'sales_with_tax' => (float) $row->gross_sales,
                'sold_quantity' => (float) $row->sold_qty,
                'returned_quantity' => 0,
                'label' => $row->customer_name,
                'value' => (float) $row->gross_sales,
                'format' => 'currency',
            ]);

        $report = $this->interactiveCollectionReport($rows, 'لا توجد مبيعات مجمعة حسب العملاء خلال الفترة المحددة.');
        $report['columns'] = [
            ['key' => 'customer_name', 'label' => 'اسم العميل', 'format' => 'text'],
            ['key' => 'customer_phone', 'label' => 'رقم هاتف العميل', 'format' => 'text'],
            ['key' => 'location', 'label' => 'الموقع', 'format' => 'text'],
            ['key' => 'sales', 'label' => 'المبيعات', 'format' => 'currency'],
            ['key' => 'cogs', 'label' => 'تكلفة البضاعة المباعة', 'format' => 'currency'],
            ['key' => 'profit', 'label' => 'قيمة الربح', 'format' => 'currency'],
            ['key' => 'sales_with_tax', 'label' => 'المبيعات (شامل الضريبة)', 'format' => 'currency'],
            ['key' => 'sold_quantity', 'label' => 'الكمية المباعة', 'format' => 'number'],
            ['key' => 'returned_quantity', 'label' => 'الكمية المرتجعة', 'format' => 'number'],
        ];

        return $report;
    }

    private function transactionsByBranchInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = DB::table('invoices as i')
            ->leftJoin('branches as b', 'b.id', '=', 'i.branch_id')
            ->leftJoin('sales_channels as sc', 'sc.id', '=', 'i.sales_channel_id')
            ->leftJoin('employees', 'employees.id', '=', 'i.employee_id')
            ->leftJoin('invoice_items as ii', 'ii.invoice_id', '=', 'i.id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->where('i.company_id', $company->id)
            ->whereIn('i.status', ['sent', 'partial', 'paid'])
            ->whereBetween('i.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->groupBy('i.id', 'b.name', 'b.code', 'i.invoice_number', 'i.invoice_date', 'i.total', 'sc.name', 'employees.first_name', 'employees.last_name')
            ->orderByDesc('i.invoice_date')
            ->selectRaw("COALESCE(NULLIF(b.name, ''), 'غير محدد') as branch_name, COALESCE(NULLIF(b.code, ''), '-') as branch_code, i.invoice_number, i.invoice_date, COALESCE(SUM(ii.quantity), 0) as qty_per_transaction, i.total as amount_paid_received, COALESCE(SUM(COALESCE(p.cost_price, 0) * ii.quantity), 0) as operation_cost, COALESCE(NULLIF(sc.name, ''), 'غير محدد') as sales_channel_name, employees.first_name, employees.last_name")
            ->get()
            ->map(function ($row) {
                $employeeName = trim(implode(' ', array_filter([$row->first_name, $row->last_name])));

                return [
                    'location' => $row->branch_name,
                    'location_code' => $row->branch_code,
                    'transaction_reference' => $row->invoice_number,
                    'transaction_type' => 'بيع',
                    'transaction_date' => $row->invoice_date,
                    'quantity_per_transaction' => (float) $row->qty_per_transaction,
                    'paid_or_received_amount' => (float) $row->amount_paid_received,
                    'operation_cost' => (float) $row->operation_cost,
                    'sales_channel' => $row->sales_channel_name,
                    'user_name' => $employeeName !== '' ? $employeeName : 'غير محدد',
                    'label' => $row->invoice_number,
                    'value' => (float) $row->amount_paid_received,
                    'format' => 'currency',
                ];
            });

        $report = $this->interactiveCollectionReport($rows, 'لا توجد معاملات موزعة على المواقع خلال الفترة المحددة.');
        $report['columns'] = [
            ['key' => 'location', 'label' => 'الموقع', 'format' => 'text'],
            ['key' => 'location_code', 'label' => 'رمز الموقع', 'format' => 'text'],
            ['key' => 'transaction_reference', 'label' => 'مرجع العملية', 'format' => 'text'],
            ['key' => 'transaction_type', 'label' => 'نوع العملية', 'format' => 'text'],
            ['key' => 'transaction_date', 'label' => 'تاريخ العملية', 'format' => 'date'],
            ['key' => 'quantity_per_transaction', 'label' => 'الكمية لكل عملية', 'format' => 'number'],
            ['key' => 'paid_or_received_amount', 'label' => 'المبلغ المدفوع/المستلم', 'format' => 'currency'],
            ['key' => 'operation_cost', 'label' => 'تكلفة العملية', 'format' => 'currency'],
            ['key' => 'sales_channel', 'label' => 'قناة البيع', 'format' => 'text'],
            ['key' => 'user_name', 'label' => 'المستخدم', 'format' => 'text'],
        ];

        return $report;
    }

    private function customerProductSalesInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = DB::table('sales_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('customers', 'customers.id', '=', 's.customer_id')
            ->leftJoin('products', 'products.id', '=', 'si.product_id')
            ->where('s.company_id', $company->id)
            ->whereIn('s.status', ['sent', 'partial', 'paid'])
            ->whereBetween('s.sale_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->groupBy('customers.id', 'customers.name', 'products.id', 'products.name', 'si.description')
            ->selectRaw("customers.name as customer_name, COALESCE(NULLIF(products.name, ''), si.description) as product_name, SUM(si.quantity) as sold_qty, SUM(si.total_amount) as total_sales")
            ->orderByDesc('sold_qty')
            ->get()
            ->map(fn($row) => ['label' => $row->customer_name . ' / ' . $row->product_name, 'meta' => 'إجمالي المبيعات: ' . number_format((float) $row->total_sales, 2), 'value' => (float) $row->sold_qty, 'format' => 'number']);

        return $this->interactiveCollectionReport($rows, 'لا توجد منتجات مباعة للعملاء خلال الفترة المحددة.', 'number');
    }

    private function salesByPeriodInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->leftJoin('branches as b', 'b.id', '=', 'i.branch_id')
            ->where('i.company_id', $company->id)
            ->whereIn('i.status', ['sent', 'partial', 'paid'])
            ->whereBetween('i.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw("i.invoice_date, COALESCE(NULLIF(ii.description, ''), NULLIF(p.name, ''), 'غير محدد') as product_name, COALESCE(NULLIF(p.code, ''), '-') as product_number, COALESCE(NULLIF(b.name, ''), 'غير محدد') as branch_name, COALESCE(SUM(ii.quantity), 0) as quantity, COALESCE(AVG(COALESCE(p.cost_price, 0)), 0) as unit_cost, COALESCE(SUM(ii.total - ii.tax_amount), 0) as net_sales, 0 as discount_amount, COALESCE(SUM(ii.tax_amount), 0) as sales_tax, COALESCE(SUM(ii.total), 0) as gross_sales, COALESCE(SUM(COALESCE(p.cost_price, 0) * ii.quantity), 0) as cogs")
            ->groupBy('i.invoice_date', 'product_name', 'product_number', 'branch_name')
            ->orderBy('i.invoice_date')
            ->get()
            ->map(fn($row) => [
                'transaction_date' => $row->invoice_date,
                'product_name' => $row->product_name,
                'product_number' => $row->product_number,
                'location' => $row->branch_name,
                'quantity' => (float) $row->quantity,
                'unit_cost' => (float) $row->unit_cost,
                'profit' => round((float) $row->net_sales - (float) $row->cogs, 2),
                'sales' => (float) $row->net_sales,
                'discount' => (float) $row->discount_amount,
                'sales_tax' => (float) $row->sales_tax,
                'sales_with_tax' => (float) $row->gross_sales,
                'label' => $row->invoice_date,
                'value' => (float) $row->gross_sales,
                'format' => 'currency',
            ]);

        $report = $this->interactiveCollectionReport($rows, 'لا توجد مبيعات ضمن الفترة الزمنية المحددة.');
        $report['columns'] = [
            ['key' => 'transaction_date', 'label' => 'تاريخ العملية', 'format' => 'date'],
            ['key' => 'product_name', 'label' => 'اسم المنتج/الرقم التعريفي', 'format' => 'text'],
            ['key' => 'product_number', 'label' => 'رقم المنتج', 'format' => 'text'],
            ['key' => 'location', 'label' => 'الموقع', 'format' => 'text'],
            ['key' => 'quantity', 'label' => 'الكمية', 'format' => 'number'],
            ['key' => 'unit_cost', 'label' => 'التكلفة لكل وحدة', 'format' => 'currency'],
            ['key' => 'profit', 'label' => 'قيمة الربح', 'format' => 'currency'],
            ['key' => 'sales', 'label' => 'المبيعات', 'format' => 'currency'],
            ['key' => 'discount', 'label' => 'الخصم', 'format' => 'currency'],
            ['key' => 'sales_tax', 'label' => 'ضريبة المبيعات', 'format' => 'currency'],
            ['key' => 'sales_with_tax', 'label' => 'المبيعات (شامل الضريبة)', 'format' => 'currency'],
        ];

        return $report;
    }

    private function customerStatementInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $agingDate = $dateTo->copy()->endOfDay();

        $rows = DB::table('invoices as i')
            ->join('customers', 'customers.id', '=', 'i.customer_id')
            ->where('i.company_id', $company->id)
            ->whereIn('i.status', ['sent', 'partial', 'paid'])
            ->whereBetween('i.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->orderBy('customers.name')
            ->get(['customers.name as customer_name', 'customers.tax_number', 'i.total', 'i.paid_amount', 'i.balance_due', 'i.due_date', 'i.invoice_date'])
            ->groupBy('customer_name')
            ->map(function ($customerInvoices, $customerName) use ($agingDate) {
                $firstInvoice = $customerInvoices->first();
                $bucket_1_15 = 0.0;
                $bucket_16_31 = 0.0;
                $bucket_31_60 = 0.0;
                $bucket_61_90 = 0.0;
                $bucket_over_90 = 0.0;

                foreach ($customerInvoices as $invoice) {
                    $balance = (float) $invoice->balance_due;
                    if ($balance <= 0) {
                        continue;
                    }

                    $referenceDate = $invoice->due_date ?: $invoice->invoice_date;
                    $ageInDays = Carbon::parse($referenceDate)->diffInDays($agingDate, false);

                    if ($ageInDays >= 1 && $ageInDays <= 15) {
                        $bucket_1_15 += $balance;
                    } elseif ($ageInDays >= 16 && $ageInDays <= 31) {
                        $bucket_16_31 += $balance;
                    } elseif ($ageInDays >= 32 && $ageInDays <= 60) {
                        $bucket_31_60 += $balance;
                    } elseif ($ageInDays >= 61 && $ageInDays <= 90) {
                        $bucket_61_90 += $balance;
                    } elseif ($ageInDays > 90) {
                        $bucket_over_90 += $balance;
                    }
                }

                return [
                    'customer_name' => $customerName,
                    'customer_tax_number' => $firstInvoice->tax_number ?? '',
                    'sales_with_tax' => round((float) $customerInvoices->sum('total'), 2),
                    'paid_amount' => round((float) $customerInvoices->sum('paid_amount'), 2),
                    'outstanding_amount' => round((float) $customerInvoices->sum('balance_due'), 2),
                    'bucket_1_15' => round($bucket_1_15, 2),
                    'bucket_16_31' => round($bucket_16_31, 2),
                    'bucket_31_60' => round($bucket_31_60, 2),
                    'bucket_61_90' => round($bucket_61_90, 2),
                    'bucket_over_90' => round($bucket_over_90, 2),
                    'label' => $customerName,
                    'value' => round((float) $customerInvoices->sum('balance_due'), 2),
                    'format' => 'currency',
                ];
            })
            ->filter(fn($row) => $row['sales_with_tax'] > 0 || $row['outstanding_amount'] > 0)
            ->sortByDesc('outstanding_amount')
            ->values();

        $report = $this->interactiveCollectionReport($rows, 'لا توجد بيانات كافية لإظهار كشف حساب المدين خلال الفترة المحددة.');
        $report['columns'] = [
            ['key' => 'customer_name', 'label' => 'اسم العميل', 'format' => 'text'],
            ['key' => 'customer_tax_number', 'label' => 'الرقم الضريبي للعميل', 'format' => 'text'],
            ['key' => 'sales_with_tax', 'label' => 'المبيعات (شاملة الضريبة)', 'format' => 'currency'],
            ['key' => 'paid_amount', 'label' => 'المبلغ المدفوع', 'format' => 'currency'],
            ['key' => 'outstanding_amount', 'label' => 'المبلغ المستحق', 'format' => 'currency'],
            ['key' => 'bucket_1_15', 'label' => 'المبلغ المستحق خلال 1 - 15 يوم', 'format' => 'currency'],
            ['key' => 'bucket_16_31', 'label' => 'المبلغ المستحق خلال 16 - 31 يوم', 'format' => 'currency'],
            ['key' => 'bucket_31_60', 'label' => 'المبلغ المستحق خلال 31 - 60 يوم', 'format' => 'currency'],
            ['key' => 'bucket_61_90', 'label' => 'المبلغ المستحق خلال 61 - 90 يوم', 'format' => 'currency'],
            ['key' => 'bucket_over_90', 'label' => 'المبلغ المستحق لأكثر من 90 يوم', 'format' => 'currency'],
        ];

        return $report;
    }

    private function inventorySnapshotInteractiveReport(Company $company): array
    {
        $rows = Product::forCompany($company->id)
            ->orderByDesc(DB::raw('stock_quantity * cost_price'))
            ->get()
            ->map(fn(Product $product) => ['label' => $product->name, 'meta' => sprintf('المخزون: %s | التكلفة: %s', number_format((float) $product->stock_quantity, 2), number_format((float) $product->cost_price, 2)), 'value' => round((float) $product->stock_quantity * (float) $product->cost_price, 2)]);

        return $this->interactiveCollectionReport($rows, 'لا توجد منتجات لعرض لقطة المخزون الحالية.');
    }

    private function lowStockAlertsInteractiveReport(Company $company): array
    {
        $rows = Product::forCompany($company->id)
            ->whereColumn('stock_quantity', '<=', 'min_stock')
            ->orderBy('stock_quantity')
            ->get()
            ->map(fn(Product $product) => ['label' => $product->name, 'meta' => sprintf('الرصيد الحالي: %s | الحد الأدنى: %s', number_format((float) $product->stock_quantity, 2), number_format((float) $product->min_stock, 2)), 'value' => max((float) $product->min_stock - (float) $product->stock_quantity, 0)]);

        return $this->interactiveCollectionReport($rows, 'لا توجد منتجات منخفضة المخزون حالياً.');
    }

    private function salesVelocityInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->where('invoices.company_id', $company->id)
            ->whereIn('invoices.status', ['sent', 'partial', 'paid'])
            ->whereBetween('invoices.invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw("COALESCE(products.name, invoice_items.description) as label, SUM(invoice_items.quantity) as sold_qty, SUM(invoice_items.total) as total_sales")
            ->groupBy('label')
            ->orderByDesc('sold_qty')
            ->get()
            ->map(fn($row) => ['label' => $row->label, 'meta' => 'إجمالي المبيعات: ' . number_format((float) $row->total_sales, 2), 'value' => (float) $row->sold_qty]);

        return $this->interactiveCollectionReport($rows, 'لا توجد حركة مبيعات كافية لحساب سرعة الدوران.');
    }

    private function taxOutputByInvoiceInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = Invoice::query()
            ->where('company_id', $company->id)
            ->whereBetween('invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('tax_amount', '>', 0)
            ->orderByDesc('invoice_date')
            ->get(['invoice_number', 'invoice_date', 'tax_amount', 'total'])
            ->map(fn(Invoice $invoice) => ['label' => $invoice->invoice_number, 'meta' => sprintf('التاريخ: %s | إجمالي الفاتورة: %s', optional($invoice->invoice_date)->format('Y-m-d'), number_format((float) $invoice->total, 2)), 'value' => (float) $invoice->tax_amount]);

        return $this->interactiveCollectionReport($rows, 'لا توجد ضرائب مبيعات ضمن الفترة المحددة.');
    }

    private function taxInputBySupplierInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = Purchase::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->where('purchases.company_id', $company->id)
            ->whereBetween('purchases.purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->groupBy('suppliers.id', 'suppliers.name')
            ->selectRaw('suppliers.name as label, COUNT(purchases.id) as purchase_count, SUM(purchases.tax_amount) as tax_total')
            ->orderByDesc('tax_total')
            ->get()
            ->map(fn($row) => ['label' => $row->label, 'meta' => 'عدد المشتريات: ' . $row->purchase_count, 'value' => (float) $row->tax_total]);

        return $this->interactiveCollectionReport($rows, 'لا توجد ضرائب مشتريات مجمعة حسب المورد خلال الفترة المحددة.');
    }

    private function taxReturnInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        // Sales (Output Tax)
        $outputVat = (float) Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partial', 'paid'])
            ->whereBetween('invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('tax_amount');

        $outputVat15 = (float) Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partial', 'paid'])
            ->whereBetween('invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('tax_type', 'vat_15')
            ->sum('tax_amount');

        $outputZero = (float) Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partial', 'paid'])
            ->whereBetween('invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('tax_type', 'vat_0')
            ->sum('total');

        $outputExempt = (float) Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partial', 'paid'])
            ->whereBetween('invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('tax_type', 'exempt')
            ->sum('total');

        $totalSales = (float) Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partial', 'paid'])
            ->whereBetween('invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('total');

        // Purchases (Input Tax)
        $inputVat = (float) Purchase::where('company_id', $company->id)
            ->whereBetween('purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('tax_amount');

        $inputVat15 = (float) Purchase::where('company_id', $company->id)
            ->whereBetween('purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('tax_type', 'vat_15')
            ->sum('tax_amount');

        $totalPurchases = (float) Purchase::where('company_id', $company->id)
            ->whereBetween('purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('total');

        // Calculate net tax
        $netTax = $outputVat - $inputVat;

        // Count documents
        $salesCount = Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partial', 'paid'])
            ->whereBetween('invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->count();

        $purchaseCount = Purchase::where('company_id', $company->id)
            ->whereBetween('purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->count();

        return [
            'type' => 'tax_return',
            'title' => 'الإقرار الضريبي',
            'period' => $dateFrom->format('Y-m-d') . ' - ' . $dateTo->format('Y-m-d'),
            'summary' => [
                'output_vat_15' => $outputVat15,
                'output_vat_total' => $outputVat,
                'output_zero_rated' => $outputZero,
                'output_exempt' => $outputExempt,
                'total_sales' => $totalSales,
                'input_vat_15' => $inputVat15,
                'input_vat_total' => $inputVat,
                'total_purchases' => $totalPurchases,
                'net_tax_payable' => $netTax,
                'sales_documents' => $salesCount,
                'purchase_documents' => $purchaseCount,
            ],
            'rows' => [],
            'empty_message' => 'لا توجد بيانات ضريبية للفترة المحددة.',
        ];
    }

    private function warehouseCoverageInteractiveReport(Company $company): array
    {
        $rows = Product::forCompany($company->id)
            ->orderByRaw('(stock_quantity - min_stock) asc')
            ->get()
            ->map(fn(Product $product) => ['label' => $product->name, 'meta' => sprintf('المخزون: %s | الحد الأدنى: %s', number_format((float) $product->stock_quantity, 2), number_format((float) $product->min_stock, 2)), 'value' => round((float) $product->stock_quantity - (float) $product->min_stock, 2)]);

        return $this->interactiveCollectionReport($rows, 'لا توجد بيانات كافية عن تغطية المخزون.');
    }

    private function warehouseIncomingInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->leftJoin('products', 'products.id', '=', 'purchase_items.product_id')
            ->where('purchases.company_id', $company->id)
            ->whereBetween('purchases.purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->selectRaw("COALESCE(products.name, purchase_items.description) as label, SUM(purchase_items.quantity) as incoming_qty, SUM(purchase_items.total) as total_value")
            ->groupBy('label')
            ->orderByDesc('incoming_qty')
            ->get()
            ->map(fn($row) => ['label' => $row->label, 'meta' => 'قيمة الوارد: ' . number_format((float) $row->total_value, 2), 'value' => (float) $row->incoming_qty]);

        return $this->interactiveCollectionReport($rows, 'لا توجد حركات وارد من المشتريات ضمن الفترة المحددة.');
    }

    private function warehouseSuppliersInteractiveReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $rows = Purchase::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->where('purchases.company_id', $company->id)
            ->whereBetween('purchases.purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->groupBy('suppliers.id', 'suppliers.name')
            ->selectRaw('suppliers.name as label, COUNT(purchases.id) as purchase_count, SUM(purchases.total) as total_received')
            ->orderByDesc('total_received')
            ->get()
            ->map(fn($row) => ['label' => $row->label, 'meta' => 'عدد أوامر الشراء: ' . $row->purchase_count, 'value' => (float) $row->total_received]);

        return $this->interactiveCollectionReport($rows, 'لا توجد استلامات موردين ضمن الفترة المحددة.');
    }

    private function interactiveCollectionReport(Collection $rows, string $emptyMessage, string $valueFormat = 'currency'): array
    {
        return [
            'supported' => true,
            'rows' => $rows,
            'chart' => [
                'type' => 'bar',
                'labels' => $rows->pluck('label')->take(8)->values(),
                'values' => $rows->pluck('value')->map(fn($value) => round((float) $value, 2))->take(8)->values(),
            ],
            'highlights' => [
                ['label' => 'عدد النتائج', 'value' => $rows->count(), 'format' => 'number'],
                ['label' => 'الإجمالي', 'value' => (float) $rows->sum('value'), 'format' => $valueFormat],
                ['label' => 'أعلى نتيجة', 'value' => $rows->isNotEmpty() ? (float) $rows->max('value') : 0, 'format' => $valueFormat],
            ],
            'value_format' => $valueFormat,
            'columns' => [
                ['key' => 'label', 'label' => 'البند'],
                ['key' => 'meta', 'label' => 'التفاصيل'],
                ['key' => 'value', 'label' => 'القيمة', 'format' => $valueFormat],
            ],
            'empty_message' => $emptyMessage,
            'insight' => $rows->isNotEmpty() ? 'الأداء الأعلى حالياً: ' . $rows->first()['label'] : $emptyMessage,
        ];
    }

    public function hr(Request $request): View
    {
        $company = $this->company($request);
        $employees = Employee::query()
            ->with(['branch', 'users'])
            ->where('company_id', $company->id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
        $branches = Branch::query()
            ->where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('hr', compact('company', 'employees', 'branches'));
    }

    public function storeEmployee(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateEmployeeData($request, $company->id);

        Employee::query()->create($this->employeePayload($validated, $company));

        return redirect()->route('employees.index')->with('success', 'تمت إضافة الموظف بنجاح.');
    }

    public function updateEmployee(Request $request, Employee $employee): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $employee->company_id !== (int) $company->id, 404);

        $validated = $this->validateEmployeeData($request, $company->id, $employee);
        $employee->update($this->employeePayload($validated, $company, $employee));

        return redirect()->route('employees.index')->with('success', 'تم تحديث بيانات الموظف بنجاح.');
    }

    public function destroyEmployee(Request $request, Employee $employee): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $employee->company_id !== (int) $company->id, 404);

        if ($employee->users()->exists()) {
            return redirect()->route('employees.index')->with('error', 'لا يمكن حذف موظف مرتبط بمستخدم داخل النظام.');
        }

        if ($employee->invoices()->exists()) {
            return redirect()->route('employees.index')->with('error', 'لا يمكن حذف موظف مرتبط بعمليات بيع محفوظة.');
        }

        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'تم حذف الموظف بنجاح.');
    }

    public function branches(Request $request): View
    {
        $company = $this->company($request);
        $branches = Branch::query()
            ->withCount(['employees', 'invoices'])
            ->where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('branches', compact('company', 'branches'));
    }

    public function storeBranch(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateBranchData($request, $company->id);
        $branch = Branch::query()->create($this->branchPayload($validated, $company));

        $this->syncDefaultBranchFlag($branch, (bool) ($validated['is_default'] ?? false));

        return redirect()->route('branches.index')->with('success', 'تمت إضافة الفرع بنجاح.');
    }

    public function updateBranch(Request $request, Branch $branch): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $branch->company_id !== (int) $company->id, 404);

        $validated = $this->validateBranchData($request, $company->id, $branch);
        $branch->update($this->branchPayload($validated, $company, $branch));
        $this->syncDefaultBranchFlag($branch, (bool) ($validated['is_default'] ?? false));

        return redirect()->route('branches.index')->with('success', 'تم تحديث الفرع بنجاح.');
    }

    public function destroyBranch(Request $request, Branch $branch): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $branch->company_id !== (int) $company->id, 404);

        if (Branch::query()->where('company_id', $company->id)->count() <= 1) {
            return redirect()->route('branches.index')->with('error', 'يجب أن يبقى فرع واحد على الأقل داخل الشركة.');
        }

        if ($branch->employees()->exists() || $branch->invoices()->exists()) {
            return redirect()->route('branches.index')->with('error', 'لا يمكن حذف فرع مرتبط بموظفين أو عمليات بيع.');
        }

        $branch->delete();

        return redirect()->route('branches.index')->with('success', 'تم حذف الفرع بنجاح.');
    }

    public function salesChannels(Request $request): View
    {
        $company = $this->company($request);
        $salesChannels = SalesChannel::query()
            ->withCount('invoices')
            ->where('company_id', $company->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return view('sales_channels', compact('company', 'salesChannels'));
    }

    public function storeSalesChannel(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateSalesChannelData($request, $company->id);
        $salesChannel = SalesChannel::query()->create($this->salesChannelPayload($validated, $company));

        $this->syncDefaultSalesChannelFlag($salesChannel, (bool) ($validated['is_default'] ?? false));

        return redirect()->route('sales_channels.index')->with('success', 'تمت إضافة قناة البيع بنجاح.');
    }

    public function updateSalesChannel(Request $request, SalesChannel $salesChannel): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $salesChannel->company_id !== (int) $company->id, 404);

        $validated = $this->validateSalesChannelData($request, $company->id, $salesChannel);
        $salesChannel->update($this->salesChannelPayload($validated, $company, $salesChannel));
        $this->syncDefaultSalesChannelFlag($salesChannel, (bool) ($validated['is_default'] ?? false));

        return redirect()->route('sales_channels.index')->with('success', 'تم تحديث قناة البيع بنجاح.');
    }

    public function destroySalesChannel(Request $request, SalesChannel $salesChannel): RedirectResponse
    {
        $company = $this->company($request);
        abort_if((int) $salesChannel->company_id !== (int) $company->id, 404);

        if (SalesChannel::query()->where('company_id', $company->id)->count() <= 1) {
            return redirect()->route('sales_channels.index')->with('error', 'يجب أن تبقى قناة بيع واحدة على الأقل داخل الشركة.');
        }

        if ($salesChannel->invoices()->exists()) {
            return redirect()->route('sales_channels.index')->with('error', 'لا يمكن حذف قناة بيع مرتبطة بعمليات بيع محفوظة.');
        }

        $salesChannel->delete();

        return redirect()->route('sales_channels.index')->with('success', 'تم حذف قناة البيع بنجاح.');
    }

    public function settings(Request $request): View
    {
        $company = $this->company($request);
        $accounts = Account::where('company_id', $company->id)->orderBy('code')->get();
        $taxSettings = TaxSetting::where('company_id', $company->id)->orderByDesc('is_default')->get();
        $countries = $this->countryConfigs();
        $companyCountry = $this->countryConfigForCompany($company);
        $companyCities = collect($companyCountry['cities'] ?? []);

        return view('settings', compact('company', 'accounts', 'taxSettings', 'countries', 'companyCountry', 'companyCities'));
    }

    public function updateCompanySettings(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateCompanySettingsData($request);

        $countryConfig = $this->countryConfigs()->get($validated['country_code'], $this->countryConfigs()->get('SA'));

        $updateData = [
            'name' => $validated['name'],
            'tax_number' => $validated['tax_number'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'country_code' => $validated['country_code'],
            'currency' => $validated['currency'] ?? ($countryConfig['currency'] ?? $company->currency),
        ];

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($company->logo_url && Storage::disk('public')->exists($company->logo_url)) {
                Storage::disk('public')->delete($company->logo_url);
            }

            $logoPath = $request->file('logo')->store('company-logos', 'public');
            $updateData['logo_url'] = $logoPath;
        }

        // Handle logo removal
        if ($request->boolean('remove_logo')) {
            if ($company->logo_url && Storage::disk('public')->exists($company->logo_url)) {
                Storage::disk('public')->delete($company->logo_url);
            }
            $updateData['logo_url'] = null;
        }

        $company->update($updateData);

        return redirect()->route('settings')->with('status', 'تم تحديث معلومات الشركة بنجاح.');
    }

    public function updateTaxSettings(Request $request): RedirectResponse
    {
        $company = $this->company($request);
        $validated = $this->validateTaxSettingsData($request, $company->id);

        $outputSetting = TaxSetting::where('company_id', $company->id)
            ->whereIn('tax_type', ['output_vat', 'vat'])
            ->orderByDesc('is_default')
            ->first();

        if ($outputSetting) {
            if ($outputSetting->tax_type === 'vat') {
                $outputSetting->tax_type = 'output_vat';
            }

            $outputSetting->fill([
                'tax_name' => 'VAT',
                'tax_name_ar' => 'ضريبة المخرجات',
                'rate' => $validated['vat_rate'],
                'is_default' => true,
                'account_id' => $validated['output_tax_account_id'],
            ])->save();
        } else {
            TaxSetting::create([
                'company_id' => $company->id,
                'tax_name' => 'VAT',
                'tax_name_ar' => 'ضريبة المخرجات',
                'tax_type' => 'output_vat',
                'rate' => $validated['vat_rate'],
                'is_default' => true,
                'account_id' => $validated['output_tax_account_id'],
            ]);
        }

        TaxSetting::updateOrCreate(
            [
                'company_id' => $company->id,
                'tax_type' => 'input_vat',
            ],
            [
                'tax_name' => 'Input VAT',
                'tax_name_ar' => 'ضريبة المدخلات',
                'rate' => $validated['vat_rate'],
                'is_default' => false,
                'account_id' => $validated['input_tax_account_id'],
            ],
        );

        return redirect()->route('settings', ['#tax-settings'])->with('status', 'تم تحديث ربط الحسابات الضريبية بنجاح.');
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
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $search = trim((string) $filters['search']);

                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('reference', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('expense_number', 'like', '%' . $search . '%');
                });
            })
            ->when(!empty($filters['date_from']), fn($query) => $query->whereDate('expense_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn($query) => $query->whereDate('expense_date', '<=', $filters['date_to']))
            ->when(!empty($filters['expense_account_id']), fn($query) => $query->where('expense_account_id', $filters['expense_account_id']))
            ->when(!empty($filters['expense_id']), fn($query) => $query->where('id', $filters['expense_id']));
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
            'tax_summary' => $this->taxSummaryReport($company, $dateFrom, $dateTo),
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
                'values' => $rows->pluck('value')->map(fn($value) => round((float) $value, 2))->values(),
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
            ->when($selectedAccountId, fn($query) => $query->where('accounts.id', $selectedAccountId))
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
                'values' => $rows->pluck('value')->take(8)->map(fn($value) => round((float) $value, 2))->values(),
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
            ->when($selectedProductId, fn($query) => $query->where('products.id', $selectedProductId))
            ->groupBy('products.id', 'products.name', 'invoice_items.description')
            ->selectRaw('products.id, COALESCE(products.name, invoice_items.description) as label, SUM(invoice_items.quantity) as quantity_sold, SUM(invoice_items.total) as total_sales')
            ->orderByDesc('total_sales')
            ->get()
            ->map(fn($row) => [
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
                'values' => $rows->pluck('value')->take(8)->map(fn($value) => round((float) $value, 2))->values(),
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
            ->when($selectedExpenseId, fn($query) => $query->where('id', $selectedExpenseId))
            ->orderByDesc('expense_date')
            ->get()
            ->map(fn(Expense $expense) => [
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
                'values' => $rows->pluck('value')->take(8)->map(fn($value) => round((float) $value, 2))->values(),
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
            ->when($selectedCustomerId, fn($query) => $query->where('customers.id', $selectedCustomerId))
            ->groupBy('customers.id', 'customers.name')
            ->selectRaw('customers.name as label, SUM(invoices.balance_due) as balance_due, COUNT(invoices.id) as invoice_count')
            ->orderByDesc('balance_due')
            ->get()
            ->map(fn($row) => [
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
                'values' => $rows->pluck('value')->take(8)->map(fn($value) => round((float) $value, 2))->values(),
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
            ->when($selectedSupplierId, fn($query) => $query->where('suppliers.id', $selectedSupplierId))
            ->groupBy('suppliers.id', 'suppliers.name')
            ->selectRaw('suppliers.name as label, SUM(purchases.subtotal) as subtotal_amount, SUM(purchases.tax_amount) as tax_amount, SUM(purchases.total) as total_amount, SUM(purchases.balance_due) as balance_due, COUNT(purchases.id) as purchase_count')
            ->orderByDesc('balance_due')
            ->get()
            ->map(fn($row) => [
                'label' => $row->label,
                'value' => (float) $row->balance_due,
                'meta' => sprintf(
                    'عدد المشتريات المفتوحة: %s | قبل الضريبة: %s | الضريبة: %s | الإجمالي: %s',
                    $row->purchase_count,
                    number_format((float) $row->subtotal_amount, 2),
                    number_format((float) $row->tax_amount, 2),
                    number_format((float) $row->total_amount, 2),
                ),
            ]);

        $purchaseTotals = Purchase::query()
            ->where('company_id', $company->id)
            ->whereBetween('purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->where('balance_due', '>', 0)
            ->when($selectedSupplierId, fn($query) => $query->where('supplier_id', $selectedSupplierId))
            ->selectRaw('COUNT(id) as purchase_count, SUM(subtotal) as subtotal_amount, SUM(tax_amount) as tax_amount, SUM(total) as total_amount, SUM(balance_due) as balance_due')
            ->first();

        return [
            'title' => $selectedSupplierId ? 'ذمم مورد محدد' : 'الذمم الدائنة',
            'rows' => $rows,
            'chart' => [
                'type' => 'bar',
                'labels' => $rows->pluck('label')->take(8)->values(),
                'values' => $rows->pluck('value')->take(8)->map(fn($value) => round((float) $value, 2))->values(),
            ],
            'highlights' => [
                ['label' => 'عدد الموردين', 'value' => $rows->count()],
                ['label' => 'إجمالي قبل الضريبة', 'value' => (float) ($purchaseTotals->subtotal_amount ?? 0)],
                ['label' => 'إجمالي الضريبة', 'value' => (float) ($purchaseTotals->tax_amount ?? 0)],
                ['label' => 'إجمالي الذمم', 'value' => (float) ($purchaseTotals->balance_due ?? 0)],
            ],
            'empty_message' => 'لا توجد ذمم دائنة ضمن المعايير المختارة.',
        ];
    }

    private function taxSummaryReport(Company $company, Carbon $dateFrom, Carbon $dateTo): array
    {
        $outputVat = (float) Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partial', 'paid'])
            ->whereBetween('invoice_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('tax_amount');

        $purchaseInputVat = (float) Purchase::where('company_id', $company->id)
            ->whereIn('status', ['approved', 'partial', 'paid'])
            ->whereBetween('purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('tax_amount');

        $expenseInputVat = (float) Expense::where('company_id', $company->id)
            ->whereBetween('expense_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->sum('tax_amount');

        $totalInputVat = $purchaseInputVat + $expenseInputVat;
        $netVat = $outputVat - $totalInputVat;

        $rows = collect([
            ['label' => 'ضريبة المخرجات من الفواتير', 'value' => $outputVat, 'meta' => 'إجمالي ضريبة المبيعات المعتمدة خلال الفترة'],
            ['label' => 'ضريبة المدخلات من المشتريات', 'value' => $purchaseInputVat, 'meta' => 'إجمالي ضريبة طلبات الشراء المعتمدة خلال الفترة'],
            ['label' => 'ضريبة المدخلات من المصروفات', 'value' => $expenseInputVat, 'meta' => 'إجمالي ضريبة المصروفات خلال الفترة'],
            ['label' => 'صافي الضريبة المستحقة', 'value' => $netVat, 'meta' => 'ضريبة المخرجات - إجمالي ضريبة المدخلات'],
        ]);

        return [
            'title' => 'تقرير الضرائب',
            'rows' => $rows,
            'chart' => [
                'type' => 'bar',
                'labels' => $rows->pluck('label')->values(),
                'values' => $rows->pluck('value')->map(fn($value) => round((float) $value, 2))->values(),
            ],
            'highlights' => [
                ['label' => 'ضريبة المخرجات', 'value' => $outputVat],
                ['label' => 'ضريبة المدخلات', 'value' => $totalInputVat],
                ['label' => 'صافي الضريبة', 'value' => $netVat],
            ],
            'empty_message' => 'لا توجد بيانات ضريبية للفترة المختارة.',
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
        $attachmentRules = [
            'nullable',
            'file',
            'mimes:jpg,jpeg,png,pdf',
            'max:8192',
        ];

        $validated = $request->validate([
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
            ],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
            ],
            'employee_id' => [
                'nullable',
                Rule::exists('employees', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
            ],
            'sales_channel_id' => [
                'nullable',
                Rule::exists('sales_channels', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
            ],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'status' => ['required', Rule::in(['draft', 'sent'])],
            'payment_status' => ['nullable', Rule::in(['deferred', 'partial', 'full'])],
            'payment_account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn($query) => $query
                    ->where('company_id', $companyId)
                    ->where('allows_direct_transactions', true)
                    ->where('is_active', true)),
            ],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'attachment' => $attachmentRules,
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'item_product_id' => ['required', 'array', 'min:1'],
            'item_product_id.*' => [
                'nullable',
                Rule::exists('products', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
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

        $validated['sales_channel_id'] = $validated['sales_channel_id'] ?? $this->defaultSalesChannelId($companyId);

        $missingDefaults = [];

        if (!$validated['sales_channel_id']) {
            $missingDefaults['sales_channel_id'] = 'لا توجد قناة بيع متاحة لحفظ عملية البيع. أضف قناة بيع أولاً.';
        }

        if ($missingDefaults !== []) {
            throw ValidationException::withMessages($missingDefaults);
        }

        $totals = $this->calculateInvoiceTotals($validated);
        $enteredPaidAmount = round((float) ($validated['paid_amount'] ?? 0), 2);
        $requestedPaymentStatus = $validated['payment_status'] ?? 'deferred';
        $validated['payment_status'] = $requestedPaymentStatus;

        if ($requestedPaymentStatus === 'deferred') {
            $validated['paid_amount'] = 0;
            $validated['payment_account_id'] = null;
        }

        if ($requestedPaymentStatus === 'full') {
            $validated['paid_amount'] = $totals['total'];
        } elseif ($requestedPaymentStatus === 'partial') {
            if ($enteredPaidAmount <= 0) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'أدخل مبلغًا مدفوعًا أكبر من صفر عند اختيار الدفع الجزئي.',
                ]);
            }

            if ($enteredPaidAmount >= (float) $totals['total']) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'مبلغ الدفع الجزئي يجب أن يكون أقل من إجمالي الفاتورة.',
                ]);
            }

            $validated['paid_amount'] = $enteredPaidAmount;
        }

        if (in_array($requestedPaymentStatus, ['partial', 'full'], true) && empty($validated['payment_account_id'])) {
            throw ValidationException::withMessages([
                'payment_account_id' => 'اختر حساب التحصيل من شجرة الحسابات عند تسجيل مبلغ محصل.',
            ]);
        }

        return $validated;
    }

    private function validateJournalEntryData(Request $request, int $companyId): array
    {
        return $request->validate([
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'line_account' => ['required', 'array', 'min:2'],
            'line_account.*' => ['nullable', Rule::exists('accounts', 'id')->where(fn($query) => $query->where('company_id', $companyId))],
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
            ->where(fn($query) => $query->where('company_id', $companyId));

        if ($product) {
            $uniqueCodeRule = $uniqueCodeRule->ignore($product->id);
        }

        return $request->validate([
            'product_modal' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'supplier_id' => [
                'nullable',
                Rule::exists('suppliers', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
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
            ->where(fn($query) => $query->where('company_id', $companyId));

        $uniqueEmailRule = Rule::unique('suppliers', 'email')
            ->where(fn($query) => $query->where('company_id', $companyId));

        if ($supplier) {
            $uniqueCodeRule = $uniqueCodeRule->ignore($supplier->id);
            $uniqueEmailRule = $uniqueEmailRule->ignore($supplier->id);
        }

        $cityRule = ['nullable', 'string', 'max:100'];
        $companyCountryCode = Company::query()->whereKey($companyId)->value('country_code');
        $availableCities = $this->cityOptionsForCountryCode($companyCountryCode);

        if ($availableCities !== []) {
            $cityRule = ['nullable', Rule::in($availableCities)];
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
            'city' => $cityRule,
            'tax_number' => ['nullable', 'string', 'max:50'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'redirect_to' => ['nullable', 'string'],
        ]);
    }

    private function validateCustomerData(Request $request, int $companyId, ?Customer $customer = null): array
    {
        $uniqueCodeRule = Rule::unique('customers', 'code')
            ->where(fn($query) => $query->where('company_id', $companyId));

        $uniqueEmailRule = Rule::unique('customers', 'email')
            ->where(fn($query) => $query->where('company_id', $companyId));

        if ($customer) {
            $uniqueCodeRule = $uniqueCodeRule->ignore($customer->id);
            $uniqueEmailRule = $uniqueEmailRule->ignore($customer->id);
        }

        $cityRule = ['nullable', 'string', 'max:100'];
        $companyCountryCode = Company::query()->whereKey($companyId)->value('country_code');
        $availableCities = $this->cityOptionsForCountryCode($companyCountryCode);

        if ($availableCities !== []) {
            $cityRule = ['nullable', Rule::in($availableCities)];
        }

        return $request->validate([
            'customer_modal' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:200'],
            'name_ar' => ['nullable', 'string', 'max:200'],
            'code' => ['nullable', 'string', 'max:20', $uniqueCodeRule],
            'email' => ['nullable', 'email', 'max:120', $uniqueEmailRule],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'city' => $cityRule,
            'tax_number' => ['nullable', 'string', 'max:50'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function validateBranchData(Request $request, int $companyId, ?Branch $branch = null): array
    {
        $uniqueCodeRule = Rule::unique('branches', 'code')
            ->where(fn($query) => $query->where('company_id', $companyId));

        if ($branch) {
            $uniqueCodeRule = $uniqueCodeRule->ignore($branch->id);
        }

        return $request->validate([
            'branch_modal' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', $uniqueCodeRule],
            'city' => ['nullable', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function validateSalesChannelData(Request $request, int $companyId, ?SalesChannel $salesChannel = null): array
    {
        $uniqueCodeRule = Rule::unique('sales_channels', 'code')
            ->where(fn($query) => $query->where('company_id', $companyId));

        if ($salesChannel) {
            $uniqueCodeRule = $uniqueCodeRule->ignore($salesChannel->id);
        }

        return $request->validate([
            'channel_modal' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40', $uniqueCodeRule],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    private function validateEmployeeData(Request $request, int $companyId, ?Employee $employee = null): array
    {
        $uniqueEmailRule = Rule::unique('employees', 'email')
            ->where(fn($query) => $query->where('company_id', $companyId));

        if ($employee) {
            $uniqueEmailRule = $uniqueEmailRule->ignore($employee->id);
        }

        return $request->validate([
            'employee_modal' => ['nullable', 'string'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255', $uniqueEmailRule],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'position' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'on_leave', 'terminated'])],
            'employment_type' => ['nullable', Rule::in(['full_time', 'part_time', 'contract', 'temporary'])],
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
            ],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function validateCompanySettingsData(Request $request): array
    {
        $countries = $this->countryConfigs();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'country_code' => ['required', Rule::in($countries->keys()->all())],
            'city' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'max:10'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ]);

        $allowedCities = $this->cityOptionsForCountryCode($validated['country_code']);

        if ($allowedCities !== [] && !empty($validated['city']) && !in_array($validated['city'], $allowedCities, true)) {
            throw ValidationException::withMessages([
                'city' => 'المدينة المحددة لا تتبع الدولة المختارة للشركة.',
            ]);
        }

        return $validated;
    }

    private function branchPayload(array $validated, Company $company, ?Branch $branch = null): array
    {
        $defaultBranch = !$branch && !Branch::query()->where('company_id', $company->id)->exists();

        return [
            'company_id' => $company->id,
            'name' => $validated['name'],
            'code' => mb_strtoupper(trim($validated['code'])),
            'city' => $validated['city'] ?? $company->city,
            'is_default' => (bool) ($validated['is_default'] ?? $defaultBranch),
        ];
    }

    private function salesChannelPayload(array $validated, Company $company, ?SalesChannel $salesChannel = null): array
    {
        $defaultChannel = !$salesChannel && !SalesChannel::query()->where('company_id', $company->id)->exists();

        return [
            'company_id' => $company->id,
            'name' => $validated['name'],
            'code' => mb_strtoupper(trim($validated['code'])),
            'is_default' => (bool) ($validated['is_default'] ?? $defaultChannel),
        ];
    }

    private function employeePayload(array $validated, Company $company, ?Employee $employee = null): array
    {
        return [
            'employee_number' => $employee?->employee_number ?? $this->nextEmployeeNumber($company->id),
            'company_id' => $company->id,
            'branch_id' => $validated['branch_id'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'hire_date' => $validated['hire_date'] ?? null,
            'termination_date' => $validated['termination_date'] ?? null,
            'position' => $validated['position'] ?? null,
            'department' => $validated['department'] ?? null,
            'salary' => $validated['salary'] ?? 0,
            'status' => $validated['status'],
            'employment_type' => $validated['employment_type'] ?? 'full_time',
            'notes' => $validated['notes'] ?? null,
        ];
    }

    private function nextEmployeeNumber(int $companyId): string
    {
        $count = Employee::query()->where('company_id', $companyId)->count() + 1;

        return 'EMP-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function syncDefaultBranchFlag(Branch $branch, bool $shouldBeDefault): void
    {
        if (!$shouldBeDefault) {
            if (!Branch::query()->where('company_id', $branch->company_id)->where('is_default', true)->exists()) {
                $branch->forceFill(['is_default' => true])->save();
            }

            return;
        }

        Branch::query()
            ->where('company_id', $branch->company_id)
            ->where('id', '!=', $branch->id)
            ->update(['is_default' => false]);

        if (!$branch->is_default) {
            $branch->forceFill(['is_default' => true])->save();
        }
    }

    private function syncDefaultSalesChannelFlag(SalesChannel $salesChannel, bool $shouldBeDefault): void
    {
        if (!$shouldBeDefault) {
            if (!SalesChannel::query()->where('company_id', $salesChannel->company_id)->where('is_default', true)->exists()) {
                $salesChannel->forceFill(['is_default' => true])->save();
            }

            return;
        }

        SalesChannel::query()
            ->where('company_id', $salesChannel->company_id)
            ->where('id', '!=', $salesChannel->id)
            ->update(['is_default' => false]);

        if (!$salesChannel->is_default) {
            $salesChannel->forceFill(['is_default' => true])->save();
        }
    }

    private function validateTaxSettingsData(Request $request, int $companyId): array
    {
        return $request->validate([
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'output_tax_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn($query) => $query->where('company_id', $companyId)->where('account_type', 'liability')),
            ],
            'input_tax_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
            ],
        ]);
    }

    private function validateExpenseData(Request $request, int $companyId): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'expense_date' => ['required', 'date'],
            'expense_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn($query) => $query->where('company_id', $companyId)->where('account_type', 'expense')),
            ],
            'payment_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where(fn($query) => $query
                    ->where('company_id', $companyId)
                    ->where('allows_direct_transactions', true)
                    ->where('is_active', true)),
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
            ->where(fn($query) => $query->where('company_id', $companyId));

        return $request->validate([
            'code' => ['required', 'string', 'max:20', $uniqueCodeRule],
            'name' => ['required', 'string', 'max:200'],
            'name_ar' => ['nullable', 'string', 'max:200'],
            'account_type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense', 'cogs'])],
            'parent_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
            ],
            'description' => ['nullable', 'string'],
            'allows_direct_transactions' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function suggestedParentIds(Collection $accounts): array
    {
        $rootCodes = [
            'asset' => '1',
            'liability' => '2',
            'equity' => '3',
            'revenue' => '4',
            'expense' => '5',
            'cogs' => '5',
        ];

        $accountsByCode = $accounts->keyBy('code');
        $suggestions = [];

        foreach ($rootCodes as $type => $code) {
            $suggestions[$type] = $code ? $accountsByCode->get($code)?->id : null;
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

                $matchesSearch = collect($haystacks)->contains(fn(string $value) => str_contains($value, $search));

                if (!$matchesSearch) {
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

    private function buildAccountTree(Collection $accounts): array
    {
        return $this->nestAccounts($accounts, null);
    }

    private function chartAccountFilters(Request $request): array
    {
        return [
            'search' => trim($request->string('search')->toString()),
            'account_type' => $request->string('account_type')->toString(),
            'min_balance' => $request->string('min_balance')->toString(),
            'max_balance' => $request->string('max_balance')->toString(),
        ];
    }

    private function visibleChartAccounts(Collection $accounts, bool $includeDynamicAccounts): Collection
    {
        if ($includeDynamicAccounts) {
            return $accounts->values();
        }

        return $accounts
            ->reject(fn(Account $account) => $this->isDynamicChartAccount($account))
            ->values();
    }

    private function isDynamicChartAccount(Account $account): bool
    {
        return preg_match('/^(1103-C\d+|2101-S\d+|4101-P\d+|1106-P\d+|5101-P\d+)$/', $account->code) === 1;
    }

    private function sortDirection(Request $request, string $parameter = 'sort_direction', string $default = 'desc'): string
    {
        $value = strtolower($request->string($parameter)->toString());

        return in_array($value, ['asc', 'desc'], true) ? $value : $default;
    }

    private function accountAncestors(Account $account, Collection $allAccounts): Collection
    {
        $ancestors = collect();
        $accountsById = $allAccounts->keyBy('id');
        $currentParentId = $account->parent_id;
        $visited = [];

        while ($currentParentId && !isset($visited[$currentParentId])) {
            $visited[$currentParentId] = true;
            $parent = $accountsById->get($currentParentId);

            if (!$parent) {
                break;
            }

            $ancestors->prepend($parent);
            $currentParentId = $parent->parent_id;
        }

        return $ancestors;
    }

    private function buildFilteredAccountTree(Collection $allAccounts, Collection $matchingAccounts): array
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

    private function nestAccounts(Collection $accounts, ?int $parentId): array
    {
        // استخدم rolled_up_balance المحسوب مسبقاً إذا كان موجوداً (من جميع الحسابات)
        // وإلا احسبه من الحسابات الحالية
        $preCalculatedBalances = [];
        foreach ($accounts as $account) {
            if (isset($account->rolled_up_balance)) {
                $preCalculatedBalances[$account->id] = (float) $account->rolled_up_balance;
            }
        }

        // Pre-calculate all rolled_up_balances in one pass (bottom-up approach)
        $rolledUpBalances = !empty($preCalculatedBalances)
            ? $preCalculatedBalances
            : $this->calculateAllRolledUpBalances($accounts);

        // Build flat array with just essential data - NO CHILDREN to avoid memory issues
        $result = [];
        foreach ($accounts as $account) {
            // Only include accounts with matching parent_id
            if ($account->parent_id === $parentId) {
                $result[] = [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'name_ar' => $account->name_ar,
                    'account_type' => $account->account_type,
                    'display_account_type' => $account->display_account_type,
                    'description' => $account->description,
                    'balance' => (float) $account->balance,
                    'parent_id' => $account->parent_id,
                    'is_system' => $account->is_system,
                    'allows_direct_transactions' => $account->allows_direct_transactions,
                    'rolled_up_balance' => $rolledUpBalances[$account->id] ?? (float) $account->balance,
                ];
            }
        }

        // Sort by code
        usort($result, fn($a, $b) => strcmp($a['code'], $b['code']));

        // Build lookup for all accounts (for finding children in view)
        $allAccountsById = [];
        foreach ($accounts as $account) {
            $allAccountsById[$account->id] = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'name_ar' => $account->name_ar,
                'account_type' => $account->account_type,
                'display_account_type' => $account->display_account_type,
                'description' => $account->description,
                'balance' => (float) $account->balance,
                'parent_id' => $account->parent_id,
                'is_system' => $account->is_system,
                'allows_direct_transactions' => $account->allows_direct_transactions,
                'rolled_up_balance' => $rolledUpBalances[$account->id] ?? (float) $account->balance,
            ];
        }

        // Store in view shared data instead of session
        view()->share('chartAccountsLookup', $allAccountsById);

        return $result;
    }

    private function calculateAllRolledUpBalances(Collection $accounts): array
    {
        $accountsById = $accounts->keyBy('id');
        $rolledUpBalances = [];

        // Initialize with own balance for each account
        foreach ($accounts as $account) {
            $rolledUpBalances[$account->id] = (float) $account->balance;
        }

        // Build parent-child relationships
        $childrenByParent = [];
        foreach ($accounts as $account) {
            if ($account->parent_id) {
                if (!isset($childrenByParent[$account->parent_id])) {
                    $childrenByParent[$account->parent_id] = [];
                }
                $childrenByParent[$account->parent_id][] = $account->id;
            }
        }

        // Calculate rolled-up balances bottom-up (from leaves to root)
        // Process accounts in order from deepest to shallowest
        $accountsByDepth = [];
        foreach ($accounts as $account) {
            $depth = $this->calculateDepth($account->id, $accountsById);
            if (!isset($accountsByDepth[$depth])) {
                $accountsByDepth[$depth] = [];
            }
            $accountsByDepth[$depth][] = $account->id;
        }

        // Process from deepest to shallowest
        krsort($accountsByDepth);
        foreach ($accountsByDepth as $depth => $accountIds) {
            foreach ($accountIds as $accountId) {
                // Add this account's rolled-up balance to its parent
                $account = $accountsById->get($accountId);
                if ($account && $account->parent_id && isset($rolledUpBalances[$accountId])) {
                    if (!isset($rolledUpBalances[$account->parent_id])) {
                        $rolledUpBalances[$account->parent_id] = 0.0;
                    }
                    $rolledUpBalances[$account->parent_id] += $rolledUpBalances[$accountId];
                }
            }
        }

        return $rolledUpBalances;
    }

    private function calculateDepth(int $accountId, Collection $accountsById): int
    {
        $depth = 0;
        $current = $accountsById->get($accountId);
        $visited = [];

        while ($current && !isset($visited[$current->id])) {
            $visited[$current->id] = true;
            if ($current->parent_id) {
                $current = $accountsById->get($current->parent_id);
                $depth++;
            } else {
                break;
            }
        }

        return $depth;
    }

    private function chartAccountRows(Collection $accounts): Collection
    {
        $accountsById = $accounts->keyBy('id');

        return $accounts
            ->sortBy('code')
            ->values()
            ->map(function (Account $account) use ($accountsById) {
                $parent = $account->parent_id ? $accountsById->get($account->parent_id) : null;

                return [
                    'code' => $account->code,
                    'name' => $account->name_ar ?: $account->name,
                    'display_account_type' => $account->display_account_type ?: $account->account_type,
                    'description' => $account->description ?: '',
                    'parent_label' => $parent ? ($parent->code . ' - ' . ($parent->name_ar ?: $parent->name)) : '',
                    'allows_direct_transactions' => (bool) $account->allows_direct_transactions,
                ];
            });
    }

    private function suggestedParentIdForType(int $companyId, string $type): ?int
    {
        $rootCodes = [
            'asset' => '1',
            'liability' => '2',
            'equity' => '3',
            'revenue' => '4',
            'expense' => '5',
            'cogs' => '5',
        ];

        $code = $rootCodes[$type] ?? null;

        if (!$code) {
            return null;
        }

        return Account::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->value('id');
    }

    private function supplierPayload(array $validated, Company $company, ?Supplier $supplier = null): array
    {
        return [
            'company_id' => $company->id,
            'code' => $validated['code'] ?? null,
            'name' => $validated['name'],
            'name_ar' => $validated['name_ar'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $this->countryLabel($company->country_code),
            'tax_number' => $validated['tax_number'] ?? null,
            'account_id' => $supplier?->account_id,
            'credit_limit' => $validated['credit_limit'] ?? 0,
            'balance' => $supplier?->balance ?? 0,
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    private function customerPayload(array $validated, Company $company, ?Customer $customer = null): array
    {
        return [
            'company_id' => $company->id,
            'code' => $validated['code'] ?? null,
            'name' => $validated['name'],
            'name_ar' => $validated['name_ar'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $this->countryLabel($company->country_code),
            'tax_number' => $validated['tax_number'] ?? null,
            'account_id' => $customer?->account_id,
            'credit_limit' => $validated['credit_limit'] ?? 0,
            'balance' => $customer?->balance ?? 0,
            'is_active' => (bool) $validated['is_active'],
        ];
    }

    private function productPayload(array $validated, int $companyId): array
    {
        return [
            'company_id' => $companyId,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'category_id' => $this->categoryIdForProductType($companyId, (string) $validated['type']),
            'revenue_account_id' => $validated['revenue_account_id'] ?? null,
            'inventory_account_id' => $validated['inventory_account_id'] ?? null,
            'cogs_account_id' => $validated['cogs_account_id'] ?? null,
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

    private function validatePurchaseData(Request $request, int $companyId, ?Purchase $purchase = null): array
    {
        $attachmentRules = [
            $purchase ? 'nullable' : 'required',
            'file',
            'mimes:jpg,jpeg,png,pdf',
            'max:8192',
        ];

        $validated = $request->validate([
            'purchase_modal' => ['nullable', 'string'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
            ],
            'purchase_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'status' => ['nullable', Rule::in(['draft', 'pending', 'approved'])],
            'payment_status' => ['required', Rule::in(['pending', 'partial', 'paid'])],
            'payment_account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')->where(fn($query) => $query
                    ->where('company_id', $companyId)
                    ->where('allows_direct_transactions', true)
                    ->where('is_active', true)),
            ],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'notes' => ['nullable', 'string'],
            'attachment' => $attachmentRules,
            'item_product_id' => ['required', 'array', 'min:1'],
            'item_product_id.*' => [
                'nullable',
                Rule::exists('products', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
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

        $totals = $this->calculatePurchaseTotals($validated);
        $enteredPaidAmount = round((float) ($validated['paid_amount'] ?? 0), 2);
        $requestedPaymentStatus = $validated['payment_status'] ?? 'pending';
        $validated['payment_status'] = $requestedPaymentStatus;

        if ($requestedPaymentStatus === 'pending') {
            $validated['payment_date'] = null;
            $validated['paid_amount'] = 0;
            $validated['payment_account_id'] = null;
        }

        if ($requestedPaymentStatus === 'paid') {
            $validated['paid_amount'] = $totals['total'];
        } elseif ($requestedPaymentStatus === 'partial') {
            if ($enteredPaidAmount <= 0) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'أدخل مبلغًا مدفوعًا أكبر من صفر عند اختيار الدفع الجزئي.',
                ]);
            }

            if ($enteredPaidAmount >= (float) $totals['total']) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'مبلغ الدفع الجزئي يجب أن يكون أقل من إجمالي طلب الشراء.',
                ]);
            }

            $validated['paid_amount'] = $enteredPaidAmount;
        }

        if (in_array($requestedPaymentStatus, ['partial', 'paid'], true) && empty($validated['payment_account_id'])) {
            throw ValidationException::withMessages([
                'payment_account_id' => 'اختر حساب السداد من شجرة الحسابات عند تسجيل مبلغ مدفوع.',
            ]);
        }

        return $validated;
    }

    private function directPaymentAccounts(int $companyId): Collection
    {
        return Account::query()
            ->where('company_id', $companyId)
            ->where('allows_direct_transactions', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    private function defaultBranchId(int $companyId): ?int
    {
        $branchId = Branch::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');

        if ($branchId) {
            return (int) $branchId;
        }

        return (int) Branch::query()->create([
            'company_id' => $companyId,
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN',
            'city' => Company::query()->whereKey($companyId)->value('city'),
            'is_default' => true,
        ])->id;
    }

    private function defaultSalesChannelId(int $companyId): ?int
    {
        $channelId = SalesChannel::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');

        if ($channelId) {
            return (int) $channelId;
        }

        return (int) SalesChannel::query()->create([
            'company_id' => $companyId,
            'name' => 'المبيعات المباشرة',
            'code' => 'DIRECT',
            'is_default' => true,
        ])->id;
    }

    private function categoryIdForProductType(int $companyId, string $type): ?int
    {
        $normalizedType = trim($type);

        $categoryName = match (strtolower($normalizedType)) {
            'product' => 'منتجات',
            'service' => 'خدمات',
            default => $normalizedType !== '' ? $normalizedType : 'غير مصنف',
        };

        return Category::query()
            ->where('company_id', $companyId)
            ->where('name', $categoryName)
            ->value('id')
            ?: Category::query()
                ->where('company_id', $companyId)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->value('id');
    }

    private function resolveInvoiceItemCategoryId(int $companyId, $productId): ?int
    {
        $resolvedProductId = (int) ($productId ?: 0);

        if ($resolvedProductId > 0) {
            $categoryId = Product::query()
                ->where('company_id', $companyId)
                ->where('id', $resolvedProductId)
                ->value('category_id');

            if ($categoryId) {
                return (int) $categoryId;
            }
        }

        return Category::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');
    }

    private function handlePurchaseAttachmentUpload(Request $request, ?Purchase $purchase = null): ?string
    {
        if (!$request->hasFile('attachment')) {
            return $purchase?->attachment_path;
        }

        $file = $request->file('attachment');

        if (!$file || !$file->isValid()) {
            return $purchase?->attachment_path;
        }

        if ($purchase && $purchase->attachment_path) {
            Storage::disk('public')->delete($purchase->attachment_path);
        }

        return $file->store('purchase_attachments', 'public');
    }

    private function handleInvoiceAttachmentUpload(Request $request, ?Invoice $invoice = null): ?string
    {
        if (!$request->hasFile('attachment')) {
            return $invoice?->attachment_path;
        }

        $file = $request->file('attachment');

        if (!$file || !$file->isValid()) {
            return $invoice?->attachment_path;
        }

        if ($invoice && $invoice->attachment_path) {
            Storage::disk('public')->delete($invoice->attachment_path);
        }

        return $file->store('invoice_attachments', 'public');
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

    private function ensureInvoiceCanBePosted(array $validated, array $totals): void
    {
        if (($validated['status'] ?? 'sent') === 'draft') {
            return;
        }

        if ((float) ($totals['total'] ?? 0) > 0) {
            return;
        }

        $validator = Validator::make([], []);
        $validator->errors()->add('item_price', 'إجمالي الفاتورة يجب أن يكون أكبر من صفر قبل الحفظ كفاتورة مرسلة.');

        throw new ValidationException($validator);
    }

    private function shouldConsumeInvoiceStock(string $status): bool
    {
        return $status !== 'draft';
    }

    private function shouldReceivePurchaseStock(string $status): bool
    {
        return $status === 'approved';
    }

    private function invoicePaymentStatus(float $paidAmount, float $total): string
    {
        if ($paidAmount >= $total && $total > 0) {
            return 'full';
        }

        if ($paidAmount > 0) {
            return 'partial';
        }

        return 'deferred';
    }

    private function resolveInvoicePaymentData(array $validated, array $totals): array
    {
        $paidAmount = round((float) ($validated['paid_amount'] ?? 0), 2);
        $balanceDue = round(max((float) $totals['total'] - $paidAmount, 0), 2);

        return [
            'paid_amount' => $paidAmount,
            'balance_due' => $balanceDue,
            'payment_status' => $this->invoicePaymentStatus($paidAmount, (float) $totals['total']),
        ];
    }

    private function resolvePurchasePaymentData(array $validated, array $totals, ?float $forcedPaidAmount = null): array
    {
        $paidAmount = $forcedPaidAmount !== null
            ? round($forcedPaidAmount, 2)
            : round((float) ($validated['paid_amount'] ?? 0), 2);

        return [
            'paid_amount' => $paidAmount,
            'balance_due' => round(max((float) $totals['total'] - $paidAmount, 0), 2),
            'payment_status' => $this->purchasePaymentStatus($paidAmount, (float) $totals['total']),
        ];
    }

    private function invoiceStockRequirementsFromValidated(array $validated): array
    {
        $requirements = [];

        foreach ($validated['item_product_id'] as $index => $productId) {
            $productId = (int) ($productId ?: 0);

            if ($productId <= 0) {
                continue;
            }

            $quantity = round((float) ($validated['item_quantity'][$index] ?? 0), 2);

            if ($quantity <= 0) {
                continue;
            }

            if (!isset($requirements[$productId])) {
                $requirements[$productId] = [
                    'requested' => 0.0,
                    'line_indexes' => [],
                ];
            }

            $requirements[$productId]['requested'] += $quantity;
            $requirements[$productId]['line_indexes'][] = $index;
        }

        return $requirements;
    }

    private function purchaseStockRequirementsFromValidated(array $validated): array
    {
        $requirements = [];

        foreach ($validated['item_product_id'] as $index => $productId) {
            $productId = (int) ($productId ?: 0);

            if ($productId <= 0) {
                continue;
            }

            $quantity = round((float) ($validated['item_quantity'][$index] ?? 0), 2);

            if ($quantity <= 0) {
                continue;
            }

            if (!isset($requirements[$productId])) {
                $requirements[$productId] = [
                    'requested' => 0.0,
                    'line_indexes' => [],
                ];
            }

            $requirements[$productId]['requested'] += $quantity;
            $requirements[$productId]['line_indexes'][] = $index;
        }

        return $requirements;
    }

    private function purchaseStockRequirementsFromItems(iterable $items): array
    {
        $requirements = [];

        foreach ($items as $index => $item) {
            $productId = (int) ($item->product_id ?? 0);
            $quantity = round((float) ($item->quantity ?? 0), 2);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            if (!isset($requirements[$productId])) {
                $requirements[$productId] = [
                    'requested' => 0.0,
                    'line_indexes' => [],
                ];
            }

            $requirements[$productId]['requested'] += $quantity;
            $requirements[$productId]['line_indexes'][] = $index;
        }

        return $requirements;
    }

    private function invoiceStockRequirementsFromItems(Collection $items): array
    {
        $requirements = [];

        foreach ($items as $index => $item) {
            $productId = (int) ($item->product_id ?: 0);

            if ($productId <= 0) {
                continue;
            }

            $quantity = round((float) $item->quantity, 2);

            if ($quantity <= 0) {
                continue;
            }

            if (!isset($requirements[$productId])) {
                $requirements[$productId] = [
                    'requested' => 0.0,
                    'line_indexes' => [],
                ];
            }

            $requirements[$productId]['requested'] += $quantity;
            $requirements[$productId]['line_indexes'][] = (int) $index;
        }

        return $requirements;
    }

    private function ensureInvoiceStockAvailability(int $companyId, array $requirements): void
    {
        if ($requirements === []) {
            return;
        }

        $productIds = array_keys($requirements);
        $products = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $validator = Validator::make([], []);

        foreach ($requirements as $productId => $requirement) {
            /** @var Product|null $product */
            $product = $products->get($productId);

            if (!$product || $product->type === 'service') {
                continue;
            }

            $availableQuantity = round((float) $product->stock_quantity, 2);
            $requestedQuantity = round((float) $requirement['requested'], 2);

            if ($requestedQuantity <= $availableQuantity) {
                continue;
            }

            $message = $availableQuantity > 0
                ? sprintf(
                    'الكمية المتاحة للمنتج "%s" هي %s فقط، بينما إجمالي الكمية المطلوبة %s.',
                    $product->name,
                    number_format($availableQuantity, 2),
                    number_format($requestedQuantity, 2)
                )
                : sprintf('المنتج "%s" نفدت كميته الحالية ولا يمكن إضافته إلى الفاتورة.', $product->name);

            foreach ($requirement['line_indexes'] as $lineIndex) {
                $validator->errors()->add('item_quantity.' . $lineIndex, $message);
            }
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }
    }

    private function consumeInvoiceStock(int $companyId, array $requirements): void
    {
        if ($requirements === []) {
            return;
        }

        $productIds = array_keys($requirements);
        $products = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $validator = Validator::make([], []);

        foreach ($requirements as $productId => $requirement) {
            /** @var Product|null $product */
            $product = $products->get($productId);

            if (!$product || $product->type === 'service') {
                continue;
            }

            $availableQuantity = round((float) $product->stock_quantity, 2);
            $requestedQuantity = round((float) $requirement['requested'], 2);

            if ($requestedQuantity > $availableQuantity) {
                $message = $availableQuantity > 0
                    ? sprintf(
                        'الكمية المتاحة للمنتج "%s" هي %s فقط، بينما إجمالي الكمية المطلوبة %s.',
                        $product->name,
                        number_format($availableQuantity, 2),
                        number_format($requestedQuantity, 2)
                    )
                    : sprintf('المنتج "%s" نفدت كميته الحالية ولا يمكن إضافته إلى الفاتورة.', $product->name);

                foreach ($requirement['line_indexes'] as $lineIndex) {
                    $validator->errors()->add('item_quantity.' . $lineIndex, $message);
                }

                continue;
            }

            $product->update([
                'stock_quantity' => round($availableQuantity - $requestedQuantity, 2),
            ]);
        }

        if ($validator->errors()->isNotEmpty()) {
            throw new ValidationException($validator);
        }
    }

    private function restoreInvoiceStock(int $companyId, array $requirements): void
    {
        if ($requirements === []) {
            return;
        }

        $productIds = array_keys($requirements);
        $products = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($requirements as $productId => $requirement) {
            /** @var Product|null $product */
            $product = $products->get($productId);

            if (!$product || $product->type === 'service') {
                continue;
            }

            $product->update([
                'stock_quantity' => round((float) $product->stock_quantity + round((float) $requirement['requested'], 2), 2),
            ]);
        }
    }

    private function applyPurchaseStock(int $companyId, array $requirements): void
    {
        if ($requirements === []) {
            return;
        }

        $productIds = array_keys($requirements);
        $products = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($requirements as $productId => $requirement) {
            /** @var Product|null $product */
            $product = $products->get($productId);

            if (!$product || $product->type === 'service') {
                continue;
            }

            $product->update([
                'stock_quantity' => round((float) $product->stock_quantity + round((float) $requirement['requested'], 2), 2),
            ]);
        }
    }

    private function reversePurchaseStock(int $companyId, array $requirements): void
    {
        if ($requirements === []) {
            return;
        }

        $productIds = array_keys($requirements);
        $products = Product::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($requirements as $productId => $requirement) {
            /** @var Product|null $product */
            $product = $products->get($productId);

            if (!$product || $product->type === 'service') {
                continue;
            }

            $availableQuantity = round((float) $product->stock_quantity, 2);
            $requestedQuantity = round((float) $requirement['requested'], 2);

            if ($requestedQuantity > $availableQuantity) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        'لا يمكن عكس المخزون لطلب الشراء لأن المنتج "%s" يتوفر منه حالياً %s فقط، بينما الكمية المطلوب عكسها %s.',
                        $product->name,
                        number_format($availableQuantity, 2),
                        number_format($requestedQuantity, 2)
                    ),
                ]);
            }

            $product->update([
                'stock_quantity' => round($availableQuantity - $requestedQuantity, 2),
            ]);
        }
    }

    private function invoiceFormView(Company $company, User $user, ?Invoice $invoice = null): View
    {
        $customers = Customer::where('company_id', $company->id)->orderBy('name')->get();
        $products = Product::forCompany($company->id)->active()->orderBy('name')->get();
        $paymentAccounts = $this->directPaymentAccounts($company->id);
        $salesChannels = SalesChannel::query()->where('company_id', $company->id)->orderByDesc('is_default')->orderBy('name')->get();
        $defaultTaxRate = 15;
        $defaultSalesChannelId = $this->defaultSalesChannelId($company->id);
        $salesOwnerContext = $this->invoiceSalesOwnerContext($user, $invoice);

        if ($invoice) {
            $invoice->loadMissing(['items', 'employee.branch', 'user']);
        }

        return view('invoice_form', compact(
            'company',
            'customers',
            'products',
            'paymentAccounts',
            'salesChannels',
            'defaultTaxRate',
            'defaultSalesChannelId',
            'salesOwnerContext',
            'invoice'
        ));
    }

    private function resolveInvoiceSalesContext(User $user, int $companyId): array
    {
        $user->loadMissing('employee.branch');
        $employee = $user->employee;

        if ($user->hasRole(\App\Support\AccessControl::ROLE_OWNER) || $user->role === \App\Support\AccessControl::ROLE_OWNER) {
            $branchId = $employee?->branch?->id ?: $this->defaultBranchId($companyId);
            $branch = $employee?->branch;

            if (!$branch && $branchId) {
                $branch = Branch::query()
                    ->where('company_id', $companyId)
                    ->find($branchId);
            }

            if (!$branch) {
                throw ValidationException::withMessages([
                    'branch_id' => 'لا يوجد فرع افتراضي متاح لمالك الشركة لتسجيل عملية البيع.',
                ]);
            }

            return [
                'user_id' => $user->id,
                'employee_id' => $employee?->id,
                'branch_id' => $branch->id,
                'user_name' => $user->full_name,
                'employee_name' => $employee?->full_name,
                'branch_name' => $branch->name,
            ];
        }

        if (!$employee || (int) $employee->company_id !== (int) $companyId) {
            throw ValidationException::withMessages([
                'employee_id' => 'يجب ربط المستخدم الحالي بموظف قبل تسجيل عملية بيع.',
            ]);
        }

        $branch = $employee->branch;

        if (!$branch || (int) $branch->company_id !== (int) $companyId) {
            throw ValidationException::withMessages([
                'branch_id' => 'الموظف المرتبط بالمستخدم الحالي يجب أن يكون تابعًا لفرع صالح قبل تسجيل عملية بيع.',
            ]);
        }

        return [
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'branch_id' => $branch->id,
            'user_name' => $user->full_name,
            'employee_name' => $employee->full_name,
            'branch_name' => $branch->name,
        ];
    }

    private function resolveInvoiceOwnershipForUpdate(Invoice $invoice, User $user, int $companyId): array
    {
        if ($invoice->user_id && $invoice->employee_id && $invoice->branch_id) {
            return [
                'user_id' => (int) $invoice->user_id,
                'employee_id' => (int) $invoice->employee_id,
                'branch_id' => (int) $invoice->branch_id,
            ];
        }

        return $this->resolveInvoiceSalesContext($user, $companyId);
    }

    private function invoiceSalesOwnerContext(User $user, ?Invoice $invoice = null): array
    {
        $user->loadMissing('employee.branch');

        $linkedUser = $invoice?->user ?? $user;
        $linkedEmployee = $invoice?->employee ?? $user->employee;
        $linkedBranch = $invoice?->branch ?? $linkedEmployee?->branch;

        if (!$linkedBranch && ($user->hasRole(\App\Support\AccessControl::ROLE_OWNER) || $user->role === \App\Support\AccessControl::ROLE_OWNER)) {
            $defaultBranchId = $this->defaultBranchId((int) $user->company_id);
            $linkedBranch = $defaultBranchId
                ? Branch::query()->where('company_id', $user->company_id)->find($defaultBranchId)
                : null;
        }

        $warning = null;

        if (!$linkedBranch) {
            $warning = 'لا يوجد فرع افتراضي صالح لتسجيل المبيعات لهذا الحساب.';
        } elseif (!$linkedEmployee && !($user->hasRole(\App\Support\AccessControl::ROLE_OWNER) || $user->role === \App\Support\AccessControl::ROLE_OWNER)) {
            $warning = 'هذا المستخدم غير مرتبط بعد بموظف وفرع صالحين. لن يمكن حفظ المبيعات حتى يتم الربط من شاشة المستخدمين والموظفين.';
        }

        return [
            'user_name' => $linkedUser?->full_name,
            'employee_name' => $linkedEmployee?->full_name,
            'branch_name' => $linkedBranch?->name,
            'warning' => $warning,
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
                'category_id' => $this->resolveInvoiceItemCategoryId((int) $invoice->company_id, $validated['item_product_id'][$index] ?? null),
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
            $costPrice = (float) ($validated['item_cost_price'][$index] ?? 0);
            $unitPrice = (float) ($validated['item_price'][$index] ?? 0);
            $taxRate = (float) ($validated['item_tax_rate'][$index] ?? 0);
            $lineSubtotal = $quantity * $unitPrice;
            $lineTax = $lineSubtotal * ($taxRate / 100);

            // تحديد المنتج: إذا تم اختياره أو البحث عنه/إنشاؤه بالاسم
            $productId = $validated['item_product_id'][$index] ?? null;
            $productName = $validated['item_product_name'][$index] ?? $description;

            if (empty($productId) && !empty($productName)) {
                // البحث عن منتج موجود بالاسم
                $product = Product::where('company_id', $purchase->company_id)
                    ->where('name', trim($productName))
                    ->first();

                if (!$product) {
                    // إنشاء منتج جديد إذا لم يكن موجوداً
                    $product = Product::create([
                        'company_id' => $purchase->company_id,
                        'name' => trim($productName),
                        'code' => $this->generateProductCode($purchase->company_id),
                        'type' => 'product',
                        'cost_price' => $costPrice > 0 ? $costPrice : $unitPrice,
                        'sell_price' => $unitPrice > 0 ? $unitPrice : ($costPrice * 1.3), // إذا سعر البيع غير محدد استخدم هامش 30%
                        'stock_quantity' => 0,
                        'is_active' => true,
                    ]);
                }

                $productId = $product->id;
            }

            $purchase->items()->create([
                'product_id' => $productId,
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => round($lineTax, 2),
                'total' => round($lineSubtotal + $lineTax, 2),
            ]);
        }
    }

    private function generateProductCode(int $companyId): string
    {
        $lastProduct = Product::where('company_id', $companyId)
            ->where('code', 'like', 'PRD-%')
            ->orderBy('id', 'desc')
            ->first();

        $lastNumber = 0;
        if ($lastProduct && preg_match('/PRD-(\d+)/', $lastProduct->code, $matches)) {
            $lastNumber = (int) $matches[1];
        }

        return 'PRD-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    }

    private function normalizeJournalLines(array $validated, int $companyId): array
    {
        $lines = [];

        foreach ($validated['line_account'] as $index => $accountId) {
            $description = trim((string) ($validated['line_description'][$index] ?? ''));
            $debit = round((float) ($validated['line_debit'][$index] ?? 0), 2);
            $credit = round((float) ($validated['line_credit'][$index] ?? 0), 2);
            $hasValue = $accountId || $description !== '' || $debit > 0 || $credit > 0;

            if (!$hasValue) {
                continue;
            }

            if (!$accountId) {
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

        if (!$sourceType) {
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
            'SA' => ['name_ar' => 'المملكة العربية السعودية', 'currency' => 'SAR', 'cities' => ['الرياض', 'جدة', 'مكة المكرمة', 'المدينة المنورة', 'الدمام', 'الخبر', 'الظهران', 'الطائف', 'أبها', 'تبوك']],
            'AE' => ['name_ar' => 'الإمارات العربية المتحدة', 'currency' => 'AED', 'cities' => ['دبي', 'أبوظبي', 'الشارقة', 'عجمان', 'رأس الخيمة', 'الفجيرة', 'أم القيوين', 'العين']],
            'US' => ['name_ar' => 'الولايات المتحدة', 'currency' => 'USD', 'cities' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Miami', 'Dallas', 'Seattle', 'San Francisco']],
            'EG' => ['name_ar' => 'مصر', 'currency' => 'EGP', 'cities' => ['القاهرة', 'الجيزة', 'الإسكندرية', 'المنصورة', 'طنطا', 'أسيوط', 'الأقصر', 'أسوان']],
            'JO' => ['name_ar' => 'الأردن', 'currency' => 'JOD', 'cities' => ['عمّان', 'إربد', 'الزرقاء', 'العقبة', 'السلط', 'مادبا', 'جرش', 'الكرك']],
        ]);
    }

    private function countryConfigForCompany(Company $company): array
    {
        $countries = $this->countryConfigs();

        return $countries->get($company->country_code, $countries->get('SA'));
    }

    private function cityOptionsForCountryCode(?string $countryCode): array
    {
        $config = $this->countryConfigs()->get((string) $countryCode);

        return array_values($config['cities'] ?? []);
    }

    private function countryLabel(?string $countryCode): string
    {
        $config = $this->countryConfigs()->get((string) $countryCode);

        return $config['name_ar'] ?? ((string) $countryCode ?: 'غير محدد');
    }
}
