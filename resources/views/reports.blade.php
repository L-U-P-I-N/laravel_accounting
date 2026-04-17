@extends('layouts.app')

@section('title', 'التقارير')

@push('styles')
<style>
    .reports-shell {
        --reports-surface: #ffffff;
        --reports-surface-glass: rgba(255, 255, 255, 0.85);
        --reports-surface-soft: #f8fafc;
        --reports-border: #e2e8f0;
        --reports-text: #0f172a;
        --reports-muted: #64748b;
        --reports-primary: #4f46e5;
        --reports-primary-dark: #3730a3;
        --reports-primary-soft: #e0e7ff;
        --reports-star: #eab308;
        --reports-star-soft: #fef9c3;
        --reports-shadow: 0 12px 32px rgba(15, 23, 42, 0.06);
        --reports-shadow-hover: 0 20px 48px rgba(79, 70, 229, 0.15);
    }

    .reports-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--reports-border);
    }

    .reports-title {
        margin: 0;
        color: var(--reports-text);
        font-size: 1.85rem;
        font-weight: 900;
        letter-spacing: -0.02em;
        background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .reports-subtitle {
        margin: 0.35rem 0 0;
        color: var(--reports-muted);
        font-size: 0.95rem;
        font-weight: 500;
    }

    .reports-print-link {
        border-radius: 999px;
        padding: 0.65rem 1.25rem;
        font-size: 0.9rem;
        font-weight: 700;
        background: var(--reports-surface);
        border: 1px solid var(--reports-border);
        color: var(--reports-text);
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .reports-print-link:hover {
        background: var(--reports-primary-soft);
        color: var(--reports-primary-dark);
        border-color: var(--reports-primary-soft);
        transform: translateY(-2px);
    }

    .reports-section-bar {
        display: flex;
        gap: 0.85rem;
        margin-bottom: 1.5rem;
        overflow-x: auto;
        overflow-y: hidden;
        padding: 0.5rem 0.5rem 1.5rem 0.5rem;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
        scroll-behavior: smooth;
    }

    .reports-section-bar::-webkit-scrollbar {
        height: 6px;
    }

    .reports-section-bar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }

    .reports-section-btn {
        background: var(--reports-surface-glass);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.6);
        border-radius: 20px;
        padding: 1rem 1.25rem;
        min-width: 160px;
        min-height: 105px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        gap: 0.65rem;
        color: var(--reports-text);
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.03), inset 0 0 0 1px rgba(255, 255, 255, 0.8);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }

    .reports-section-btn:hover {
        transform: translateY(-4px) scale(1.02);
        border-color: var(--reports-primary-soft);
        box-shadow: 0 12px 25px rgba(15, 23, 42, 0.06), inset 0 0 0 1px rgba(255, 255, 255, 0.8);
        background: #ffffff;
    }

    .reports-section-btn.active {
        background: linear-gradient(135deg, var(--reports-primary) 0%, #6366f1 100%);
        border-color: transparent;
        color: #fff;
        box-shadow: var(--reports-shadow-hover);
    }

    .reports-section-top {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
    }

    .reports-section-icon {
        width: 2.6rem;
        height: 2.6rem;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--reports-primary-soft) 0%, #f1f5f9 100%);
        color: var(--reports-primary);
        font-size: 1.15rem;
        transition: all 0.3s ease;
    }

    .reports-section-btn:hover .reports-section-icon {
        transform: scale(1.1);
        color: var(--reports-primary-dark);
    }

    .reports-section-btn.active .reports-section-icon {
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
        backdrop-filter: blur(4px);
    }

    .reports-section-title {
        color: inherit;
        font-size: 0.95rem;
        font-weight: 800;
        line-height: 1.4;
        margin: 0;
        text-align: center;
        transition: color 0.3s ease;
    }

    .reports-catalog {
        background: var(--reports-surface-glass);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: 24px;
        box-shadow: var(--reports-shadow);
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .reports-catalog-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .reports-catalog-title {
        margin: 0;
        color: var(--reports-text);
        font-size: 1.25rem;
        font-weight: 900;
    }

    .reports-catalog-note {
        margin: 0.25rem 0 0;
        color: var(--reports-muted);
        font-size: 0.9rem;
    }

    .reports-catalog-count {
        border: 1px solid var(--reports-primary-soft);
        background: var(--reports-primary-soft);
        color: var(--reports-primary-dark);
        border-radius: 999px;
        padding: 0.45rem 1rem;
        font-size: 0.85rem;
        font-weight: 800;
        box-shadow: 0 2px 8px rgba(79, 70, 229, 0.1);
    }

    .reports-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1.25rem;
    }

    .reports-card {
        background: #ffffff;
        border: 1px solid var(--reports-border);
        border-radius: 20px;
        padding: 1.25rem;
        min-height: 185px;
        display: flex;
        flex-direction: column;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .reports-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--reports-primary), #3b82f6);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .reports-grid.sales-rhythm {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1.25rem;
        grid-auto-rows: 1fr;
    }

    .reports-grid.sales-rhythm .reports-card {
        min-height: 185px;
        height: 100%;
        padding: 1.25rem;
        border-radius: 20px;
        justify-content: space-between;
    }

    .reports-card:hover {
        transform: translateY(-6px);
        border-color: transparent;
        box-shadow: var(--reports-shadow-hover);
    }
    
    .reports-card:hover::before {
        opacity: 1;
    }

    .reports-card.active {
        border-color: var(--reports-primary);
        box-shadow: 0 0 0 1px var(--reports-primary), var(--reports-shadow-hover);
    }

    .reports-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.6rem;
        margin-bottom: 0.75rem;
    }

    .reports-card-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--reports-primary-soft) 0%, #eef2ff 100%);
        color: var(--reports-primary);
        font-size: 1.1rem;
        transition: transform 0.3s ease;
    }

    .reports-card:hover .reports-card-icon {
        transform: scale(1.1) rotate(-5deg);
    }

    .reports-favorite-btn {
        width: 2rem;
        height: 2rem;
        border-radius: 999px;
        border: 1px solid var(--reports-border);
        background: #fff;
        color: #94a3b8;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }

    .reports-favorite-btn:hover {
        background: var(--reports-surface-soft);
        transform: scale(1.1);
    }

    .reports-favorite-btn.is-favorite {
        color: var(--reports-star);
        background: var(--reports-star-soft);
        border-color: rgba(234, 179, 8, 0.3);
    }

    .reports-card-title {
        margin: 0 0 0.4rem;
        color: var(--reports-text);
        font-size: 1.05rem;
        font-weight: 800;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .reports-card-description {
        margin: 0 0 0.8rem;
        color: var(--reports-muted);
        font-size: 0.85rem;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .reports-grid.sales-rhythm .reports-card > div:nth-child(2) {
        text-align: center;
    }

    .reports-grid.sales-rhythm .reports-card-title {
        font-size: 1.05rem;
        line-height: 1.4;
        min-height: 1.4em;
        text-align: center;
    }

    .reports-grid.sales-rhythm .reports-card-description {
        -webkit-line-clamp: 2;
        min-height: 3em;
        max-width: 95%;
        margin-inline: auto;
        text-align: center;
    }

    .reports-card-footer {
        margin-top: auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .reports-card-time {
        color: var(--reports-muted);
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        font-weight: 500;
    }

    .reports-grid.sales-rhythm .reports-card-footer {
        justify-content: center;
        gap: 0.75rem;
    }

    .reports-grid.sales-rhythm .reports-card-time {
        width: 100%;
        justify-content: center;
    }

    .reports-run-btn {
        border: none;
        border-radius: 999px;
        padding: 0.5rem 1rem;
        min-width: 100px;
        background: linear-gradient(135deg, var(--reports-primary) 0%, var(--reports-primary-dark) 100%);
        color: #fff;
        font-size: 0.8rem;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        transition: all 0.25s ease;
        cursor: pointer;
    }

    .reports-run-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
        background: linear-gradient(135deg, #6366f1 0%, var(--reports-primary) 100%);
    }

    .reports-empty-list {
        display: none;
        border: 2px dashed var(--reports-border);
        background: rgba(248, 250, 252, 0.5);
        border-radius: 20px;
        padding: 3rem 1.5rem;
        text-align: center;
        color: var(--reports-muted);
        font-size: 0.95rem;
        font-weight: 600;
    }

    .reports-empty-list.is-visible {
        display: block;
    }

    .reports-results {
        display: none;
        background: var(--reports-surface-glass);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: 24px;
        box-shadow: var(--reports-shadow);
        padding: 1.5rem;
        animation: fadeIn 0.4s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .reports-results.is-visible {
        display: block;
    }

    .reports-results-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--reports-border);
        padding-bottom: 1.25rem;
    }

    .reports-result-title {
        margin: 0 0 0.3rem;
        color: var(--reports-text);
        font-size: 1.35rem;
        font-weight: 900;
        letter-spacing: -0.01em;
    }

    .reports-result-description,
    .reports-result-range,
    .reports-result-insight {
        margin: 0 0 0.35rem;
        color: var(--reports-muted);
        font-size: 0.9rem;
        line-height: 1.55;
    }

    .reports-result-toolbar {
        display: flex;
        align-items: end;
        gap: 0.8rem;
        flex-wrap: wrap;
        background: #f8fafc;
        padding: 1rem;
        border-radius: 16px;
        border: 1px solid var(--reports-border);
    }

    .reports-toolbar-group {
        min-width: 150px;
    }

    .reports-toolbar-group label {
        display: block;
        margin-bottom: 0.4rem;
        color: var(--reports-text);
        font-size: 0.85rem;
        font-weight: 700;
    }

    .reports-refresh-btn {
        border-radius: 12px;
        padding: 0.65rem 1rem;
        font-size: 0.85rem;
        font-weight: 800;
        transition: all 0.2s ease;
    }

    .reports-loading {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--reports-primary);
        font-size: 0.9rem;
        font-weight: 800;
    }

    .reports-highlights {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .reports-highlight {
        border: 1px solid var(--reports-border);
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        padding: 1.25rem;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.02);
        transition: transform 0.2s ease;
    }
    
    .reports-highlight:hover {
        transform: translateY(-2px);
    }

    .reports-highlight-value {
        color: var(--reports-text);
        font-size: 1.4rem;
        font-weight: 900;
        margin-bottom: 0.2rem;
        letter-spacing: -0.02em;
    }

    .reports-highlight-label {
        color: var(--reports-muted);
        font-size: 0.85rem;
        font-weight: 600;
    }

    .reports-unsupported {
        display: none;
        align-items: center;
        gap: 0.8rem;
        margin-bottom: 1.5rem;
        padding: 1rem 1.25rem;
        border-radius: 16px;
        border: 1px solid rgba(234, 179, 8, 0.3);
        background: linear-gradient(135deg, #fefce8 0%, #ffffff 100%);
        color: #854d0e;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .reports-unsupported.is-visible {
        display: flex;
    }

    .reports-chart-wrap {
        border: 1px solid var(--reports-border);
        border-radius: 20px;
        background: #ffffff;
        padding: 1.25rem;
        min-height: 320px;
        margin-bottom: 1.5rem;
        box-shadow: inset 0 2px 4px rgba(15, 23, 42, 0.01);
    }

    .reports-table-wrap {
        overflow: hidden;
        border: 1px solid var(--reports-border);
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.03);
    }

    .reports-table-wrap table {
        margin-bottom: 0;
        font-size: 0.9rem;
    }

    .reports-table-wrap thead th {
        background: #f8fafc;
        color: var(--reports-text);
        font-weight: 800;
        padding: 1rem;
        border-bottom: 2px solid var(--reports-border);
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.02em;
    }

    .reports-table-wrap tbody td {
        vertical-align: middle;
        color: var(--reports-text);
        padding: 1rem;
        border-bottom: 1px solid var(--reports-border);
    }
    
    .reports-table-wrap tbody tr:last-child td {
        border-bottom: none;
    }

    .reports-table-wrap tbody tr:hover {
        background-color: #f8fafc;
    }

    .reports-row-meta {
        color: var(--reports-muted);
        font-size: 0.8rem;
        margin-top: 0.2rem;
    }

    .reports-hidden {
        display: none !important;
    }

    @media (max-width: 1399.98px) {
        .reports-grid,
        .reports-grid.sales-rhythm {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 991.98px) {
        .reports-grid,
        .reports-grid.sales-rhythm,
        .reports-highlights {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .reports-grid,
        .reports-grid.sales-rhythm,
        .reports-highlights {
            grid-template-columns: 1fr;
        }

        .reports-section-btn {
            min-width: 140px;
            min-height: 90px;
        }

        .reports-results-head,
        .reports-catalog-head,
        .reports-header {
            flex-direction: column;
            align-items: stretch;
        }

        .reports-result-toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .reports-toolbar-group {
            min-width: 100%;
        }
    }
</style>
@endpush

@section('content')
@php
    $reportCatalogJson = collect($reportCatalog)->map(function ($report, $key) {
        return array_merge($report, ['key' => $key]);
    })->values();
    $orderedSectionKeys = ['favorites', 'sales', 'inventory', 'taxes', 'warehouse', 'finance'];
    $orderedSections = collect($orderedSectionKeys)
        ->mapWithKeys(fn ($key) => isset($sections[$key]) ? [$key => $sections[$key]] : [])
        ->all();
    $defaultSection = request()->filled('section') ? $initialSection : 'favorites';
    $defaultReport = request()->filled('report') ? $initialReportKey : 'sales_by_location';
@endphp

<div class="reports-shell"
     id="reportsApp"
     data-initial-section="{{ $defaultSection }}"
    data-initial-report="{{ $defaultReport }}">

    <script type="application/json" id="reportsCatalogData">@json($reportCatalogJson, JSON_UNESCAPED_UNICODE)</script>

    <div class="reports-header">
        <div>
            <h1 class="reports-title">التقارير</h1>
            <p class="reports-subtitle">اختيار أسرع للتقارير بتصميم أخف وأقرب للوحات الأنظمة الحديثة.</p>
        </div>
    </div>

    <section class="reports-section-bar" id="reportsSectionTabs">
        @foreach ($orderedSections as $key => $section)
            <button type="button"
                    class="reports-section-btn {{ $defaultSection === $key ? 'active' : '' }}"
                    data-section-tab
                    data-section="{{ $key }}">
                <div class="reports-section-top">
                    <span class="reports-section-icon"><i class="fas {{ $section['icon'] }}"></i></span>
                </div>
                <h2 class="reports-section-title">{{ $section['label'] }}</h2>
            </button>
        @endforeach
    </section>

    <section class="reports-catalog">
        <div class="reports-catalog-head">
            <div>
                <h2 class="reports-catalog-title" id="reportsCardsTitle">تقارير المبيعات</h2>
                <p class="reports-catalog-note" id="reportsCardsNote">اضغط على بطاقة التقرير لعرض النتائج مباشرة.</p>
            </div>
            <div class="reports-catalog-count" id="reportsVisibleCount">0 تقرير</div>
        </div>

        <div class="reports-empty-list" id="reportsEmptyList">
            لا توجد تقارير ضمن هذا القسم حالياً.
        </div>

        <div class="reports-grid" id="reportsCardGrid">
            @foreach ($reportCatalog as $key => $report)
                <article class="reports-card {{ $defaultReport === $key ? 'active' : '' }}"
                         data-report-card
                         data-section="{{ $report['section'] }}"
                         data-report-key="{{ $key }}">
                    <div class="reports-card-top">
                        <span class="reports-card-icon"><i class="fas {{ $report['icon'] }}"></i></span>
                        <button type="button" class="reports-favorite-btn" data-favorite-toggle data-report-key="{{ $key }}" aria-label="إضافة للمفضلة">
                            <i class="fa-star fa-regular"></i>
                        </button>
                    </div>

                    <div>
                        <h3 class="reports-card-title">{{ $report['title'] }}</h3>
                        <p class="reports-card-description">{{ $report['description'] }}</p>
                    </div>

                    <div class="reports-card-footer">
                        <span class="reports-card-time">
                            <i class="fas fa-clock"></i>
                            <span data-last-opened="{{ $key }}">لم يتم فتحه بعد</span>
                        </span>
                        <button type="button" class="reports-run-btn" data-show-report data-report-key="{{ $key }}">
                            عرض التقرير
                        </button>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

</div>
@endsection

@push('scripts')
<script>
    (() => {
        const app = document.getElementById('reportsApp');

        if (!app) {
            return;
        }

        const storageKeys = {
            favorites: 'reports-favorites',
            lastOpened: 'reports-last-opened',
        };

        const catalogDataElement = document.getElementById('reportsCatalogData');
        const catalog = JSON.parse(catalogDataElement?.textContent || '[]');
        const catalogByKey = Object.fromEntries(catalog.map((report) => [report.key, report]));
        const state = {
            section: app.dataset.initialSection || 'sales',
            activeReport: null,
            favorites: readStorageJson(storageKeys.favorites, []),
            lastOpened: readStorageJson(storageKeys.lastOpened, {}),
            chart: null,
        };

        const sectionTabs = Array.from(app.querySelectorAll('[data-section-tab]'));
        const reportCards = Array.from(app.querySelectorAll('[data-report-card]'));
        const favoriteButtons = Array.from(app.querySelectorAll('[data-favorite-toggle]'));
        const showButtons = Array.from(app.querySelectorAll('[data-show-report]'));
        const cardsTitle = document.getElementById('reportsCardsTitle');
        const cardsNote = document.getElementById('reportsCardsNote');
        const reportsVisibleCount = document.getElementById('reportsVisibleCount');
        const reportsEmptyList = document.getElementById('reportsEmptyList');

        function readStorageJson(key, fallback) {
            try {
                const value = window.localStorage.getItem(key);
                return value ? JSON.parse(value) : fallback;
            } catch (error) {
                return fallback;
            }
        }

        function writeStorageJson(key, value) {
            window.localStorage.setItem(key, JSON.stringify(value));
        }

        function formatLastOpened(value) {
            if (!value) {
                return 'لم يتم فتحه بعد';
            }

            const date = new Date(value);

            if (Number.isNaN(date.getTime())) {
                return 'لم يتم فتحه بعد';
            }

            return date.toLocaleString('ar-EG', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            });
        }

        function updateFavoritesUi() {
            favoriteButtons.forEach((button) => {
                const key = button.dataset.reportKey;
                const favorite = state.favorites.includes(key);
                button.classList.toggle('is-favorite', favorite);
                const icon = button.querySelector('i');
                icon.className = favorite ? 'fa-star fa-solid' : 'fa-star fa-regular';
            });
        }

        function updateLastOpenedUi() {
            Object.entries(state.lastOpened).forEach(([key, value]) => {
                const node = app.querySelector(`[data-last-opened="${key}"]`);
                if (node) {
                    node.textContent = formatLastOpened(value);
                }
            });
        }

        function currentSectionTitle() {
            const activeTab = sectionTabs.find((tab) => tab.dataset.section === state.section);
            return activeTab ? activeTab.querySelector('.reports-section-title').textContent.trim() : 'القسم';
        }

        function visibleCards() {
            return reportCards.filter((card) => {
                const reportKey = card.dataset.reportKey;
                return state.section === 'favorites' ? state.favorites.includes(reportKey) : card.dataset.section === state.section;
            });
        }

        function updateCardsView() {
            const visible = visibleCards();
            cardsTitle.textContent = `تقارير ${currentSectionTitle()}`;
            cardsNote.textContent = state.section === 'favorites'
                ? 'التقارير التي اخترتها للوصول السريع.'
                : 'اختر التقرير المناسب ثم اضغط عرض التقرير.';
            reportsVisibleCount.textContent = `${visible.length} تقرير`;
            reportsEmptyList.classList.toggle('is-visible', visible.length === 0);
            const cardGrid = document.getElementById('reportsCardGrid');

            if (cardGrid) {
                cardGrid.classList.toggle('sales-rhythm', state.section === 'sales');
            }

            reportCards.forEach((card) => {
                const reportKey = card.dataset.reportKey;
                const matches = state.section === 'favorites' ? state.favorites.includes(reportKey) : card.dataset.section === state.section;
                card.classList.toggle('reports-hidden', !matches);
                card.classList.toggle('active', state.activeReport === reportKey);
            });
        }

        function setActiveSection(section) {
            state.section = section;
            sectionTabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.section === section));
            updateCardsView();
        }

        sectionTabs.forEach((tab) => {
            tab.addEventListener('click', () => setActiveSection(tab.dataset.section));
        });

        favoriteButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.dataset.reportKey;
                const exists = state.favorites.includes(key);
                state.favorites = exists ? state.favorites.filter((item) => item !== key) : [...state.favorites, key];
                writeStorageJson(storageKeys.favorites, state.favorites);
                updateFavoritesUi();
                updateCardsView();
            });
        });

        showButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const reportKey = button.dataset.reportKey;
                const report = catalogByKey[reportKey];

                if (!report) {
                    return;
                }

                state.lastOpened[reportKey] = new Date().toISOString();
                writeStorageJson(storageKeys.lastOpened, state.lastOpened);
                updateLastOpenedUi();
                window.location.href = `{{ url('/reports/view') }}/${reportKey}?section=${encodeURIComponent(report.section)}`;
            });
        });

        updateFavoritesUi();
        updateLastOpenedUi();
        setActiveSection(state.section);
    })();
</script>
@endpush
