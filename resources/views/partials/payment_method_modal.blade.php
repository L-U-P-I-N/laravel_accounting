@php
    $isEditingPaymentMethod = (bool) $paymentMethod;
    $activePaymentMethodModalId = old('payment_method_modal');
    $useOldPaymentMethodInput = $activePaymentMethodModalId === $modalId;
    $paymentMethodNameValue = $useOldPaymentMethodInput ? old('name', $paymentMethod?->name) : $paymentMethod?->name;
    $paymentMethodCodeValue = $useOldPaymentMethodInput ? old('code', $paymentMethod?->code) : $paymentMethod?->code;
    $paymentMethodTypeValue = $useOldPaymentMethodInput ? old('type', $paymentMethod?->type ?? 'cash') : ($paymentMethod?->type ?? 'cash');
    $paymentMethodDefaultValue = $useOldPaymentMethodInput ? old('is_default', $paymentMethod?->is_default) : $paymentMethod?->is_default;
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}">
                @csrf
                @if ($isEditingPaymentMethod)
                    @method('PUT')
                @endif
                <input type="hidden" name="payment_method_modal" value="{{ $modalId }}">
                <div class="modal-header">
                    <h5 class="modal-title">{{ $modalTitle }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اسم الطريقة</label>
                        <input type="text" name="name" class="form-control" value="{{ $paymentMethodNameValue }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الكود</label>
                        <input type="text" name="code" class="form-control" value="{{ $paymentMethodCodeValue }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">النوع</label>
                        <select name="type" class="form-select" required>
                            @foreach ($typeLabels as $value => $label)
                                <option value="{{ $value }}" @selected($paymentMethodTypeValue === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="paymentMethodDefault{{ $modalId }}" @checked($paymentMethodDefaultValue)>
                        <label class="form-check-label" for="paymentMethodDefault{{ $modalId }}">تعيين كطريقة دفع افتراضية</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">{{ $isEditingPaymentMethod ? 'حفظ التعديلات' : 'إضافة الطريقة' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
