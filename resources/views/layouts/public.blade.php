<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'WPiTV — Televisi Digital Kampus Institut Widya Pratama' }}</title>
    <meta name="description"
        content="{{ $description ?? 'WPiTV adalah televisi digital kampus Institut Widya Pratama Pekalongan. Brings The World Into You.' }}">

    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">

    <!-- Vite Assets -->
    @vite(['resources/css/public.css', 'resources/js/public.js'])
    @livewireStyles
</head>

<body
    class="bg-[var(--bg)] text-[var(--text-primary)] font-body antialiased min-h-screen flex flex-col justify-between">

    <!-- HEADER -->
    <header id="siteHeader">
        <div class="pub-nav">
            <a href="{{ route('public.home') }}" class="flex items-center gap-2.5 flex-shrink-0"
                aria-label="WPiTV Beranda">
                <img src="{{ asset('logo-wpi.png') }}" alt="Logo WPiTV" class="h-[38px] w-auto" loading="eager"
                    fetchpriority="high">
                <div class="flex flex-col leading-none">
                    <span class="nav-logo-name">WPiTV</span>
                    <span class="text-[10px] font-medium text-[var(--text-muted)] mt-0.5 nav-logo-slogan">Brings The
                        World Into You</span>
                </div>
            </a>

            <nav aria-label="Navigasi utama">
                <ul class="flex items-center justify-center gap-0.5 list-none m-0 p-0">
                    <li><a href="{{ route('public.home') }}"
                            class="nav-link {{ request()->routeIs('public.home') ? 'active' : '' }}">Beranda</a></li>
                    <li><a href="{{ route('program.index') }}"
                            class="nav-link {{ request()->routeIs('program.*') ? 'active' : '' }}">Program</a></li>
                    <li><a href="{{ route('video.index') }}"
                            class="nav-link {{ request()->routeIs('video.*') ? 'active' : '' }}">Tayangan</a></li>
                    <li><a href="{{ route('news.index') }}"
                            class="nav-link {{ request()->routeIs('news.*') ? 'active' : '' }}">Berita</a></li>
                    <li><a href="{{ route('peliputan.index') }}"
                            class="nav-link {{ request()->routeIs('peliputan.*') ? 'active' : '' }}">Peliputan</a></li>
                    <li><a href="{{ route('agenda.index') }}"
                            class="nav-link {{ request()->routeIs('agenda.*') ? 'active' : '' }}">Agenda</a></li>
                    <li class="tvclub-nav-dropdown" data-tvclub-nav>
                        <button type="button"
                            class="nav-link tvclub-nav-trigger {{ request()->routeIs('tvclub.*') ? 'active' : '' }}"
                            aria-expanded="false" aria-controls="tvclubDesktopMenu" data-tvclub-nav-trigger>
                            TV Club
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="tvclub-nav-menu" id="tvclubDesktopMenu" aria-label="Navigasi TV Club" aria-hidden="true" data-tvclub-nav-menu>
                            <a href="{{ route('tvclub.index') }}" class="{{ request()->routeIs('tvclub.index') ? 'active' : '' }}">
                                <span class="tvclub-nav-menu__icon" aria-hidden="true">01</span><span><strong>Tentang TV Club</strong><small>Mengenal komunitas produksi WPiTV</small></span>
                            </a>
                            <a href="{{ route('tvclub.generations.index') }}" class="{{ request()->routeIs('tvclub.generations.*') ? 'active' : '' }}">
                                <span class="tvclub-nav-menu__icon" aria-hidden="true">02</span><span><strong>Generasi</strong><small>Perjalanan TV Club dari setiap angkatan</small></span>
                            </a>
                            <a href="{{ route('tvclub.members.index') }}" class="{{ request()->routeIs('tvclub.members.*') ? 'active' : '' }}">
                                <span class="tvclub-nav-menu__icon" aria-hidden="true">03</span><span><strong>Kru TV Club</strong><small>Direktori mahasiswa dan kru produksi</small></span>
                            </a>
                            <a href="{{ route('tvclub.groups.index') }}" class="{{ request()->routeIs('tvclub.groups.*') ? 'active' : '' }}">
                                <span class="tvclub-nav-menu__icon" aria-hidden="true">04</span><span><strong>Kelompok Produksi</strong><small>Tim di balik program-program WPiTV</small></span>
                            </a>
                        </div>
                    </li>
                    <li><a href="{{ route('live.index') }}" class="nav-link {{ request()->routeIs('live.*') ? 'active' : '' }}">Live</a></li><li><a href="{{ route('public.about') }}"
                            class="nav-link {{ request()->routeIs('public.about') ? 'active' : '' }}">Tentang</a></li>
                </ul>
            </nav>

            <div class="flex items-center gap-2.5">
                <button class="nav-icon-btn" id="searchBtn" aria-label="Cari konten" aria-controls="globalPublicSearch" aria-expanded="false" type="button">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.35-4.35" />
                    </svg>
                </button>
                <button class="toggle-btn cursor-pointer bg-transparent border-0 p-0" id="themeToggle"
                    aria-label="Ganti tema" type="button">
                    <span class="toggle-track block"><span class="toggle-thumb block"></span></span>
                </button>
                <a href="{{ route('video.index') }}"
                    class="pub-btn pub-btn--primary !text-xs !py-2 !px-3.5 hidden sm:inline-flex">Tonton Terbaru</a>
                <button class="nav-burger" id="burgerBtn" aria-label="Buka menu" aria-expanded="false" type="button">
                    <span></span><span></span><span></span>
                </button>
            </div>

            <div class="nav-mobile" id="mobilePanel" aria-hidden="true">
                <div class="flex flex-col gap-0.5 py-2.5 px-0 border-t border-[var(--border)] mt-2">
                    <a href="{{ route('public.home') }}"
                        class="text-base font-medium text-[var(--text-secondary)] py-2.5 px-2 rounded-lg hover:text-brand-blue hover:bg-brand-blue/10">Beranda</a>
                    <a href="{{ route('program.index') }}"
                        class="text-base font-medium text-[var(--text-secondary)] py-2.5 px-2 rounded-lg hover:text-brand-blue hover:bg-brand-blue/10">Program</a>
                    <a href="{{ route('video.index') }}"
                        class="text-base font-medium text-[var(--text-secondary)] py-2.5 px-2 rounded-lg hover:text-brand-blue hover:bg-brand-blue/10">Tayangan</a>
                    <a href="{{ route('news.index') }}"
                        class="text-base font-medium text-[var(--text-secondary)] py-2.5 px-2 rounded-lg hover:text-brand-blue hover:bg-brand-blue/10">Berita</a>
                    <a href="{{ route('peliputan.index') }}"
                        class="text-base font-medium text-[var(--text-secondary)] py-2.5 px-2 rounded-lg hover:text-brand-blue hover:bg-brand-blue/10">Peliputan</a>
                    <a href="{{ route('agenda.index') }}"
                        class="text-base font-medium text-[var(--text-secondary)] py-2.5 px-2 rounded-lg hover:text-brand-blue hover:bg-brand-blue/10">Agenda</a>
                    <a href="{{ route('live.index') }}"
                        class="text-base font-medium text-[var(--text-secondary)] py-2.5 px-2 rounded-lg hover:text-brand-blue hover:bg-brand-blue/10">Live Streaming</a>
                    <div class="tvclub-mobile-accordion" data-tvclub-mobile>
                        <button type="button" class="tvclub-mobile-trigger {{ request()->routeIs('tvclub.*') ? 'active' : '' }}"
                            aria-expanded="false" aria-controls="tvclubMobileMenu" data-tvclub-mobile-trigger>
                            <span>TV Club</span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="tvclub-mobile-menu" id="tvclubMobileMenu" aria-hidden="true" data-tvclub-mobile-menu>
                            <a href="{{ route('tvclub.index') }}" class="{{ request()->routeIs('tvclub.index') ? 'active' : '' }}">Tentang TV Club</a>
                            <a href="{{ route('tvclub.generations.index') }}" class="{{ request()->routeIs('tvclub.generations.*') ? 'active' : '' }}">Generasi</a>
                            <a href="{{ route('tvclub.members.index') }}" class="{{ request()->routeIs('tvclub.members.*') ? 'active' : '' }}">Kru TV Club</a>
                            <a href="{{ route('tvclub.groups.index') }}" class="{{ request()->routeIs('tvclub.groups.*') ? 'active' : '' }}">Kelompok Produksi</a>
                        </div>
                    </div>
                    <a href="{{ route('public.about') }}"
                        class="text-base font-medium text-[var(--text-secondary)] py-2.5 px-2 rounded-lg hover:text-brand-blue hover:bg-brand-blue/10">Tentang</a>
                </div>
            </div>
        </div>
    </header>

    <livewire:public.global-search />

    <!-- MAIN CONTENT -->
    <main class="flex-grow">
        {{ $slot }}
    </main>

    <!-- FOOTER -->
    <footer id="tentang" class="pub-footer" aria-label="Footer WPiTV">
        <div class="pub-container">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 pb-12 mb-8 border-b border-white/10">
                <div class="lg:col-span-1">
                    <img src="{{ asset('logo-wpi.png') }}" alt="Logo WPiTV" class="h-[42px] w-auto mb-3.5"
                        loading="lazy">
                    <p
                        class="text-xs font-semibold tracking-wider text-brand-orange uppercase mb-3 flex items-center gap-2">
                        <span class="inline-block w-3.5 h-0.5 bg-brand-orange rounded-full"></span>
                        Brings The World Into You
                    </p>
                    <p class="text-sm text-white/55 leading-relaxed max-w-[260px] mb-6">
                        Televisi digital kampus Institut Widya Pratama — ruang karya, informasi, dan broadcasting mahasiswa.
                    </p>
                    <x-public.social-links variant="footer" />
                </div>

                <div>
                    <h4 class="font-heading font-bold text-xs tracking-wider uppercase text-white/35 mb-4">Jelajahi</h4>
                    <ul class="space-y-2.5 list-none p-0 m-0 text-sm text-white/60">
                        <li><a href="{{ route('program.index') }}" class="hover:text-white transition-colors">Program
                                Acara</a></li>
                        <li><a href="{{ route('video.index') }}" class="hover:text-white transition-colors">Tayangan</a>
                        </li>
                        <li><a href="{{ route('news.index') }}" class="hover:text-white transition-colors">Berita</a>
                        </li>
                        <li><a href="{{ route('live.index') }}" class="hover:text-white transition-colors">Live
                                Streaming</a></li>
                        <li><a href="{{ route('peliputan.index') }}"
                                class="hover:text-white transition-colors">Peliputan</a></li>
                        <li><a href="{{ route('agenda.index') }}" class="hover:text-white transition-colors">Agenda</a></li>
                        <li><a href="{{ route('tvclub.index') }}" class="hover:text-white transition-colors">TV Club</a>
                        </li>
                    </ul>
                </div>

                <div>
                    <h4 class="font-heading font-bold text-xs tracking-wider uppercase text-white/35 mb-4">Institusi
                    </h4>
                    <ul class="space-y-2.5 list-none p-0 m-0 text-sm text-white/60">
                        <li><a href="https://stmik-wp.ac.id" target="_blank" rel="noopener noreferrer"
                                class="hover:text-white transition-colors">Institut Widya Pratama</a></li>
                        <li><a href="https://stmik-wp.ac.id/profil" target="_blank" rel="noopener noreferrer"
                                class="hover:text-white transition-colors">Profil Kampus</a></li>
                        <li><a href="https://pmb.stmik-wp.ac.id" target="_blank" rel="noopener noreferrer"
                                class="hover:text-white transition-colors">PMB</a></li>
                        <li><a href="{{ route('public.about') }}" class="hover:text-white transition-colors">Tentang WPiTV</a></li>
                    </ul>
                </div>

                <x-public.contact-info variant="footer" />
            </div>

            <div
                class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 text-xs text-white/30">
                <span>&copy; {{ date('Y') }} Institut Widya Pratama Pekalongan — WPiTV</span>
                <span>Televisi Digital Kampus</span>
            </div>
        </div>
    </footer>

    <div class="pub-floating-dock" data-floating-dock>
        <div class="pub-floating-actions" role="group" aria-label="Aksi cepat">
            <button
                type="button"
                id="backToTopBtn"
                class="pub-fab pub-fab--top"
                data-back-to-top
                data-tooltip="Kembali ke atas"
                title="Kembali ke atas"
                aria-label="Kembali ke atas"
                aria-hidden="true"
                tabindex="-1">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25"
                    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 19V5M5 12l7-7 7 7" />
                </svg>
            </button>

            @php($floatingInstagram = app(\App\Services\PublicSocialAccounts::class)->primaryByPlatform('instagram'))
            @if($floatingInstagram)
                <a
                    href="{{ $floatingInstagram->url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="pub-fab pub-fab--instagram"
                    data-floating-instagram
                    data-tooltip="Instagram WPiTV"
                    title="Instagram WPiTV"
                    aria-label="Instagram WPiTV — {{ $floatingInstagram->display_name }}">
                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="2" width="20" height="20" rx="5" />
                        <circle cx="12" cy="12" r="4" />
                        <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
                    </svg>
                </a>
            @endif
        </div>

        <livewire:public.visitor-counter />
    </div>

    @livewireScripts
</body>

</html>
