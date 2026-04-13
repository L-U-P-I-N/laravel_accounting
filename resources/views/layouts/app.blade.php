<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'المحاسب الذكي') | SmartBooks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
    @php($currentUser = request()->user())
    @php($currentCompany = auth()->user()?->company ?? \App\Models\Company::first())
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            @if($currentCompany?->logo_url)
                <img src="{{ $currentCompany->logoUrl() }}" alt="{{ $currentCompany->name }}" style="height: 40px; max-width: 180px; object-fit: contain;">
            @else
                <i class="fas fa-chart-line"></i>
                <span class="sidebar-brand">المحاسبة المتقدمة</span>
            @endif
        </div>

        <nav class="sidebar-nav">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt"></i>
                <span>لوحة التحكم</span>
            </a>
            @if ($currentUser?->hasPermission('manage_users'))
                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <i class="fas fa-user-shield"></i>
                    <span>إدارة المستخدمين</span>
                </a>
            @endif
            @if ($currentUser?->hasPermission('manage_employees'))
                <a href="{{ route('branches.index') }}" class="nav-link {{ request()->routeIs('branches.*') ? 'active' : '' }}">
                    <i class="fas fa-code-branch"></i>
                    <span>الفروع</span>
                </a>
                <a href="{{ route('employees.index') }}" class="nav-link {{ request()->routeIs('employees.*') || request()->routeIs('hr') ? 'active' : '' }}">
                    <i class="fas fa-user-tie"></i>
                    <span>الموظفون</span>
                </a>
            @endif
            @if ($currentUser?->hasPermission('manage_invoices'))
                <a href="{{ route('invoices') }}" class="nav-link {{ request()->routeIs('invoices*') ? 'active' : '' }}">
                    <i class="fas fa-file-invoice"></i>
                    <span>المبيعات</span>
                </a>
                <a href="{{ route('sales_channels.index') }}" class="nav-link {{ request()->routeIs('sales_channels.*') ? 'active' : '' }}">
                    <i class="fas fa-share-nodes"></i>
                    <span>قنوات البيع</span>
                </a>
            @endif
            @if ($currentUser?->hasPermission('manage_purchases'))
                <a href="{{ route('purchases') }}" class="nav-link {{ request()->routeIs('purchases') ? 'active' : '' }}">
                    <i class="fas fa-shopping-cart"></i>
                    <span>المشتريات</span>
                </a>
            @endif
            @if ($currentUser?->hasPermission('manage_customers'))
                <a href="{{ route('customers') }}" class="nav-link {{ request()->routeIs('customers') ? 'active' : '' }}">
                    <i class="fas fa-users"></i>
                    <span>العملاء</span>
                </a>
            @endif
            @if ($currentUser?->hasPermission('manage_suppliers'))
                <a href="{{ route('suppliers') }}" class="nav-link {{ request()->routeIs('suppliers') ? 'active' : '' }}">
                    <i class="fas fa-truck"></i>
                    <span>الموردين</span>
                </a>
            @endif
            @if ($currentUser?->hasPermission('manage_products'))
                <a href="{{ route('products') }}" class="nav-link {{ request()->routeIs('products') ? 'active' : '' }}">
                    <i class="fas fa-box"></i>
                    <span>المنتجات</span>
                </a>
            @endif
            @if ($currentUser?->hasPermission('manage_accounts'))
                <a href="{{ route('chart_of_accounts') }}" class="nav-link {{ request()->routeIs('chart_of_accounts*') ? 'active' : '' }}">
                    <i class="fas fa-sitemap"></i>
                    <span>شجرة الحسابات</span>
                </a>
                <a href="{{ route('expenses') }}" class="nav-link {{ request()->routeIs('expenses*') ? 'active' : '' }}">
                    <i class="fas fa-receipt"></i>
                    <span>المصروفات</span>
                </a>
            @endif
            @if ($currentUser?->hasPermission('manage_journal_entries'))
                <a href="{{ route('journal_entries') }}" class="nav-link {{ request()->routeIs('journal_entries*') ? 'active' : '' }}">
                    <i class="fas fa-book"></i>
                    <span>القيود المحاسبية</span>
                </a>
            @endif
            @if ($currentUser?->hasPermission('view_reports'))
                <a href="{{ route('reports') }}" class="nav-link {{ request()->routeIs('reports') ? 'active' : '' }}">
                    <i class="fas fa-chart-bar"></i>
                    <span>التقارير</span>
                </a>
            @endif
            @if ($currentUser?->hasPermission('manage_settings'))
                <a href="{{ route('settings') }}" class="nav-link {{ request()->routeIs('settings') ? 'active' : '' }}">
                    <i class="fas fa-cog"></i>
                    <span>الإعدادات</span>
                </a>
            @endif
        </nav>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar(false)"></div>

    <div class="main-content" id="mainContent">
        <nav class="top-navbar">
            <button type="button" class="btn btn-link sidebar-toggle" onclick="toggleSidebar()" aria-label="فتح القائمة الجانبية">
                <i class="fas fa-bars"></i>
            </button>
            
            @if($currentCompany?->logo_url)
                {{-- Mobile logo only shown on small screens --}}
                <div class="d-lg-none ms-3">
                    <img src="{{ $currentCompany->logoUrl() }}" alt="{{ $currentCompany->name }}" style="height: 32px; max-width: 120px; object-fit: contain;">
                </div>
            @endif

            <div class="top-nav-right">
                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i>
                        {{ $currentUser?->full_name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <span class="dropdown-item-text text-muted small">{{ $currentUser?->role_label }}</span>
                        </li>
                        @if ($currentUser?->hasPermission('manage_users'))
                            <li>
                                <a class="dropdown-item" href="{{ route('users.index') }}">
                                    <i class="fas fa-user-shield me-2"></i>إدارة المستخدمين
                                </a>
                            </li>
                        @endif
                        @if ($currentUser?->hasPermission('manage_settings'))
                            <li>
                                <a class="dropdown-item" href="{{ route('settings') }}">
                                    <i class="fas fa-cog me-2"></i>الإعدادات
                                </a>
                            </li>
                        @endif
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt me-2"></i>تسجيل خروج
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 mt-3">
            @foreach (['success', 'error', 'warning'] as $flashType)
                @if (session()->has($flashType))
                    <div class="alert alert-{{ $flashType === 'error' ? 'danger' : $flashType }} alert-dismissible fade show" role="alert">
                        {{ session($flashType) }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
            @endforeach

            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
        </div>

        <div class="container-fluid px-4 py-3">
            @yield('content')
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
