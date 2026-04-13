<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>طباعة طلب الشراء {{ $purchase->purchase_number }}</title>
    <style>
        body { font-family: 'Tahoma', sans-serif; margin: 32px; color: #222; }
        h1 { margin-bottom: 8px; }
        h3 { margin: 0 0 8px; }
        .layout { display: flex; justify-content: space-between; gap: 32px; }
        .card { border: 1px solid #ddd; padding: 16px; border-radius: 8px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e0e0e0; padding: 8px; text-align: center; }
        th { background-color: #f9f9f9; }
        .totals { display: flex; justify-content: flex-end; gap: 16px; margin-top: 16px; }
        .totals div { border: 1px solid #ddd; padding: 12px 16px; border-radius: 6px; }
        .muted { color: #666; font-size: 14px; }
    </style>
</head>
<body>
@php
    $paymentStatusLabels = [
        'paid' => 'دفع كامل',
        'partial' => 'دفع جزئي',
        'pending' => 'أجل',
    ];
@endphp
    <header class="card">
        <h1>طلب شراء {{ $purchase->purchase_number }}</h1>
        <p class="muted">تاريخ الشراء: {{ optional($purchase->purchase_date)->format('Y-m-d') ?: '-' }}</p>
        <p class="muted">تاريخ الاستحقاق: {{ optional($purchase->due_date)->format('Y-m-d') ?: '-' }}</p>
        <p class="muted">حالة الدفع: {{ $paymentStatusLabels[$purchase->payment_status] ?? 'غير محدد' }}</p>
    </header>

    <section class="layout">
        <div class="card" style="flex:1">
            <h3>بيانات الشركة</h3>
            @if($company->logo_url)
                <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }}" style="max-height: 60px; max-width: 150px; object-fit: contain; margin-bottom: 10px;">
            @endif
            <p>الاسم: {{ $company->name }}</p>
            <p>المدينة: {{ $company->city ?: '-' }}</p>
            <p>الدولة: {{ $companyCountry }}</p>
        </div>
        <div class="card" style="flex:1">
            <h3>بيانات المورد</h3>
            <p>الاسم: {{ $supplier?->name ?? '-' }}</p>
            <p>المدينة: {{ $supplier?->city ?? '-' }}</p>
            <p>الدولة: {{ $supplierCountry }}</p>
        </div>
    </section>

    <section class="card">
        <h3>تفاصيل البنود</h3>
        <table>
            <thead>
                <tr>
                    <th>المنتج</th>
                    <th>الوصف</th>
                    <th>الكمية</th>
                    <th>سعر الحبة</th>
                    <th>الضريبة %</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($purchase->items as $item)
                    <tr>
                        <td>{{ $item->product?->name ?? '-' }}</td>
                        <td>{{ $item->description ?? '-' }}</td>
                        <td>{{ number_format((float) $item->quantity, 2) }}</td>
                        <td>{{ number_format((float) $item->unit_price, 2) }} {{ $company->currency }}</td>
                        <td>{{ number_format((float) $item->tax_rate, 2) }}%</td>
                        <td>{{ number_format((float) $item->total, 2) }} {{ $company->currency }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">لا توجد بنود مسجلة</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="totals">
        <div><strong>المجموع الفرعي:</strong><br>{{ number_format((float) $purchase->subtotal, 2) }} {{ $company->currency }}</div>
        <div><strong>الضريبة:</strong><br>{{ number_format((float) $purchase->tax_amount, 2) }} {{ $company->currency }}</div>
        <div><strong>الإجمالي:</strong><br>{{ number_format((float) $purchase->total, 2) }} {{ $company->currency }}</div>
        <div><strong>المتبقي:</strong><br>{{ number_format((float) $purchase->balance_due, 2) }} {{ $company->currency }}</div>
    </section>

    @if ($purchase->notes)
        <section class="card">
            <h3>ملاحظات</h3>
            <p>{{ $purchase->notes }}</p>
        </section>
    @endif
</body>
</html>
