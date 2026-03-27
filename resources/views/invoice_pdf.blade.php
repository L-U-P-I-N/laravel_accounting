<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة رقم {{ $invoice->invoice_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    <style>
        * {

            font-family: 'Tajawal', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f5f5f5;
            padding: 20px;
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 24px;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #667eea;
        }

        .company-info h1 {
            color: #667eea;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .company-info p,
        .billing-info p,
        .invoice-meta,
        .notes-content,
        .footer {
            color: #666;
            font-size: 14px;
            line-height: 1.7;
        }

        .invoice-details {
            text-align: left;
            direction: ltr;
        }

        .invoice-number {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }

        .billing-info {
            display: flex;
            justify-content: space-between;
            gap: 40px;
            margin-bottom: 30px;
        }

        .bill-to,
        .bill-from {
            flex: 1;
        }

        .section-title,
        .notes-title {
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th {
            background: #667eea;
            color: #fff;
            padding: 15px;
            text-align: right;
            font-weight: 600;
            font-size: 14px;
        }

        .items-table td {
            padding: 15px;
            text-align: right;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        .text-right {
            text-align: left;
            direction: ltr;
        }

        .description {
            font-weight: 500;
            color: #333;
        }

        .totals-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #667eea;
        }

        .totals-table {
            width: 300px;
            margin-right: auto;
            direction: ltr;
        }

        .totals-table td {
            padding: 10px;
            font-size: 14px;
        }

        .totals-table .label {
            text-align: right;
            color: #666;
        }

        .totals-table .value {
            text-align: left;
            font-weight: 600;
            color: #333;
        }

        .totals-table .total-row {
            border-top: 2px solid #667eea;
        }

        .notes-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 12px;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-draft {
            background: #f8f9fa;
            color: #6c757d;
        }

        .status-sent {
            background: #d4edda;
            color: #155724;
        }

        .status-paid {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-overdue,
        .status-partial {
            background: #f8d7da;
            color: #721c24;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(102, 126, 234, 0.1);
            font-weight: 900;
            z-index: -1;
            pointer-events: none;
        }

        @media print {
            body {
                padding: 0;
                background: #fff;
            }

            .invoice-container {
                box-shadow: none;
                border-radius: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    @if ($invoice->status === 'draft')
        <div class="watermark">مسودة</div>
    @endif

    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-info">
                <h1>{{ $company->name }}</h1>
                <p>{{ $company->address }}</p>
                <p>{{ trim(($company->city ?? '') . ' ' . ($company->country_code ?? '')) }}</p>
                <p>الهاتف: {{ $company->phone }}</p>
                <p>البريد: {{ $company->email }}</p>
                @if ($company->tax_number)
                    <p>الرقم الضريبي: {{ $company->tax_number }}</p>
                @endif
            </div>

            <div class="invoice-details">
                <div class="invoice-number">فاتورة #{{ $invoice->invoice_number }}</div>
                <div class="status-badge status-{{ $invoice->status }}">
                    {{ match ($invoice->status) {
                        'draft' => 'مسودة',
                        'sent' => 'مرسلة',
                        'paid' => 'مدفوعة',
                        'overdue' => 'متأخرة',
                        'partial' => 'جزئية',
                        default => $invoice->status,
                    } }}
                </div>
                <div class="invoice-meta">
                    <div>التاريخ: {{ optional($invoice->invoice_date)->format('Y-m-d') }}</div>
                    @if ($invoice->due_date)
                        <div>تاريخ الاستحقاق: {{ optional($invoice->due_date)->format('Y-m-d') }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="billing-info">
            <div class="bill-from">
                <div class="section-title">معلومات الشركة</div>
                <p><strong>{{ $company->name }}</strong></p>
                <p>{{ $company->address }}</p>
                <p>{{ trim(($company->city ?? '') . ' ' . ($company->country_code ?? '')) }}</p>
                <p>الهاتف: {{ $company->phone }}</p>
                <p>البريد: {{ $company->email }}</p>
            </div>

            <div class="bill-to">
                <div class="section-title">فاتورة إلى</div>
                <p><strong>{{ $invoice->customer?->name ?? 'عميل غير محدد' }}</strong></p>
                @if ($invoice->customer?->address)
                    <p>{{ $invoice->customer->address }}</p>
                @endif
                @if ($invoice->customer?->city)
                    <p>{{ trim(($invoice->customer->city ?? '') . ' ' . ($invoice->customer->country_code ?? '')) }}</p>
                @endif
                @if ($invoice->customer?->phone)
                    <p>الهاتف: {{ $invoice->customer->phone }}</p>
                @endif
                @if ($invoice->customer?->email)
                    <p>البريد: {{ $invoice->customer->email }}</p>
                @endif
                @if ($invoice->customer?->tax_number)
                    <p>الرقم الضريبي: {{ $invoice->customer->tax_number }}</p>
                @endif
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%">#</th>
                    <th style="width: 45%">الوصف</th>
                    <th style="width: 15%">الكمية</th>
                    <th style="width: 15%">السعر</th>
                    <th style="width: 10%">الضريبة</th>
                    <th style="width: 10%">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="description">{{ $item->description }}</td>
                        <td class="text-right">{{ number_format((float) $item->quantity, 2) }}</td>
                        <td class="text-right">{{ number_format((float) $item->unit_price, 2) }} {{ $company->currency }}</td>
                        <td class="text-right">{{ number_format((float) $item->tax_rate, 1) }}%</td>
                        <td class="text-right">{{ number_format((float) $item->total, 2) }} {{ $company->currency }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">لا توجد بنود فاتورة محفوظة في قاعدة البيانات الحالية.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="label">المجموع الفرعي:</td>
                    <td class="value">{{ number_format((float) $invoice->subtotal, 2) }} {{ $company->currency }}</td>
                </tr>
                <tr>
                    <td class="label">ضريبة القيمة المضافة:</td>
                    <td class="value">{{ number_format((float) $invoice->tax_amount, 2) }} {{ $company->currency }}</td>
                </tr>
                <tr class="total-row">
                    <td class="label">الإجمالي:</td>
                    <td class="value">{{ number_format((float) $invoice->total, 2) }} {{ $company->currency }}</td>
                </tr>
                @if ((float) $invoice->paid_amount > 0)
                    <tr>
                        <td class="label">المدفوع:</td>
                        <td class="value">-{{ number_format((float) $invoice->paid_amount, 2) }} {{ $company->currency }}</td>
                    </tr>
                    <tr class="total-row">
                        <td class="label">المتبقي:</td>
                        <td class="value">{{ number_format((float) $invoice->balance_due, 2) }} {{ $company->currency }}</td>
                    </tr>
                @endif
            </table>
        </div>

        @if ($invoice->notes)
            <div class="notes-section">
                <div class="notes-title">ملاحظات</div>
                <div class="notes-content">{{ $invoice->notes }}</div>
            </div>
        @endif

        <div class="footer">
            <p>شكراً لتعاملكم مع {{ $company->name }} | هذه الفاتورة تم إنشاؤها بواسطة نظام المحاسبة المتقدم</p>
            <p>الصفحة 1 من 1</p>
        </div>
    </div>

    <script>
    if (window.location.search.includes('print=1')) {
        window.print();
    }
    </script>
</body>
</html>
