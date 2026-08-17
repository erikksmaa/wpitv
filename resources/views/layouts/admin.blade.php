<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Admin Dashboard - WPiTV' }}</title>

    <!-- Admin Assets (Bootstrap 5 + Mazer UI) -->
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @livewireStyles
</head>
<body>
    <div id="app">
        <!-- Sidebar -->
        <div id="sidebar">
            <div class="sidebar-wrapper">
                <div class="sidebar-header">
                    <a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-primary">
                        <i class="bi bi-tv-fill me-2"></i>WPiTV Admin
                    </a>
                </div>
                <div class="sidebar-menu">
                    <ul class="menu list-unstyled">
                        <li class="sidebar-title">Main Menu</li>
                        <li class="sidebar-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                            <a href="{{ route('admin.dashboard') }}" class="sidebar-link">
                                <i class="bi bi-grid-fill"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>

                        <li class="sidebar-title">Content Management</li>
                        <li class="sidebar-item">
                            <a href="#" class="sidebar-link text-muted">
                                <i class="bi bi-play-btn-fill"></i>
                                <span>Videos</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="#" class="sidebar-link text-muted">
                                <i class="bi bi-collection-play-fill"></i>
                                <span>Programs</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="#" class="sidebar-link text-muted">
                                <i class="bi bi-newspaper"></i>
                                <span>News</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="#" class="sidebar-link text-muted">
                                <i class="bi bi-images"></i>
                                <span>Banners</span>
                            </a>
                        </li>

                        <li class="sidebar-title">Submissions & TV Club</li>
                        <li class="sidebar-item">
                            <a href="#" class="sidebar-link text-muted">
                                <i class="bi bi-camera-reels-fill"></i>
                                <span>Coverage Requests</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="#" class="sidebar-link text-muted">
                                <i class="bi bi-people-fill"></i>
                                <span>TV Club</span>
                            </a>
                        </li>

                        <li class="sidebar-title">Administration</li>
                        <li class="sidebar-item">
                            <a href="#" class="sidebar-link text-muted">
                                <i class="bi bi-person-gear"></i>
                                <span>Users</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="#" class="sidebar-link text-muted">
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>Roles</span>
                            </a>
                        </li>

                        <li class="sidebar-title">System</li>
                        <li class="sidebar-item">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="sidebar-link text-danger border-0 bg-transparent w-100 text-start">
                                    <i class="bi bi-box-arrow-right text-danger"></i>
                                    <span>Logout</span>
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div id="main">
            <!-- Top Navbar Header -->
            <header class="mb-4 d-flex justify-content-between align-items-center bg-white p-3 rounded shadow-sm">
                <div class="d-flex align-items-center">
                    <h5 class="m-0 text-secondary fw-semibold">{{ $header ?? 'Dashboard' }}</h5>
                </div>
                <div class="user-menu d-flex align-items-center">
                    <div class="user-name text-end me-3">
                        <h6 class="mb-0 text-gray-600 font-bold">{{ Auth::user()->name ?? 'User' }}</h6>
                        <p class="mb-0 text-sm text-muted">{{ Auth::user()->role->name ?? 'No Role' }}</p>
                    </div>
                    <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; font-weight: bold;">
                        {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-heading">
                {{ $slot }}
            </div>

            <!-- Footer -->
            <footer class="mt-5 pt-3 border-top text-muted text-center text-sm">
                <p>&copy; {{ date('Y') }} WPiTV Admin Infrastructure - STMIK Widya Pratama Pekalongan</p>
            </footer>
        </div>
    </div>

    @livewireScripts
</body>
</html>
