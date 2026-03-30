@extends('layouts.app')

@php
    $canManageInvoices = auth()->user()->hasPermission('manage_invoices');
    $defaultTaxRate = $defaultTaxRate ?? 15; // fallback when controller does not provide rate
    $isEditingInvoice = isset($invoice) && $invoice->exists;
    $invoiceDateValue = old('invoice_date', $isEditingInvoice ? optional($invoice->invoice_date)->format('Y-m-d') : now()->format('Y-m-d'));
    $dueDateValue = old('due_date', $isEditingInvoice ? optional($invoice->due_date)->format('Y-m-d') : now()->addDays(30)->format('Y-m-d'));
    $customerIdValue = old('customer_id', $isEditingInvoice ? $invoice->customer_id : null);
    $branchIdValue = old('branch_id', $isEditingInvoice ? $invoice->branch_id : ($defaultBranchId ?? null));
    $employeeIdValue = old('employee_id', $isEditingInvoice ? $invoice->employee_id : null);
    $salesChannelIdValue = old('sales_channel_id', $isEditingInvoice ? $invoice->sales_channel_id : ($defaultSalesChannelId ?? null));
    $paymentMethodIdValue = old('payment_method_id', $isEditingInvoice ? $invoice->payment_method_id : ($defaultPaymentMethodId ?? null));
    $invoiceStatusValue = old('status', $isEditingInvoice ? $invoice->status : 'sent');
    $notesValue = old('notes', $isEditingInvoice ? $invoice->notes : '');
    $termsValue = old('terms', $isEditingInvoice ? $invoice->terms : '');
@endphp

@section('title', $isEditingInvoice ? 'تعديل عملية البيع' : 'إضافة مبيعات')

