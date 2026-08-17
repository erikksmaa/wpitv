<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'WPiTV') }}</title>

    <!-- Public Assets -->
    @vite(['resources/css/public.css', 'resources/js/public.js'])
    @livewireStyles
</head>
<body class="bg-gray-100 font-sans text-gray-900 antialiased min-h-screen flex flex-col justify-between">

    <!-- Header / Navbar Placeholder -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ url('/') }}" class="text-xl font-bold text-indigo-700 tracking-tight">
                    WPiTV <span class="text-xs font-semibold px-2 py-0.5 rounded bg-indigo-100 text-indigo-800">Media Platform</span>
                </a>
            </div>
            <nav class="flex items-center space-x-6 text-sm font-medium text-gray-600">
                <a href="{{ url('/') }}" class="hover:text-indigo-600">Beranda</a>
                <a href="#" class="hover:text-indigo-600">Program</a>
                <a href="#" class="hover:text-indigo-600">Berita</a>
                <a href="#" class="hover:text-indigo-600">Perliputan</a>
                <a href="#" class="hover:text-indigo-600">TV Club</a>
                @auth
                    <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Admin Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="px-3 py-1.5 rounded-md border border-indigo-600 text-indigo-600 hover:bg-indigo-50">Login Admin</a>
                @endauth
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{ $slot }}
    </main>

    <!-- Footer Placeholder -->
    <footer class="bg-white border-t border-gray-200 py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-500">
            &copy; {{ date('Y') }} STMIK Widya Pratama Pekalongan - WPiTV Modernization. All rights reserved.
        </div>
    </footer>

    @livewireScripts
</body>
</html>
