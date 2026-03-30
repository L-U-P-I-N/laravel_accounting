<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $reportPayload['title'] }} - {{ $company->name }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Tajawal', sans-serif; }
        body { margin: 0; background: #eef2f7; color: #0f172a; padding: 24px; }
        .sheet { max-width: 1240px; margin: 0 auto; background: #fff; padding: 32px; border-radius: 20px; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.12); }
        .header { display: flex; justify-content: space-between; align-items: start; gap: 20px; margin-bottom: 24px; border-bottom: 2px solid #e5e7eb; padding-bottom: 20px; }
        .title { font-size: 28px; font-weight: 800; margin: 0 0 8px; }
        .muted { color: #64748b; font-size: 14px; line-height: 1.8; }
        .chips { margin: 16px 0 0; display: flex; flex-wrap: wrap; gap: 8px; }
        .chip { display: inline-block; padding: 8px 12px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 13px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat { border: 1px solid #e5e7eb; border-radius: 16px; padding: 16px; }
        .stat-label { color: #64748b; font-size: 14px; margin-bottom: 8px; }
        .stat-value { font-size: 24px; font-weight: 800; }
        table { width: 100%; border-collapse: collapse; }
        thead th { background: #0f172a; color: #fff; padding: 12px; text-align: right; font-size: 14px; }
        tbody td { padding: 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; font-size: 14px; }
        .number-cell { font-weight: 700; }
        .footer { margin-top: 20px; color: #64748b; font-size: 13px; text-align: center; }
        @media print {
            body { padding: 0; background: #fff; }
            .sheet { max-width: 100%; box-shadow: none; border-radius: 0; padding: 16px; }
        }
    </style>
</head>
<body>
    @php
        $defaultValueFormat = $reportPayload['value_format'] ?? 'currency';
        $formatMetric = static function ($value, string $format) use ($company): string {
            return match ($format) {
                'currency' => number_format((float) $value, 2) . ' ' . $company->currency,
                'number' => number_format((float) $value, 2),
                'date' => blank($value) ? '—' : \Illuminate\Support\Carbon::parse($value)->format('Y-m-d'),
                default => blank($value) ? '—' : (string) $value,
            };
        };
    @endphp
    <div class="sheet">
        <div class="header">
            <div>
                <h1 class="title">{{ $reportPayload['title'] }}</h1>
                <div class="muted">{{ $company->name }}</div>
                <div class="muted">{{ $reportPayload['description'] }}</div>
                <div class="chips">
                    <span class="chip">{{ $reportPayload['date_range_label'] }}</span>
                    <span class="chip">{{ $reportMeta['title'] }}</span>
                </div>
                <div class="muted" style="margin-top: 12px;">{{ $reportPayload['insight'] }}</div>
            </div>
            <div class="muted">تاريخ الإصدار: {{ now()->format('Y-m-d H:i') }}</div>
        </div>

        <div class="stats">
            @foreach ($reportPayload['highlights'] as $highlight)
                <div class="stat">
                    <div class="stat-label">{{ $highlight['label'] }}</div>
                    <div class="stat-value">{{ $formatMetric($highlight['value'], $highlight['format'] ?? $defaultValueFormat) }}</div>
                </div>
            @endforeach
        </div>

        <table>
            <thead>
                <tr>
                    @foreach ($reportPayload['columns'] as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($reportPayload['rows'] as $row)
                    <tr>
                        @foreach ($reportPayload['columns'] as $column)
                            @php
                                $columnKey = $column['key'];
                                $columnFormat = $column['format'] ?? ($columnKey === 'meta' ? 'text' : ($row['format'] ?? $defaultValueFormat));
                                $columnValue = $row[$columnKey] ?? '';
                            @endphp
                            <td class="{{ in_array($columnFormat, ['currency', 'number'], true) ? 'number-cell' : '' }}">
                                {{ $formatMetric($columnValue, $columnFormat) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($reportPayload['columns']) }}">{{ $reportPayload['empty_message'] }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">هذه النسخة المطبوعة مبنية من نفس بيانات التقرير الظاهرة على الشاشة.</div>
    </div>

    @if ($printMode ?? false)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
