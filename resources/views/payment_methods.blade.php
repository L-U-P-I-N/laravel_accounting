@extends('layouts.app')

@php
    $typeLabels = [
        'cash' => 'نقدي',
        'bank' => 'تحويل بنكي',
        'card' => 'بطاقة',
        'wallet' => 'محفظة',
        'other' => 'أخرى',
    ];
@endphp

@section('title', 'طرق الدفع')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fas fa-money-check-dollar"></i> طرق الدفع</h2>
        <p class="text-muted mt-2 mb-0">إدارة طرق الدفع المستخدمة في شاشات المبيعات والمشتريات والتقارير.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPaymentMethodModal">
        <i class="fas fa-plus ms-1"></i> إضافة طريقة دفع
    </button>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon blue"><i class="fas fa-money-check-dollar"></i></div><div class="stat-value">{{ $paymentMethods->count() }}</div><div class="stat-label">إجمالي الطرق</div></div></div>
    <div class="col-md-4 mb-3 mb-md-0"><div class="stat-card"><div class="stat-icon green"><i class="fas fa-star"></i></div><div class="stat-value">{{ $paymentMethods->where('is_default', true)->count() }}</div><div class="stat-label">طرق افتراضية</div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon orange"><i class="fas fa-file-invoice-dollar"></i></div><div class="stat-value">{{ $paymentMethods->sum('invoices_count') + $paymentMethods->sum('purchases_count') }}</div><div class="stat-label">العمليات المرتبطة</div></div></div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">قائمة طرق الدفع</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>الكود</th>
                        <th>النوع</th>
                        <th>المبيعات</th>
                        <th>المشتريات</th>
                        <th>الدفعات</th>
                        <th>الافتراضي</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($paymentMethods as $paymentMethod)
                        <tr>
                            <td>{{ $paymentMethod->name }}</td>
                            <td>{{ $paymentMethod->code }}</td>
                            <td>{{ $typeLabels[$paymentMethod->type] ?? $paymentMethod->type }}</td>
                            <td>{{ $paymentMethod->invoices_count }}</td>
                            <td>{{ $paymentMethod->purchases_count }}</td>
                            <td>{{ $paymentMethod->payments_count }}</td>
                            <td>{!! $paymentMethod->is_default ? '<span class="badge bg-success">افتراضي</span>' : '<span class="text-muted">-</span>' !!}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPaymentMethodModal{{ $paymentMethod->id }}"><i class="fas fa-edit"></i></button>
                                <form method="POST" action="{{ route('payment_methods.destroy', $paymentMethod) }}" class="d-inline" onsubmit="return confirm('سيتم حذف طريقة الدفع فقط إذا لم تكن مرتبطة بسجلات محفوظة. هل تريد المتابعة؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-4">لا توجد طرق دفع بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('partials.payment_method_modal', [
    'modalId' => 'createPaymentMethodModal',
    'modalTitle' => 'إضافة طريقة دفع',
    'action' => route('payment_methods.store'),
    'paymentMethod' => null,
    'typeLabels' => $typeLabels,
])

@foreach ($paymentMethods as $paymentMethod)
    @include('partials.payment_method_modal', [
        'modalId' => 'editPaymentMethodModal' . $paymentMethod->id,
        'modalTitle' => 'تعديل طريقة الدفع: ' . $paymentMethod->name,
        'action' => route('payment_methods.update', $paymentMethod),
        'paymentMethod' => $paymentMethod,
        'typeLabels' => $typeLabels,
    ])
@endforeach
@endsection

@push('scripts')
@if ($errors->any() && old('payment_method_modal'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById(@json(old('payment_method_modal')));

    if (modalElement) {
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
});
</script>
@endif
@endpush
