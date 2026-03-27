<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب شراء {{ $purchase->purchase_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }

        body {
            margin: 0;
            background: #eef2f7;
            color: #0f172a;
            padding: 24px;
        }

        .sheet {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            padding: 32px;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .print-button {
            border: 0;
            background: #0f172a;
            color: #fff;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 14px;
            cursor: pointer;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }

        .company-name {
            font-size: 30px;
            font-weight: 800;
            margin: 0 0 8px;
            color: #0f172a;
        }

        .purchase-number {
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 10px;
            text-align: left;
            direction: ltr;
        }

        .muted {
            color: #64748b;
            font-size: 14px;
            line-height: 1.8;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: #e2e8f0;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .meta-grid,
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px;
        }

        .card strong {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        thead th {
            background: #0f172a;
            color: #fff;
            padding: 12px;
            text-align: right;
            font-size: 14px;
        }

        tbody td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 14px;
        }

        .notes {
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .sheet {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
                padding: 16px;
            }

            .toolbar {
                display: none;
            }
        }
    </style>
</head>
<body>
    @php
        $companyCountryLabel = $companyCountry['name_ar'] ?? ($company->country_code ?? 'غير محددة');
        $companyCityLabel = $company->city ?: '-';
        $supplierCountryLabel = $purchase->supplier?->country ?: 'غير محددة';
        $supplierCityLabel = $purchase->supplier?->city ?: '-';
    @endphp
    <div class="sheet">
        <div class="toolbar">
            <div class="muted">عرض مستقل لطباعة طلب شراء واحد</div>
            <button type="button" class="print-button" onclick="window.print()">طباعة / PDF</button>
        </div>

        <div class="header">
            <div>
                <h1 class="company-name">{{ $company->name }}</h1>
                <div class="muted">{{ $company->address }}</div>
                <div class="muted">المدينة: {{ $companyCityLabel }}</div>
                <div class="muted">الدولة: {{ $companyCountryLabel }}</div>
                <div class="muted">الهاتف: {{ $company->phone }}</div>
                <div class="muted">البريد: {{ $company->email }}</div>
                @if ($company->tax_number)
                    <div class="muted">الرقم الضريبي: {{ $company->tax_number }}</div>
                @endif
            </div>
            <div>
                <div class="purchase-number">PO #{{ $purchase->purchase_number }}</div>
                <div class="status">
                    {{ match ($purchase->status) {
                        'draft' => 'مسودة',
                        'pending' => 'في الانتظار',
                        'approved' => 'معتمد',
                        'partial' => 'مدفوع جزئياً',
                        'paid' => 'مدفوع',
                        default => 'ملغي',
                    } }}
                </div>
                <div class="muted">تاريخ الشراء: {{ optional($purchase->purchase_date)->format('Y-m-d') ?: $purchase->purchase_date }}</div>
                <div class="muted">تاريخ الاستحقاق: {{ optional($purchase->due_date)->format('Y-m-d') ?: ($purchase->due_date ?? '-') }}</div>
            </div>
        </div>

        <div class="meta-grid">
            <div class="card">
                <strong>المورد</strong>
                <div class="muted">{{ $purchase->supplier?->name ?? '-' }}</div>
                <div class="muted">المدينة: {{ $supplierCityLabel }}</div>
                <div class="muted">الدولة: {{ $supplierCountryLabel }}</div>
                <div class="muted">{{ $purchase->supplier?->email ?? '' }}</div>
                <div class="muted">{{ $purchase->supplier?->phone ?? '' }}</div>
            </div>
            <div class="card">
                <strong>ملخص الدفع</strong>
                <div class="muted">المدفوع: {{ number_format((float) $purchase->paid_amount, 2) }} {{ $company->currency }}</div>
                <div class="muted">المتبقي: {{ number_format((float) $purchase->balance_due, 2) }} {{ $company->currency }}</div>
                <div class="muted">الحالة المالية: {{ $purchase->payment_status }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>المنتج</th>
                    <th>الوصف</th>
                    <th>الكمية</th>
                    <th>سعر الحبة</th>
                    <th>نسبة الضريبة</th>
                    <th>المبلغ الضريبي</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchase->items as $item)
                    <tr>
                        <td>{{ $item->product?->name ?? '-' }}</td>
                        <td>{{ $item->description ?: '-' }}</td>
                        <td>{{ number_format((float) $item->quantity, 2) }}</td>
                        <td>{{ number_format((float) $item->unit_price, 2) }} {{ $company->currency }}</td>
                        <td>{{ number_format((float) $item->tax_rate, 2) }}%</td>
                        <td>{{ number_format((float) $item->tax_amount, 2) }} {{ $company->currency }}</td>
                        <td>{{ number_format((float) $item->total, 2) }} {{ $company->currency }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary-grid">
            <div class="card">
                <strong>المجموع الفرعي</strong>
                <div>{{ number_format((float) $purchase->subtotal, 2) }} {{ $company->currency }}</div>
            </div>
            <div class="card">
                <strong>الضريبة</strong>
                <div>{{ number_format((float) $purchase->tax_amount, 2) }} {{ $company->currency }}</div>
            </div>
            <div class="card">
                <strong>الإجمالي</strong>
                <div>{{ number_format((float) $purchase->total, 2) }} {{ $company->currency }}</div>
            </div>
            <div class="card">
                <strong>المتبقي</strong>
                <div>{{ number_format((float) $purchase->balance_due, 2) }} {{ $company->currency }}</div>
            </div>
        </div>

        @if ($purchase->notes)
            <div class="notes">
                <strong>الملاحظات</strong>
                <div class="muted">{{ $purchase->notes }}</div>
            </div>
        @endif
    </div>

    @if ($printMode)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
