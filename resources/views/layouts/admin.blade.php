<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard Admin - WPiTV' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Mazer Theme Init (Prevent Flash) -->
    <script src="{{ asset('js/admin-theme-init.js') }}" data-navigate-once></script>

    <!-- Mazer Original Core Stylesheets -->
    <link rel="stylesheet" href="{{ asset('mazer/compiled/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('mazer/compiled/css/app-dark.css') }}">
    <link rel="stylesheet" href="{{ asset('mazer/compiled/css/iconly.css') }}">
    <link rel="stylesheet" href="{{ asset('mazer/extensions/bootstrap-icons/font/bootstrap-icons.css') }}">

    <!-- WPiTV visual override layer (loaded after Mazer) -->
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])

    @livewireStyles
</head>
<body>
    <div id="app">
        <!-- Mazer Original Sidebar -->
        <div id="sidebar" aria-label="Navigasi admin">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header position-relative">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="logo">
                            <a href="{{ route('admin.dashboard') }}">
                                <img src="{{ asset('logo-wpi.png') }}" class="brand-logo" alt="WPiTV Admin">
                            </a>
                        </div>
                        <div class="sidebar-toggler x">
                            <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle fs-4"></i></a>
                        </div>
                    </div>
                </div>
                <div class="sidebar-menu">
                    <ul class="menu">
                        <li class="sidebar-title">Menu</li>

                        <li class="sidebar-item {{ request()->routeIs('admin.dashboard', 'admin.index') ? 'active' : '' }}">
                            <a href="{{ route('admin.dashboard') }}" class="sidebar-link">
                                <i class="bi bi-grid-fill"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>

                        @include('components.admin.role-navigation')

                        @can('social.view')
                            <li class="sidebar-title">Media Sosial</li>
                            <li class="sidebar-item has-sub {{ request()->routeIs('admin.social.*') ? 'active' : '' }}">
                                <a href="#" class="sidebar-link"><i class="bi bi-share"></i><span>Media Sosial</span></a>
                                <ul class="submenu {{ request()->routeIs('admin.social.*') ? 'submenu-open' : '' }}">
                                    @foreach(['accounts'=>'Akun Media Sosial','posts'=>'Publikasi Konten','reports'=>'Laporan Bulanan'] as $key => $label)
                                        <li class="submenu-item {{ request()->routeIs('admin.social.'.$key) ? 'active' : '' }}"><a class="submenu-link" href="{{ route('admin.social.'.$key) }}">{{ $label }}</a></li>
                                    @endforeach
                                </ul>
                            </li>
                        @endcan

                        @can('equipment.inventory.view')
                            <li class="sidebar-title">Peralatan</li>
                            @php($inventoryActive = request()->routeIs('admin.inventory.*'))
                            <li class="sidebar-item has-sub {{ $inventoryActive ? 'active' : '' }}">
                                <a href="#" class="sidebar-link"><i class="bi bi-box-seam"></i><span>Inventaris</span></a>
                                <ul class="submenu {{ $inventoryActive ? 'submenu-open' : '' }}">
                                    @foreach(['categories'=>'Kategori','equipment'=>'Peralatan','activity'=>'Riwayat Aktivitas'] as $item => $label)
                                        @if($item !== 'activity' || auth()->user()->can('equipment.audit.view'))
                                            @php($current = request()->routeIs('admin.inventory.'.$item))
                                            <li class="submenu-item {{ $current ? 'active' : '' }}">
                                                <a href="{{ route('admin.inventory.'.$item) }}" class="submenu-link" @if($current) aria-current="page" @endif>{{ $label }}</a>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </li>
                        @endcan

                        @can('equipment.report.view')
                            <li class="sidebar-item {{ request()->routeIs('admin.equipment-reports.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.equipment-reports.index') }}" class="sidebar-link"><i class="bi bi-bar-chart"></i><span>Laporan Peralatan</span></a>
                            </li>
                        @endcan
                        @can('equipment.loan.view')
                            <li class="sidebar-item {{ request()->routeIs('admin.equipment-loans.*') ? 'active' : '' }}">
                                <a href="{{ route('admin.equipment-loans.index') }}" class="sidebar-link"><i class="bi bi-arrow-left-right"></i><span>Peminjaman Peralatan</span></a>
                            </li>
                        @endcan

                        <li class="sidebar-item">
                            <form method="POST" action="{{ route('logout') }}" id="logout-form" data-admin-confirm="logout">
                                @csrf
                                <button type="submit" class="sidebar-link text-danger border-0 bg-transparent w-100 text-start">
                                    <i class="bi bi-box-arrow-right text-danger"></i>
                                    <span>Keluar</span>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Mazer Main Content Container -->
        <div id="main" class="layout-navbar">
            <header class="admin-topbar" aria-label="Navigasi panel admin">
                <button type="button" class="burger-btn admin-menu-toggle d-xl-none" aria-controls="sidebar" aria-expanded="false" aria-label="Buka menu admin">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <a href="{{ route('admin.dashboard') }}" class="admin-topbar-brand" aria-label="Ke Dashboard WPiTV">
                    <img src="{{ asset('logo-wpi.png') }}" alt="" aria-hidden="true">
                    <span>WPiTV Admin</span>
                </a>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <a href="{{ route('public.home') }}" target="_blank" rel="noopener noreferrer" class="btn admin-topbar-btn" aria-label="Buka portal publik WPiTV">
                        <i class="bi bi-globe2" aria-hidden="true"></i><span class="d-none d-sm-inline">Lihat Situs</span>
                    </a>
                    <button type="button" class="btn admin-theme-toggle" data-admin-theme-toggle aria-label="Aktifkan mode gelap" aria-pressed="false">
                        <i class="bi bi-sun" data-admin-theme-icon aria-hidden="true"></i><span data-admin-theme-label class="d-none d-sm-inline">Mode terang</span>
                    </button>
                </div>
            </header>

            <div id="main-content">
                <div class="page-heading">
                    @isset($header)
                    <div class="page-title mb-3">
                        <div class="row">
                            <div class="col-12 col-md-6 order-md-1 order-last">
                                <h3>{{ $header }}</h3>
                                @isset($subtitle)
                                    <p class="text-subtitle text-muted">{{ $subtitle }}</p>
                                @endisset
                            </div>
                            <div class="col-12 col-md-6 order-md-2 order-first">
                                <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                                        <li class="breadcrumb-item active" aria-current="page">{{ $header }}</li>
                                    </ol>
                                </nav>
                            </div>
                        </div>
                    </div>
                    @endisset

                    <div class="page-content">
                        @if (session('success'))
                            <div class="alert alert-success d-none" data-admin-flash role="status">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if (session('error'))
                            <div class="alert alert-danger d-none" data-admin-flash role="alert">
                                {{ session('error') }}
                            </div>
                        @endif

                        {{ $slot }}
                    </div>
                </div>

                <footer class="border-top pt-3 mt-4">
                    <div class="footer clearfix mb-0 text-muted">
                        <div class="float-start">
                            <p class="mb-0">&copy; {{ date('Y') }} WPiTV Media Platform</p>
                        </div>
                        <div class="float-end">
                            <p class="mb-0">STMIK Widya Pratama Pekalongan</p>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </div>

    <!-- Mazer Original JavaScript -->
    <script src="{{ asset('mazer/extensions/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('mazer/compiled/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('mazer/compiled/js/app.js') }}"></script>
    @stack('scripts')
    @livewireScripts
</body>
</html>
