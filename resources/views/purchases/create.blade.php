@extends('layouts.app')

@php
    $canManagePurchases = auth()->user()->hasPermission('manage_purchases');
    $defaultTaxRate = 15;
    $purchaseDateValue = old('purchase_date', now()->format('Y-m-d'));
    $dueDateValue = old('due_date', now()->addDays(30)->format('Y-m-d'));
    $supplierIdValue = old('supplier_id');
    $paymentStatusValue = old('payment_status', 'pending');
    $paymentAccountIdValue = old('payment_account_id');
    $paidAmountValue = old('paid_amount', '0');
    $statusValue = old('status', 'draft');
    $notesValue = old('notes', '');
    $supplierInvoiceNumberValue = old('supplier_invoice_number', '');
@endphp

@section('title', 'إنشاء طلب شراء جديد')

@push('styles')
<style>
.purchase-form {
    background: #fff;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

.purchase-form .form-control,
.purchase-form .form-select {
    border-radius: 10px;
    padding: 12px;
    border: 2px solid #e0e0e0;
    transition: all 0.3s ease;
}

.purchase-form .form-control:focus,
.purchase-form .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.18);
}

.btn-save {
    background: linear-gradient(45deg, #667eea, #764ba2);
    border: none;
    border-radius: 25px;
    padding: 12px 30px;
    color: #fff;
    font-weight: 700;
}

.purchase-item {
    border-radius: 12px;
    padding: 20px;
    background: #f8f9fa;
    overflow: visible !important;
    max-height: none !important;
}

.purchase-item * {
    overflow: visible !important;
}

.item-row {
    background: #fff;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 10px;
    border: 1px solid #e0e0e0;
}

table.table-purchase-items {
    overflow: visible !important;
}

table.table-purchase-items td {
    position: relative !important;
    overflow: visible !important;
}

tbody[data-purchase-items] {
    overflow: visible !important;
    max-height: none !important;
}

.product-autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 99999 !important;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.product-suggestion {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
}

.product-suggestion:hover {
    background: #f8f9fa;
}

.total-section {
    background: linear-gradient(45deg, #e8f5e8, #f0f8f0);
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
}

.table-purchase-items input.form-control {
    min-width: 80px;
}

.table-purchase-items th {
    white-space: nowrap;
    font-size: 0.85rem;
    background: #f8f9fa;
}

.table-purchase-items td {
    vertical-align: middle;
}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-plus-circle"></i> إنشاء طلب شراء جديد</h2>
        <p class="text-muted mt-2 mb-0">إنشاء طلب شراء جديد مع إدارة البنود والمخزون</p>
    </div>
    <a href="{{ route('purchases') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-right ms-2"></i>العودة للمشتريات
    </a>
</div>

<div class="purchase-form">
    @php
        $purchaseItems = collect(old('item_description', ['']))->map(function ($description, $index) use ($defaultTaxRate) {
            return (object) [
                'product_id' => old('item_product_id.' . $index),
                'description' => $description,
                'quantity' => old('item_quantity.' . $index, 1),
                'unit_price' => old('item_price.' . $index, 0),
                'cost_price' => old('item_cost_price.' . $index, 0),
                'tax_rate' => old('item_tax_rate.' . $index, $defaultTaxRate),
            ];
        });
    @endphp

    <form method="POST" action="{{ route('purchases.store') }}" class="purchase-form-full" enctype="multipart/form-data" data-purchase-form data-paid-amount="0">
        @csrf

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- معلومات الطلب --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">المورد <span class="text-danger">*</span></label>
                <select name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                    <option value="">اختر المورد</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" {{ $supplierIdValue == $supplier->id ? 'selected' : '' }}>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
                @error('supplier_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">تاريخ الشراء <span class="text-danger">*</span></label>
                <input type="date" name="purchase_date" class="form-control @error('purchase_date') is-invalid @enderror"
                       value="{{ $purchaseDateValue }}" required>
                @error('purchase_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">تاريخ الاستحقاق</label>
                <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                       value="{{ $dueDateValue }}">
                @error('due_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">رقم فاتورة المورد</label>
                <input type="text" name="supplier_invoice_number" class="form-control @error('supplier_invoice_number') is-invalid @enderror"
                       value="{{ $supplierInvoiceNumberValue }}" placeholder="اختياري">
                @error('supplier_invoice_number')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">الحالة</label>
                <select name="status" class="form-select @error('status') is-invalid @enderror">
                    <option value="draft" {{ $statusValue == 'draft' ? 'selected' : '' }}>مسودة</option>
                    <option value="pending" {{ $statusValue == 'pending' ? 'selected' : '' }}>معلق</option>
                    <option value="approved" {{ $statusValue == 'approved' ? 'selected' : '' }}>معتمد</option>
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">حالة الدفع</label>
                <select name="payment_status" class="form-select @error('payment_status') is-invalid @enderror">
                    <option value="pending" {{ $paymentStatusValue == 'pending' ? 'selected' : '' }}>معلق</option>
                    <option value="partial" {{ $paymentStatusValue == 'partial' ? 'selected' : '' }}>جزئي</option>
                    <option value="paid" {{ $paymentStatusValue == 'paid' ? 'selected' : '' }}>مدفوع</option>
                </select>
                @error('payment_status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">حساب السداد</label>
                <select name="payment_account_id" class="form-select @error('payment_account_id') is-invalid @enderror">
                    <option value="">اختر الحساب</option>
                    @foreach ($paymentAccounts as $account)
                        <option value="{{ $account->id }}" {{ $paymentAccountIdValue == $account->id ? 'selected' : '' }}>
                            {{ $account->name }}
                        </option>
                    @endforeach
                </select>
                @error('payment_account_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">تاريخ الدفع</label>
                <input type="date" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror">
                @error('payment_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">الملاحظات</label>
                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2">{{ $notesValue }}</textarea>
                @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label">ملف الفاتورة (PDF أو صورة، حتى 8 ميغابايت)</label>
                <input type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png">
                @error('attachment')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        {{-- بنود الطلب --}}
        <h5 class="mb-3"><i class="fas fa-list me-2"></i>بنود الطلب</h5>
        <div class="purchase-item">
            <table class="table table-purchase-items align-middle table-bordered">
                <thead>
                    <tr>
                        <th style="width: 20%;">المنتج</th>
                        <th style="width: 10%;">التكلفة</th>
                        <th style="width: 20%;">الوصف</th>
                        <th style="width: 10%;">الكمية</th>
                        <th style="width: 10%;">سعر البيع</th>
                        <th style="width: 8%;">الضريبة %</th>
                        <th style="width: 12%;">المبلغ الضريبي</th>
                        <th style="width: 12%;">الإجمالي</th>
                        <th style="width: 8%;"></th>
                    </tr>
                </thead>
                <tbody data-purchase-items>
                    @foreach ($purchaseItems as $item)
                        <tr data-purchase-item-row>
                            <td style="position: relative;">
                                <input type="hidden" name="item_product_id[]" class="purchase-product-id" value="{{ $item->product_id }}">
                                <input type="text" name="item_product_name[]" class="form-control purchase-product-autocomplete"
                                       value="{{ $item->product_id ? ($products->firstWhere('id', $item->product_id)->name ?? '') : '' }}"
                                       placeholder="اكتب اسم المنتج..."
                                       autocomplete="off"
                                       data-products-json='{{ json_encode($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'description' => $p->description ?? '', 'cost_price' => $p->cost_price ?? 0, 'sell_price' => $p->sell_price ?? 0, 'tax_rate' => $p->tax_rate ?? 0])->values()) }}'>
                                <div class="product-autocomplete-dropdown" style="display: none;"></div>
                            </td>
                            <td>
                                <input type="number" name="item_cost_price[]" class="form-control purchase-item-cost" min="0" step="0.01" value="{{ $item->cost_price }}" lang="en" dir="ltr">
                            </td>
                            <td>
                                <input type="text" name="item_description[]" class="form-control purchase-item-description" value="{{ $item->description }}">
                            </td>
                            <td>
                                <input type="number" name="item_quantity[]" class="form-control purchase-item-quantity" min="0.01" step="0.01" value="{{ $item->quantity }}" lang="en" dir="ltr">
                            </td>
                            <td>
                                <input type="number" name="item_price[]" class="form-control purchase-item-price" min="0" step="0.01" value="{{ $item->unit_price }}" lang="en" dir="ltr">
                            </td>
                            <td>
                                <input type="number" name="item_tax_rate[]" class="form-control purchase-item-tax" min="0" max="100" step="0.01" value="{{ $item->tax_rate }}" lang="en" dir="ltr">
                            </td>
                            <td>
                                <input type="text" class="form-control purchase-item-tax-amount" readonly value="0.00">
                            </td>
                            <td>
                                <input type="text" class="form-control purchase-item-total" readonly value="0.00">
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-remove-purchase-item>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button type="button" class="btn btn-outline-primary" data-add-purchase-item>
            <i class="fas fa-plus me-1"></i> إضافة بند
        </button>

        {{-- ملخص المبالغ --}}
        <div class="row mt-4 g-3 total-section">
            <div class="col-md-2">
                <div class="text-center">
                    <strong>المجموع الفرعي:</strong>
                    <div class="h5 mb-0" data-purchase-subtotal>0.00 {{ $company->currency }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <strong>الضريبة:</strong>
                    <div class="h5 mb-0" data-purchase-tax>0.00 {{ $company->currency }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <strong>الإجمالي:</strong>
                    <div class="h5 mb-0" data-purchase-total>0.00 {{ $company->currency }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <strong>المدفوع:</strong>
                    <div class="h5 mb-0" data-purchase-paid-summary>0.00 {{ $company->currency }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="text-center">
                    <strong>المتبقي:</strong>
                    <div class="h5 mb-0" data-purchase-remaining>0.00 {{ $company->currency }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-save w-100">
                    <i class="fas fa-save me-2"></i>حفظ الطلب
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    initPurchaseForm();
});

function initPurchaseForm() {
    const forms = document.querySelectorAll('[data-purchase-form]');

    forms.forEach(form => {
        setupPurchaseItems(form);
        calculatePurchaseTotals(form);
    });
}

function setupPurchaseItems(form) {
    const itemsContainer = form.querySelector('[data-purchase-items]');
    const addItemBtn = form.querySelector('[data-add-purchase-item]');

    if (!itemsContainer || !addItemBtn) return;

    // Add new item row
    addItemBtn.addEventListener('click', () => {
        const template = itemsContainer.querySelector('[data-purchase-item-row]');
        if (!template) return;

        const newRow = template.cloneNode(true);
        newRow.querySelectorAll('input').forEach(input => {
            if (input.type === 'hidden') {
                input.value = '';
            } else if (input.type === 'number') {
                input.value = input.classList.contains('purchase-item-quantity') ? 1 :
                              input.classList.contains('purchase-item-tax') ? 15 : 0;
            } else {
                input.value = '';
            }
        });

        itemsContainer.appendChild(newRow);
        setupPurchaseItemRow(newRow, form);
    });

    // Setup existing rows
    itemsContainer.querySelectorAll('[data-purchase-item-row]').forEach(row => {
        setupPurchaseItemRow(row, form);
    });
}

function setupPurchaseItemRow(row, form) {
    const autocompleteInput = row.querySelector('.purchase-product-autocomplete');
    const productIdInput = row.querySelector('.purchase-product-id');
    const dropdown = row.querySelector('.product-autocomplete-dropdown');

    let products = [];
    try {
        products = JSON.parse(autocompleteInput?.dataset.productsJson || '[]');
    } catch (e) {
        products = [];
    }

    // Autocomplete functionality
    if (autocompleteInput && dropdown) {
        autocompleteInput.addEventListener('input', () => {
            const query = autocompleteInput.value.trim().toLowerCase();
            productIdInput.value = '';

            if (query.length < 1) {
                dropdown.style.display = 'none';
                return;
            }

            const matches = products.filter(p => p.name.toLowerCase().includes(query));

            if (matches.length > 0) {
                dropdown.innerHTML = matches.map(p =>
                    `<div class="product-suggestion" data-product='${JSON.stringify(p)}'>
                        ${p.name}
                    </div>`
                ).join('');
                dropdown.style.display = 'block';

                dropdown.querySelectorAll('.product-suggestion').forEach(suggestion => {
                    suggestion.addEventListener('click', () => {
                        const product = JSON.parse(suggestion.dataset.product);
                        autocompleteInput.value = product.name;
                        productIdInput.value = product.id;
                        dropdown.style.display = 'none';
                        applySelectedProduct(row, form, product);
                    });
                });
            } else {
                dropdown.innerHTML = `<div style="padding: 8px 12px; color: #666;">لم يتم العثور على منتج - سيتم إنشاؤه عند الحفظ</div>`;
                dropdown.style.display = 'block';
            }
        });

        document.addEventListener('click', (e) => {
            if (!autocompleteInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        autocompleteInput.addEventListener('focus', () => {
            if (autocompleteInput.value.trim() === '') {
                dropdown.innerHTML = products.slice(0, 10).map(p =>
                    `<div class="product-suggestion" data-product='${JSON.stringify(p)}'>
                        ${p.name}
                    </div>`
                ).join('');
                if (products.length > 0) dropdown.style.display = 'block';

                dropdown.querySelectorAll('.product-suggestion').forEach(suggestion => {
                    suggestion.addEventListener('click', () => {
                        const product = JSON.parse(suggestion.dataset.product);
                        autocompleteInput.value = product.name;
                        productIdInput.value = product.id;
                        dropdown.style.display = 'none';
                        applySelectedProduct(row, form, product);
                    });
                });
            }
        });
    }

    // Remove item button
    const removeBtn = row.querySelector('[data-remove-purchase-item]');
    if (removeBtn) {
        removeBtn.addEventListener('click', () => {
            const allRows = row.parentElement.querySelectorAll('[data-purchase-item-row]');
            if (allRows.length > 1) {
                row.remove();
                calculatePurchaseTotals(form);
            } else {
                // Clear the last row instead of removing
                row.querySelectorAll('input').forEach(input => {
                    if (input.type !== 'hidden') {
                        if (input.classList.contains('purchase-item-quantity')) {
                            input.value = 1;
                        } else if (input.classList.contains('purchase-item-tax')) {
                            input.value = 15;
                        } else {
                            input.value = '';
                        }
                    }
                });
                row.querySelector('.purchase-product-id').value = '';
                calculatePurchaseTotals(form);
            }
        });
    }

    // Calculate on input change
    const calculateInputs = row.querySelectorAll('.purchase-item-quantity, .purchase-item-price, .purchase-item-tax, .purchase-item-cost');
    calculateInputs.forEach(input => {
        input.addEventListener('input', () => calculatePurchaseTotals(form));
    });
}

function applySelectedProduct(row, form, product) {
    const descriptionInput = row.querySelector('.purchase-item-description');
    const costInput = row.querySelector('.purchase-item-cost');
    const priceInput = row.querySelector('.purchase-item-price');
    const taxInput = row.querySelector('.purchase-item-tax');

    if (descriptionInput && !descriptionInput.value) {
        descriptionInput.value = product.description || product.name;
    }
    if (costInput) {
        costInput.value = product.cost_price || 0;
    }
    if (priceInput) {
        priceInput.value = product.sell_price || 0;
    }
    if (taxInput) {
        taxInput.value = product.tax_rate || 15;
    }

    calculatePurchaseTotals(form);
}

function calculatePurchaseTotals(form) {
    let subtotal = 0;
    let totalTax = 0;

    form.querySelectorAll('[data-purchase-item-row]').forEach(row => {
        const quantity = parseFloat(row.querySelector('.purchase-item-quantity')?.value) || 0;
        const cost = parseFloat(row.querySelector('.purchase-item-cost')?.value) || 0;
        const taxRate = parseFloat(row.querySelector('.purchase-item-tax')?.value) || 0;

        const lineSubtotal = quantity * cost;
        const lineTax = lineSubtotal * (taxRate / 100);
        const lineTotal = lineSubtotal + lineTax;

        const taxAmountInput = row.querySelector('.purchase-item-tax-amount');
        const totalInput = row.querySelector('.purchase-item-total');

        if (taxAmountInput) taxAmountInput.value = lineTax.toFixed(2);
        if (totalInput) totalInput.value = lineTotal.toFixed(2);

        subtotal += lineSubtotal;
        totalTax += lineTax;
    });

    const total = subtotal + totalTax;

    const subtotalEl = form.querySelector('[data-purchase-subtotal]');
    const taxEl = form.querySelector('[data-purchase-tax]');
    const totalEl = form.querySelector('[data-purchase-total]');
    const paidSummaryEl = form.querySelector('[data-purchase-paid-summary]');
    const remainingEl = form.querySelector('[data-purchase-remaining]');

    if (subtotalEl) subtotalEl.textContent = subtotal.toFixed(2) + ' {{ $company->currency }}';
    if (taxEl) taxEl.textContent = totalTax.toFixed(2) + ' {{ $company->currency }}';
    if (totalEl) totalEl.textContent = total.toFixed(2) + ' {{ $company->currency }}';

    const paidAmount = 0;
    const remaining = total - paidAmount;

    if (paidSummaryEl) paidSummaryEl.textContent = paidAmount.toFixed(2) + ' {{ $company->currency }}';
    if (remainingEl) remainingEl.textContent = remaining.toFixed(2) + ' {{ $company->currency }}';
}
</script>
@endpush
