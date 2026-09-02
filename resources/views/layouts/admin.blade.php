<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dasbor Admin - WPiTV' }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Mazer Theme Init (Prevent Flash) -->
    <script src="{{ asset('mazer/static/js/initTheme.js') }}"></script>

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
                                <span>Dasbor</span>
                            </a>
                        </li>

                        @include('components.admin.role-navigation')

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
            <header class="admin-mobile-bar d-xl-none" aria-label="Navigasi admin seluler">
                <button type="button" class="burger-btn admin-menu-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="Buka menu admin">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <a href="{{ route('admin.dashboard') }}" class="admin-mobile-brand" aria-label="Ke Dasbor WPiTV">
                    <img src="{{ asset('logo-wpi.png') }}" alt="" aria-hidden="true">
                    <span>WPiTV Admin</span>
                </a>
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
                                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dasbor</a></li>
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
    <script src="{{ asset('mazer/static/js/components/dark.js') }}"></script>
    <script src="{{ asset('mazer/extensions/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('mazer/compiled/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('mazer/compiled/js/app.js') }}"></script>
    @stack('scripts')
    @livewireScripts
</body>
</html>
