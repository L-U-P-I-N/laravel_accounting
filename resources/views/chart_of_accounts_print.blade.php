<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة شجرة الحسابات</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            margin: 24px;
            color: #1f2937;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 24px;
        }

        p {
            margin: 0 0 6px;
            color: #4b5563;
        }

        .meta {
            margin: 0 0 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 13px;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
            text-align: right;
            vertical-align: top;
        }

        th {
            background: #eff6ff;
            font-weight: 700;
        }

        .empty {
            padding: 18px;
            border: 1px dashed #cbd5e1;
            margin-top: 18px;
            color: #64748b;
        }

        @media print {
            body {
                margin: 12px;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <h1>شجرة الحسابات</h1>
    <div class="meta">
        <p>الشركة: {{ $company->name }}</p>
        <p>تاريخ الطباعة: {{ now()->format('Y-m-d H:i') }}</p>
        <p>الحسابات الديناميكية: {{ $includeDynamicAccounts ? 'مشمولة' : 'مخفية' }}</p>
    </div>

    @if ($rows->isEmpty())
        <div class="empty">لا توجد حسابات مطابقة للخيارات الحالية.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th>الرمز</th>
                    <th>اسم الحساب</th>
                    <th>النوع</th>
                    <th>الوصف</th>
                    <th>رقم تعريفي للحساب الأصلي</th>
                    <th>يمكن الدفع والتحصيل بهذا الحساب</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['code'] }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['display_account_type'] }}</td>
                        <td>{{ $row['description'] ?: '-' }}</td>
                        <td>{{ $row['parent_label'] ?: '-' }}</td>
                        <td>{{ $row['allows_direct_transactions'] ? 'نعم' : 'لا' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
