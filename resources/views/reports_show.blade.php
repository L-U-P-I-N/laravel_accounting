@extends('layouts.app')

@section('title', $reportPayload['title'])

@push('styles')
<style>
    .report-view {
        --report-surface: #ffffff;
        --report-soft: #f7faff;
        --report-border: #dbe7f5;
        --report-text: #243b53;
        --report-muted: #6c8199;
        --report-primary: #2563eb;
        --report-primary-dark: #1d4ed8;
        --report-primary-soft: #dbeafe;
        --report-shadow: 0 14px 32px rgba(37, 99, 235, 0.08);
    }

    .report-view-shell,
    .report-view-panel,
    .report-view-highlight,
    .report-view-chart,
    .report-view-warning {
        background: var(--report-surface);
        border: 1px solid var(--report-border);
        border-radius: 20px;
        box-shadow: var(--report-shadow);
    }

    .report-view-shell {
        padding: 1.1rem;
        margin-bottom: 1rem;
    }

    .report-view-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .report-view-breadcrumb {
        color: var(--report-muted);
        font-size: 0.84rem;
        margin-bottom: 0.35rem;
    }

    .report-view-title {
        color: var(--report-text);
        font-size: 1.35rem;
        font-weight: 800;
        margin-bottom: 0.3rem;
    }

    .report-view-description,
    .report-view-range,
    .report-view-insight {
        color: var(--report-muted);
        font-size: 0.9rem;
        line-height: 1.7;
        margin-bottom: 0.3rem;
    }

    .report-view-actions {
        display: flex;
        gap: 0.65rem;
        flex-wrap: wrap;
    }

    .report-view-actions .btn {
        border-radius: 12px;
        font-weight: 700;
    }

    .report-view-panel {
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .report-view-form {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.85rem;
        align-items: end;
    }

    .report-view-form label {
        display: block;
        margin-bottom: 0.35rem;
        color: var(--report-text);
        font-size: 0.8rem;
        font-weight: 700;
    }

    .report-view-highlights {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.8rem;
        margin-bottom: 1rem;
    }

    .report-view-highlight {
        padding: 0.9rem;
    }

    .report-view-highlight-value {
        color: var(--report-text);
        font-size: 1.1rem;
        font-weight: 800;
        margin-bottom: 0.2rem;
    }

    .report-view-highlight-label {
        color: var(--report-muted);
        font-size: 0.8rem;
        font-weight: 600;
    }

    .report-view-warning {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.9rem 1rem;
        margin-bottom: 1rem;
        background: linear-gradient(135deg, #fff6e9 0%, #fff 100%);
        border-style: dashed;
        color: #805c16;
    }

    .report-view-chart {
        padding: 0.9rem;
        margin-bottom: 1rem;
        min-height: 320px;
    }

    .report-view-table {
        overflow: hidden;
        border: 1px solid var(--report-border);
        border-radius: 18px;
    }

    .report-view-table table {
        margin-bottom: 0;
    }

    .report-view-table thead th {
        background: #f2f7fe;
        color: var(--report-text);
        font-weight: 800;
        border-bottom: 1px solid var(--report-border);
    }

    .report-view-table tbody td {
        vertical-align: middle;
        color: var(--report-text);
    }

    .report-view-meta {
        color: var(--report-muted);
        font-size: 0.84rem;
    }

    @media (max-width: 991.98px) {
        .report-view-form,
        .report-view-highlights {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .report-view-form,
        .report-view-highlights {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
@php
    $formatMetric = static function ($value, string $format) use ($company): string {
        if ($format === 'text') {
            return (string) $value;
        }

        if ($format === 'date') {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '';
        }

        $formatted = number_format((float) $value, 2);

        if ($format === 'number') {
            return rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted . ' ' . $company->currency;
    };

    $defaultValueFormat = $reportPayload['value_format'] ?? 'currency';
@endphp
<div class="report-view">
    <section class="report-view-shell">
        <div class="report-view-head">
            <div>
                <div class="report-view-breadcrumb">التقارير / {{ $reportMeta['section'] === 'sales' ? 'المبيعات' : $reportMeta['title'] }}</div>
                <h1 class="report-view-title">{{ $reportPayload['title'] }}</h1>
                <p class="report-view-description">{{ $reportPayload['description'] }}</p>
                <div class="report-view-range">{{ $reportPayload['date_range_label'] }}</div>
                <div class="report-view-insight">{{ $reportPayload['insight'] }}</div>
            </div>
            <div class="report-view-actions">
                <a href="{{ route('reports', ['section' => $reportMeta['section'], 'report' => $reportKey]) }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-right ms-1"></i> العودة للتقارير
                </a>
                <a href="{{ route('reports.show', array_filter(['report' => $reportKey, 'period' => $selectedPeriod, 'date_from' => $selectedPeriod === 'custom' ? $dateFrom->format('Y-m-d') : null, 'date_to' => $selectedPeriod === 'custom' ? $dateTo->format('Y-m-d') : null, 'print' => 1])) }}" class="btn btn-outline-primary" target="_blank" rel="noopener">
                    <i class="fas fa-print ms-1"></i> طباعة
                </a>
            </div>
        </div>
    </section>

    <section class="report-view-panel">
        <form method="GET" action="{{ route('reports.show', ['report' => $reportKey]) }}" class="report-view-form">
            <div>
                <label for="period">الفترة</label>
                <select class="form-select" id="period" name="period" onchange="this.form.submit()">
                    @foreach ($periodOptions as $value => $label)
                        <option value="{{ $value }}" {{ $selectedPeriod === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="date_from">من تاريخ</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="{{ $dateFrom->format('Y-m-d') }}" {{ $selectedPeriod !== 'custom' ? 'disabled' : '' }}>
            </div>
            <div>
                <label for="date_to">إلى تاريخ</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="{{ $dateTo->format('Y-m-d') }}" {{ $selectedPeriod !== 'custom' ? 'disabled' : '' }}>
            </div>
            <div>
                <button type="submit" class="btn btn-primary w-100">تحديث التقرير</button>
            </div>
        </form>
    </section>

    <section class="report-view-highlights">
        @foreach ($reportPayload['highlights'] as $highlight)
            <article class="report-view-highlight">
                <div class="report-view-highlight-value">{{ $formatMetric($highlight['value'], $highlight['format'] ?? $defaultValueFormat) }}</div>
                <div class="report-view-highlight-label">{{ $highlight['label'] }}</div>
            </article>
        @endforeach
    </section>

    @if (($reportPayload['supported'] ?? true) === false)
        <section class="report-view-warning">
            <i class="fas fa-circle-info"></i>
            <div>{{ $reportPayload['empty_message'] }}</div>
        </section>
    @endif

    <section class="report-view-chart">
        <canvas id="reportShowChart" height="240"></canvas>
    </section>

    <section class="report-view-table">
        <table class="table table-hover align-middle">
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
                            <td class="{{ in_array($columnFormat, ['currency', 'number'], true) ? 'fw-bold' : '' }}">
                                @if ($columnKey === 'meta')
                                    <span class="report-view-meta">{{ $columnValue }}</span>
                                @else
                                    {{ $formatMetric($columnValue, $columnFormat) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($reportPayload['columns']) }}" class="text-center text-muted py-4">{{ $reportPayload['empty_message'] }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const canvas = document.getElementById('reportShowChart');

        if (!canvas) {
            return;
        }

        new Chart(canvas, {
            type: @json($reportPayload['chart']['type'] ?? 'bar'),
            data: {
                labels: @json($reportPayload['chart']['labels'] ?? []),
                datasets: [{
                    data: @json($reportPayload['chart']['values'] ?? []),
                    borderRadius: 10,
                    backgroundColor: ['#2563eb', '#1d4ed8', '#3b82f6', '#60a5fa', '#93c5fd', '#d89a2b', '#64748b', '#dc2626'],
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => Number(value).toLocaleString('en-US'),
                        },
                    },
                },
            },
        });
    })();
</script>
@endpush
