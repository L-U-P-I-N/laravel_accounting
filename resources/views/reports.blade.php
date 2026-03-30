@extends('layouts.app')

@section('title', 'التقارير')

@push('styles')
<style>
    .reports-shell {
        --reports-surface: #ffffff;
        --reports-surface-soft: #f7fafc;
        --reports-border: #dce5ee;
        --reports-text: #243b53;
        --reports-muted: #74859a;
        --reports-primary: #2563eb;
        --reports-primary-dark: #1d4ed8;
        --reports-primary-soft: #dbeafe;
        --reports-star: #d89a2b;
        --reports-star-soft: #fff3da;
        --reports-shadow: 0 10px 28px rgba(31, 57, 88, 0.08);
    }

    .reports-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .reports-title {
        margin: 0;
        color: var(--reports-text);
        font-size: 1.5rem;
        font-weight: 800;
    }

    .reports-subtitle {
        margin: 0.15rem 0 0;
        color: var(--reports-muted);
        font-size: 0.88rem;
    }

    .reports-print-link {
        border-radius: 12px;
        padding: 0.58rem 0.9rem;
        font-size: 0.84rem;
        font-weight: 700;
    }

    .reports-section-bar {
        display: flex;
        gap: 0.65rem;
        margin-bottom: 1rem;
        overflow-x: auto;
        overflow-y: hidden;
        padding-bottom: 0.2rem;
        scrollbar-width: thin;
        scrollbar-color: #c7d5e2 transparent;
    }

    .reports-section-bar::-webkit-scrollbar {
        height: 6px;
    }

    .reports-section-bar::-webkit-scrollbar-thumb {
        background: #c7d5e2;
        border-radius: 999px;
    }

    .reports-section-btn {
        background: var(--reports-surface);
        border: 1px solid var(--reports-border);
        border-radius: 16px;
        padding: 0.75rem 0.85rem;
        min-width: 150px;
        min-height: 88px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 0.45rem;
        text-align: start;
        color: var(--reports-text);
        box-shadow: 0 4px 14px rgba(31, 57, 88, 0.04);
        transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
    }

    .reports-section-btn:hover {
        transform: translateY(-2px);
        border-color: rgba(37, 99, 235, 0.35);
        box-shadow: 0 10px 22px rgba(31, 57, 88, 0.08);
    }

    .reports-section-btn.active {
        background: linear-gradient(135deg, var(--reports-primary) 0%, var(--reports-primary-dark) 100%);
        border-color: transparent;
        color: #fff;
    }

    .reports-section-top {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .reports-section-icon {
        width: 2rem;
        height: 2rem;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--reports-primary-soft);
        color: var(--reports-primary-dark);
        font-size: 0.88rem;
    }

    .reports-section-btn.active .reports-section-icon {
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
    }

    .reports-section-title {
        color: var(--reports-text);
        font-size: 0.92rem;
        font-weight: 800;
        line-height: 1.35;
        margin: 0;
        text-align: center;
    }

    .reports-catalog {
        background: var(--reports-surface);
        border: 1px solid var(--reports-border);
        border-radius: 20px;
        box-shadow: var(--reports-shadow);
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .reports-catalog-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.9rem;
        flex-wrap: wrap;
    }

    .reports-catalog-title {
        margin: 0;
        color: var(--reports-text);
        font-size: 1rem;
        font-weight: 800;
    }

    .reports-catalog-note {
        margin: 0.15rem 0 0;
        color: var(--reports-muted);
        font-size: 0.84rem;
    }

    .reports-catalog-count {
        border: 1px solid var(--reports-border);
        background: var(--reports-surface-soft);
        color: var(--reports-muted);
        border-radius: 999px;
        padding: 0.35rem 0.7rem;
        font-size: 0.76rem;
        font-weight: 700;
    }

    .reports-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.65rem;
    }

    .reports-card {
        background: var(--reports-surface);
        border: 1px solid var(--reports-border);
        border-radius: 16px;
        padding: 0.78rem;
        min-height: 168px;
        display: flex;
        flex-direction: column;
        transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
    }

    .reports-grid.sales-rhythm {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.8rem;
        grid-auto-rows: 1fr;
    }

    .reports-grid.sales-rhythm .reports-card {
        min-height: 154px;
        height: 100%;
        padding: 0.72rem 0.78rem;
        border-radius: 14px;
        justify-content: space-between;
    }

    .reports-card:hover {
        transform: translateY(-2px);
        border-color: rgba(37, 99, 235, 0.35);
        box-shadow: 0 14px 24px rgba(31, 57, 88, 0.08);
    }

    .reports-card.active {
        border-color: rgba(37, 99, 235, 0.45);
        box-shadow: 0 16px 28px rgba(37, 99, 235, 0.1);
    }

    .reports-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.6rem;
        margin-bottom: 0.52rem;
    }

    .reports-card-icon {
        width: 2rem;
        height: 2rem;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--reports-primary-soft) 0%, #eef9fc 100%);
        color: var(--reports-primary-dark);
        font-size: 0.82rem;
    }

    .reports-favorite-btn {
        width: 1.8rem;
        height: 1.8rem;
        border-radius: 999px;
        border: 1px solid var(--reports-border);
        background: #fff;
        color: #96a5b4;
        font-size: 0.72rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.18s ease;
    }

    .reports-favorite-btn.is-favorite {
        color: var(--reports-star);
        background: var(--reports-star-soft);
        border-color: rgba(216, 154, 43, 0.35);
    }

    .reports-card-title {
        margin: 0 0 0.22rem;
        color: var(--reports-text);
        font-size: 0.88rem;
        font-weight: 800;
        line-height: 1.38;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .reports-card-description {
        margin: 0 0 0.55rem;
        color: var(--reports-muted);
        font-size: 0.76rem;
        line-height: 1.45;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .reports-grid.sales-rhythm .reports-card > div:nth-child(2) {
        text-align: center;
    }

    .reports-grid.sales-rhythm .reports-card-title {
        font-size: 0.9rem;
        line-height: 1.4;
        min-height: 1.4em;
        text-align: center;
    }

    .reports-grid.sales-rhythm .reports-card-description {
        -webkit-line-clamp: 2;
        min-height: 2.9em;
        max-width: 92%;
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
        font-size: 0.7rem;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .reports-grid.sales-rhythm .reports-card-footer {
        justify-content: center;
        gap: 0.45rem;
    }

    .reports-grid.sales-rhythm .reports-card-time {
        width: 100%;
        justify-content: center;
    }

    .reports-run-btn {
        border: none;
        border-radius: 11px;
        padding: 0.46rem 0.72rem;
        min-width: 92px;
        background: linear-gradient(135deg, var(--reports-primary) 0%, var(--reports-primary-dark) 100%);
        color: #fff;
        font-size: 0.74rem;
        font-weight: 700;
    }

    .reports-empty-list {
        display: none;
        border: 1px dashed var(--reports-border);
        background: var(--reports-surface-soft);
        border-radius: 16px;
        padding: 1.6rem 1rem;
        text-align: center;
        color: var(--reports-muted);
        font-size: 0.85rem;
    }

    .reports-empty-list.is-visible {
        display: block;
    }

    .reports-results {
        display: none;
        background: var(--reports-surface);
        border: 1px solid var(--reports-border);
        border-radius: 20px;
        box-shadow: var(--reports-shadow);
        padding: 1rem;
    }

    .reports-results.is-visible {
        display: block;
    }

    .reports-results-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.85rem;
        flex-wrap: wrap;
    }

    .reports-result-title {
        margin: 0 0 0.2rem;
        color: var(--reports-text);
        font-size: 1.02rem;
        font-weight: 800;
    }

    .reports-result-description,
    .reports-result-range,
    .reports-result-insight {
        margin: 0 0 0.25rem;
        color: var(--reports-muted);
        font-size: 0.84rem;
        line-height: 1.55;
    }

    .reports-result-toolbar {
        display: flex;
        align-items: end;
        gap: 0.6rem;
        flex-wrap: wrap;
    }

    .reports-toolbar-group {
        min-width: 145px;
    }

    .reports-toolbar-group label {
        display: block;
        margin-bottom: 0.3rem;
        color: var(--reports-text);
        font-size: 0.78rem;
        font-weight: 700;
    }

    .reports-refresh-btn {
        border-radius: 11px;
        padding: 0.56rem 0.85rem;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .reports-loading {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        color: var(--reports-muted);
        font-size: 0.8rem;
        font-weight: 700;
    }

    .reports-highlights {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.7rem;
        margin-bottom: 0.8rem;
    }

    .reports-highlight {
        border: 1px solid var(--reports-border);
        border-radius: 14px;
        background: linear-gradient(180deg, #fff 0%, #fafcfe 100%);
        padding: 0.8rem;
    }

    .reports-highlight-value {
        color: var(--reports-text);
        font-size: 1rem;
        font-weight: 800;
        margin-bottom: 0.12rem;
    }

    .reports-highlight-label {
        color: var(--reports-muted);
        font-size: 0.78rem;
        font-weight: 600;
    }

    .reports-unsupported {
        display: none;
        align-items: center;
        gap: 0.65rem;
        margin-bottom: 0.8rem;
        padding: 0.8rem;
        border-radius: 14px;
        border: 1px dashed rgba(216, 154, 43, 0.45);
        background: linear-gradient(135deg, #fff6e7 0%, #fff 100%);
        color: #7f6020;
        font-size: 0.82rem;
    }

    .reports-unsupported.is-visible {
        display: flex;
    }

    .reports-chart-wrap {
        border: 1px solid var(--reports-border);
        border-radius: 16px;
        background: linear-gradient(180deg, #fff 0%, #f9fbfd 100%);
        padding: 0.8rem;
        min-height: 270px;
        margin-bottom: 0.8rem;
    }

    .reports-table-wrap {
        overflow: hidden;
        border: 1px solid var(--reports-border);
        border-radius: 16px;
    }

    .reports-table-wrap table {
        margin-bottom: 0;
        font-size: 0.86rem;
    }

    .reports-table-wrap thead th {
        background: #f2f6fa;
        color: var(--reports-text);
        font-weight: 800;
        border-bottom: 1px solid var(--reports-border);
    }

    .reports-table-wrap tbody td {
        vertical-align: middle;
        color: var(--reports-text);
    }

    .reports-row-meta {
        color: var(--reports-muted);
        font-size: 0.78rem;
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
            min-width: 132px;
            min-height: 82px;
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
    $orderedSectionKeys = ['sales', 'favorites', 'inventory', 'taxes', 'warehouse', 'finance'];
    $orderedSections = collect($orderedSectionKeys)
        ->mapWithKeys(fn ($key) => isset($sections[$key]) ? [$key => $sections[$key]] : [])
        ->all();
    $defaultSection = request()->filled('section') ? $initialSection : 'sales';
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
        <a href="{{ route('reports', ['print' => 1]) }}" class="btn btn-outline-secondary reports-print-link" target="_blank" rel="noopener">
            <i class="fas fa-print me-1"></i> نسخة الطباعة
        </a>
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