@push('styles')
<style>
.invoice-form {
    background: #fff;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

.invoice-form .form-control,
.invoice-form .form-select {
    border-radius: 10px;
    padding: 12px;
    border: 2px solid #e0e0e0;
    transition: all 0.3s ease;
}

.invoice-form .form-control:focus,
.invoice-form .form-select:focus {
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

.invoice-item,
.tax-info {
    border-radius: 12px;
    padding: 20px;
}

.invoice-item {
    background: #f8f9fa;
}

.item-row {
    background: #fff;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 10px;
    border: 1px solid #e0e0e0;
}

.tax-info {
    background: linear-gradient(45deg, #e8f5e8, #f0f8f0);
    margin-top: 20px;
}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-plus-circle"></i> {{ $isEditingInvoice ? 'تعديل عملية البيع' : 'إضافة مبيعات' }}</h2>
        <p class="text-muted mt-2 mb-0">{{ $isEditingInvoice ? 'تحديث بيانات عملية البيع الحالية مع الحفاظ على سلامة المخزون.' : 'إضافة مبيعات جديدة بنفس هيكلة واجهة Flask' }}</p>
    </div>
    <a href="{{ route('invoices') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-right ms-2"></i>العودة للمبيعات
    </a>
</div>

<div class="invoice-form">
    @php
        $invoiceItems = collect(old('item_description', []))->map(function ($description, $index) use ($defaultTaxRate) {
            return (object) [
                'product_id' => old('item_product_id.' . $index),
                'description' => $description,
                'quantity' => old('item_quantity.' . $index, 1),
                'unit_price' => old('item_price.' . $index, 0),
                'tax_rate' => old('item_tax_rate.' . $index, $defaultTaxRate),
            ];
        });

        if ($invoiceItems->isEmpty()) {
            $invoiceItems = $isEditingInvoice
                ? $invoice->items->map(function ($item) {
                    return (object) [
                        'product_id' => $item->product_id,
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'tax_rate' => $item->tax_rate,
                    ];
                })
                : collect([(object) [
                    'product_id' => null,
                    'description' => '',
                    'quantity' => 1,
                    'unit_price' => 0,
                    'tax_rate' => $defaultTaxRate,
                ]]);
        }
    @endphp
    <form method="POST" action="{{ $isEditingInvoice ? route('invoices.update', $invoice) : route('invoices.store') }}" data-invoice-form>
        @csrf
        @if ($isEditingInvoice)
            @method('PUT')
        @endif
        <div class="row mb-4">
            <div class="col-md-4 mb-3 mb-md-0">
                <label class="form-label">العميل *</label>
                <select name="customer_id" class="form-select" required>
                    <option value="">اختر العميل</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" {{ (string) $customerIdValue === (string) $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <label class="form-label">تاريخ الفاتورة *</label>
                <input type="date" name="invoice_date" class="form-control" value="{{ $invoiceDateValue }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">تاريخ الاستحقاق</label>
                <input type="date" name="due_date" class="form-control" value="{{ $dueDateValue }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">الحالة</label>
                <select name="status" class="form-select">
                    <option value="draft" {{ $invoiceStatusValue === 'draft' ? 'selected' : '' }}>مسودة</option>
                    <option value="sent" {{ $invoiceStatusValue === 'sent' ? 'selected' : '' }}>مرسلة</option>
                </select>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3 mb-3 mb-md-0">
                <label class="form-label">الفرع *</label>
                <select name="branch_id" class="form-select" required>
                    <option value="">اختر الفرع</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" {{ (string) $branchIdValue === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <label class="form-label">الموظف</label>
                <select name="employee_id" class="form-select">
                    <option value="">بدون موظف</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" {{ (string) $employeeIdValue === (string) $employee->id ? 'selected' : '' }}>{{ trim($employee->full_name) !== '' ? $employee->full_name : ('موظف #' . $employee->id) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <label class="form-label">قناة البيع *</label>
                <select name="sales_channel_id" class="form-select" required>
                    <option value="">اختر قناة البيع</option>
                    @foreach ($salesChannels as $salesChannel)
                        <option value="{{ $salesChannel->id }}" {{ (string) $salesChannelIdValue === (string) $salesChannel->id ? 'selected' : '' }}>{{ $salesChannel->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">طريقة الدفع *</label>
                <select name="payment_method_id" class="form-select" required>
                    <option value="">اختر طريقة الدفع</option>
                    @foreach ($paymentMethods as $paymentMethod)
                        <option value="{{ $paymentMethod->id }}" {{ (string) $paymentMethodIdValue === (string) $paymentMethod->id ? 'selected' : '' }}>{{ $paymentMethod->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="invoice-item">
            <h5 class="mb-3"><i class="fas fa-list ms-2 text-primary"></i>بنود الفاتورة</h5>
            <div id="itemsContainer">
                @foreach ($invoiceItems as $item)
                    <div class="item-row" data-invoice-item-row>
                        <div class="row align-items-center g-3">
                            <div class="col-md-3">
                                <label class="form-label">المنتج</label>
                                <select name="item_product_id[]" class="form-select invoice-product-select">
                                    <option value="">اختر المنتج</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" data-description="{{ $product->description ?? '' }}" data-sell-price="{{ $product->sell_price ?? 0 }}" data-stock-quantity="{{ $product->stock_quantity ?? 0 }}" data-product-type="{{ $product->type }}" data-product-name="{{ $product->name }}" {{ (string) $item->product_id === (string) $product->id ? 'selected' : '' }}>{{ $product->name }}{{ $product->type !== 'service' ? ' - المتاح: ' . number_format((float) $product->stock_quantity, 2) : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">الوصف *</label>
                                <input type="text" name="item_description[]" class="form-control invoice-item-description" value="{{ $item->description }}" placeholder="اكتب الوصف أو اختر منتج" required>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">الكمية *</label>
                                @php $quantityErrorKey = 'item_quantity.' . $loop->index; @endphp
                                <input type="number" name="item_quantity[]" class="form-control invoice-item-quantity @error($quantityErrorKey) is-invalid @enderror" value="{{ $item->quantity }}" min="0.01" step="0.01" required>
                                @error($quantityErrorKey)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <div class="form-text text-danger d-none" data-stock-feedback></div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">السعر *</label>
                                <input type="number" name="item_price[]" class="form-control invoice-item-price" value="{{ $item->unit_price }}" min="0" step="0.01" required>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">الضريبة %</label>
                                <input type="number" name="item_tax_rate[]" class="form-control invoice-item-tax" value="{{ $item->tax_rate }}" min="0" step="0.1">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">الإجمالي</label>
                                <input type="text" class="form-control invoice-item-total" readonly value="0.00">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label d-block">&nbsp;</label>
                                <button type="button" class="btn btn-outline-danger w-100" data-remove-invoice-item><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-3">
                @if ($canManageInvoices)
                    <button type="button" class="btn btn-outline-primary" id="addInvoiceItem">
                        <i class="fas fa-plus ms-2"></i>إضافة بند جديد
                    </button>
                @endif
            </div>
        </div>

        <div class="alert alert-warning d-none mt-3" data-invoice-stock-warning role="alert"></div>

        <div class="row mt-4">
            <div class="col-md-12">
                <label class="form-label">ملاحظات</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="ملاحظات إضافية...">{{ $notesValue }}</textarea>
            </div>
            <div class="col-md-12 mt-3">
                <label class="form-label">الشروط</label>
                <textarea name="terms" class="form-control" rows="2" placeholder="شروط السداد أو شروط الفاتورة">{{ $termsValue }}</textarea>
            </div>
        </div>

        <div class="tax-info">
            <div class="row">
                <div class="col-md-3 mb-3 mb-md-0">
                    <label class="form-label">المجموع الفرعي</label>
                    <h4 id="subtotal">0.00 {{ $company->currency }}</h4>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <label class="form-label">ضريبة القيمة المضافة</label>
                    <h4 id="taxAmount">0.00 {{ $company->currency }}</h4>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <label class="form-label">الخصم</label>
                    <input type="number" id="discountAmount" class="form-control" value="0" min="0" step="0.01" disabled>
                </div>
                <div class="col-md-3">
                    <label class="form-label">الإجمالي</label>
                    <h4 id="totalAmount" class="text-primary">0.00 {{ $company->currency }}</h4>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="d-flex gap-2 flex-wrap">
                    @if ($canManageInvoices)
                        <button type="submit" class="btn btn-save">
                            <i class="fas fa-save ms-2"></i>{{ $isEditingInvoice ? 'حفظ التعديلات' : 'حفظ الفاتورة' }}
                        </button>
                    @endif
                    <a href="{{ route('invoices') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-times ms-2"></i>إلغاء
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function invoiceNumericValue(value) {
    const parsedValue = Number.parseFloat(value);

    return Number.isFinite(parsedValue) ? parsedValue : 0;
}

function recalculateInvoiceTotals(form) {
    let subtotal = 0;
    let taxAmount = 0;

    form.querySelectorAll('[data-invoice-item-row]').forEach((row) => {
        const quantity = invoiceNumericValue(row.querySelector('.invoice-item-quantity')?.value);
        const price = invoiceNumericValue(row.querySelector('.invoice-item-price')?.value);
        const taxRate = invoiceNumericValue(row.querySelector('.invoice-item-tax')?.value);
        const lineSubtotal = quantity * price;
        const lineTax = lineSubtotal * (taxRate / 100);
        const lineTotal = lineSubtotal + lineTax;

        subtotal += lineSubtotal;
        taxAmount += lineTax;

        const totalField = row.querySelector('.invoice-item-total');
        if (totalField) {
            totalField.value = lineTotal.toFixed(2);
        }
    });

    const discount = invoiceNumericValue(form.querySelector('#discountAmount')?.value);
    const total = subtotal + taxAmount - discount;

    form.querySelector('#subtotal').textContent = `${subtotal.toFixed(2)} {{ $company->currency }}`;
    form.querySelector('#taxAmount').textContent = `${taxAmount.toFixed(2)} {{ $company->currency }}`;
    form.querySelector('#totalAmount').textContent = `${total.toFixed(2)} {{ $company->currency }}`;
    updateInvoiceStockWarnings(form);
}

function invoiceSelectedProductOption(row) {
    const select = row.querySelector('.invoice-product-select');

    if (!select || select.selectedIndex < 0) {
        return null;
    }

    return select.options[select.selectedIndex] || null;
}

function setInvoiceRowStockMessage(row, message) {
    const quantityInput = row.querySelector('.invoice-item-quantity');
    const feedback = row.querySelector('[data-stock-feedback]');

    if (quantityInput) {
        quantityInput.classList.toggle('is-invalid', Boolean(message));
    }

    if (!feedback) {
        return;
    }

    if (message) {
        feedback.textContent = message;
        feedback.classList.remove('d-none');

        return;
    }

    feedback.textContent = '';
    feedback.classList.add('d-none');
}

function updateInvoiceStockWarnings(form) {
    const groupedProducts = new Map();
    const warningBox = form.querySelector('[data-invoice-stock-warning]');

    form.querySelectorAll('[data-invoice-item-row]').forEach((row) => {
        setInvoiceRowStockMessage(row, '');

        const selectedOption = invoiceSelectedProductOption(row);
        if (!selectedOption || !selectedOption.value || selectedOption.dataset.productType === 'service') {
            return;
        }

        const quantity = invoiceNumericValue(row.querySelector('.invoice-item-quantity')?.value);
        if (quantity <= 0) {
            return;
        }

        const productId = selectedOption.value;
        const existingGroup = groupedProducts.get(productId) ?? {
            available: invoiceNumericValue(selectedOption.dataset.stockQuantity),
            name: selectedOption.dataset.productName || selectedOption.textContent.trim(),
            requested: 0,
            rows: [],
        };

        existingGroup.requested += quantity;
        existingGroup.rows.push(row);
        groupedProducts.set(productId, existingGroup);
    });

    const messages = [];

    groupedProducts.forEach((group) => {
        if (group.requested <= group.available) {
            return;
        }

        const message = group.available > 0
            ? `الكمية المتاحة للمنتج "${group.name}" هي ${group.available.toFixed(2)} فقط، بينما إجمالي الكمية المطلوبة ${group.requested.toFixed(2)}.`
            : `المنتج "${group.name}" نفدت كميته الحالية ولا يمكن إضافته إلى الفاتورة.`;

        group.rows.forEach((row) => setInvoiceRowStockMessage(row, message));
        messages.push(message);
    });

    if (warningBox) {
        if (messages.length > 0) {
            warningBox.innerHTML = messages.map((message) => `<div>${message}</div>`).join('');
            warningBox.classList.remove('d-none');
        } else {
            warningBox.innerHTML = '';
            warningBox.classList.add('d-none');
        }
    }

    form.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.disabled = messages.length > 0;
    });

    return messages.length === 0;
}

function applyInvoiceProduct(row, form) {
    const select = row.querySelector('.invoice-product-select');
    const descriptionInput = row.querySelector('.invoice-item-description');
    const priceInput = row.querySelector('.invoice-item-price');

    if (!select) {
        return;
    }

    const selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
        recalculateInvoiceTotals(form);
        return;
    }

    if (descriptionInput && (!descriptionInput.value || descriptionInput.dataset.autoFilled === 'true')) {
        descriptionInput.value = selectedOption.dataset.description || selectedOption.textContent.trim();
        descriptionInput.dataset.autoFilled = 'true';
    }

    if (priceInput && (!priceInput.value || invoiceNumericValue(priceInput.value) === 0 || priceInput.dataset.autoFilled === 'true')) {
        priceInput.value = selectedOption.dataset.sellPrice || '0';
        priceInput.dataset.autoFilled = 'true';
    }

    recalculateInvoiceTotals(form);
}

function bindInvoiceRow(row, form) {
    row.querySelector('.invoice-product-select')?.addEventListener('change', () => applyInvoiceProduct(row, form));
    row.querySelector('.invoice-item-description')?.addEventListener('input', (event) => {
        event.target.dataset.autoFilled = 'false';
    });
    row.querySelector('.invoice-item-price')?.addEventListener('input', (event) => {
        event.target.dataset.autoFilled = 'false';
        recalculateInvoiceTotals(form);
    });
    row.querySelectorAll('.invoice-item-quantity, .invoice-item-tax').forEach((input) => {
        input.addEventListener('input', () => recalculateInvoiceTotals(form));
    });
    row.querySelector('[data-remove-invoice-item]')?.addEventListener('click', () => {
        if (form.querySelectorAll('[data-invoice-item-row]').length > 1) {
            row.remove();
            recalculateInvoiceTotals(form);
        }
    });

    if (row.querySelector('.invoice-product-select')?.value) {
        applyInvoiceProduct(row, form);
    }
}

function addInvoiceRow(form) {
    const firstRow = form.querySelector('[data-invoice-item-row]');
    const container = form.querySelector('#itemsContainer');

    if (!firstRow || !container) {
        return;
    }

    const clone = firstRow.cloneNode(true);
    clone.querySelectorAll('input').forEach((input) => {
        if (input.classList.contains('invoice-item-quantity')) {
            input.value = '1';
            input.classList.remove('is-invalid');
        } else if (input.classList.contains('invoice-item-tax')) {
            input.value = '{{ $defaultTaxRate }}';
        } else if (input.classList.contains('invoice-item-total')) {
            input.value = '0.00';
        } else {
            input.value = '';
        }
        delete input.dataset.autoFilled;
    });
    clone.querySelectorAll('[data-stock-feedback]').forEach((feedback) => {
        feedback.textContent = '';
        feedback.classList.add('d-none');
    });
    clone.querySelectorAll('select').forEach((select) => {
        select.selectedIndex = 0;
    });

    container.appendChild(clone);
    bindInvoiceRow(clone, form);
    recalculateInvoiceTotals(form);
}

document.querySelectorAll('[data-invoice-form]').forEach((form) => {
    form.querySelectorAll('[data-invoice-item-row]').forEach((row) => bindInvoiceRow(row, form));
    form.querySelector('#addInvoiceItem')?.addEventListener('click', () => addInvoiceRow(form));
    form.addEventListener('submit', (event) => {
        if (updateInvoiceStockWarnings(form)) {
            return;
        }

        event.preventDefault();
        form.querySelector('.invoice-item-quantity.is-invalid')?.focus();
    });
    recalculateInvoiceTotals(form);
});
</script>
@endpush
