<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير المصروفات - {{ $company->name }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Tajawal', sans-serif; }
        body { margin: 0; background: #f1f5f9; color: #0f172a; padding: 24px; }
        .sheet { max-width: 1180px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 20px; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12); }
        .header { display: flex; justify-content: space-between; align-items: start; gap: 20px; border-bottom: 2px solid #e5e7eb; padding-bottom: 20px; margin-bottom: 24px; }
        h1 { margin: 0 0 10px; font-size: 28px; }
        .muted { color: #64748b; font-size: 14px; line-height: 1.8; }
        .chips { margin-top: 14px; }
        .chip { display: inline-block; padding: 8px 12px; border-radius: 999px; background: #fef3c7; color: #92400e; margin-left: 8px; margin-bottom: 8px; font-size: 13px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat { border: 1px solid #e5e7eb; border-radius: 16px; padding: 16px; }
        .stat-label { color: #64748b; font-size: 14px; margin-bottom: 8px; }
        .stat-value { font-size: 24px; font-weight: 800; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #0f172a; color: #fff; padding: 12px; text-align: right; }
        tbody td { padding: 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .footer { margin-top: 18px; color: #64748b; font-size: 13px; text-align: center; }
        @media print {
            body { padding: 0; background: #fff; }
            .sheet { max-width: 100%; box-shadow: none; border-radius: 0; padding: 16px; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="header">
            <div>
                <h1>تقرير المصروفات</h1>
                <div class="muted">{{ $company->name }}</div>
                <div class="muted">عرض منظم للطباعة أو الحفظ بصيغة PDF من المتصفح.</div>
                @if ($filterSummary !== [])
                    <div class="chips">
                        @foreach ($filterSummary as $item)
                            <span class="chip">{{ $item }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="muted">تاريخ الإصدار: {{ now()->format('Y-m-d H:i') }}</div>
        </div>

        <div class="stats">
            <div class="stat"><div class="stat-label">عدد العمليات</div><div class="stat-value">{{ $summary['count'] }}</div></div>
            <div class="stat"><div class="stat-label">إجمالي المصروفات</div><div class="stat-value">{{ number_format((float) $summary['total'], 2) }} {{ $company->currency }}</div></div>
            <div class="stat"><div class="stat-label">إجمالي الضريبة</div><div class="stat-value">{{ number_format((float) $summary['tax'], 2) }} {{ $company->currency }}</div></div>
            <div class="stat"><div class="stat-label">متوسط العملية</div><div class="stat-value">{{ number_format((float) $summary['average'], 2) }} {{ $company->currency }}</div></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>رقم المصروف</th>
                    <th>اسم المصروف</th>
                    <th>التاريخ</th>
                    <th>حساب المصروف</th>
                    <th>حساب السداد</th>
                    <th>المرجع</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $expense)
                    <tr>
                        <td>{{ $expense->expense_number }}</td>
                        <td>{{ $expense->name }}</td>
                        <td>{{ optional($expense->expense_date)->format('Y-m-d') ?: $expense->expense_date }}</td>
                        <td>{{ $expense->expenseAccount?->code }} - {{ $expense->expenseAccount?->name_ar ?? $expense->expenseAccount?->name }}</td>
                        <td>{{ $expense->paymentAccount?->code }} - {{ $expense->paymentAccount?->name_ar ?? $expense->paymentAccount?->name }}</td>
                        <td>{{ $expense->reference ?: '—' }}</td>
                        <td>{{ number_format((float) $expense->total, 2) }} {{ $company->currency }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">لا توجد مصروفات مطابقة للمعايير المختارة.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">يمكن استخدام هذه الصفحة للطباعة أو الحفظ بصيغة PDF.</div>
    </div>

    @if ($printMode)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
