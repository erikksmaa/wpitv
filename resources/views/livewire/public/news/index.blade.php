<div class="public-listing-page public-listing-page--news">
    <!-- COMPACT HERO -->
    <section class="pub-hero !py-12 md:!py-16 border-b border-[var(--border)]" aria-labelledby="newsIndexTitle">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container relative z-10">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-4" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                <span class="text-[var(--text-faint)]">/</span>
                <span class="text-brand-blue font-medium" aria-current="page">Berita</span>
            </nav>

            <div class="max-w-2xl">
                <div class="hero-eyebrow !mb-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-blue" aria-hidden="true"></span>
                    Kabar &amp; Jurnalistik Kampus
                </div>
                <h1 class="hero-title !text-3xl md:!text-4xl !mb-2.5" id="newsIndexTitle">
                    Berita WPiTV
                </h1>
                <p class="text-sm md:text-base text-[var(--text-muted)] leading-relaxed">
                    Informasi, kegiatan, dan cerita terbaru dari Institut Widya Pratama dan WPiTV.
                </p>
            </div>
        </div>
    </section>

    <!-- CONTENT SECTION -->
    <section class="pub-section">
        <div class="pub-container">
            <!-- CONTROLS & FILTERS BAR -->
            <div class="bg-[var(--surface)] p-4 sm:p-5 rounded-2xl border border-[var(--border)] shadow-sm mb-8 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-center">
                    <!-- Search Input -->
                    <div class="md:col-span-6 relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[var(--text-faint)]">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        </div>
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="search"
                            placeholder="Cari judul berita / penulis..." 
                            class="w-full pl-10 pr-4 py-2.5 bg-[var(--bg)] border border-[var(--border)] rounded-lg text-sm text-[var(--text-primary)] placeholder-[var(--text-faint)] focus:outline-none focus:border-brand-blue transition-colors"
                            aria-label="Cari berita"
                        >
                    </div>

                    <!-- Year Filter -->
                    <div class="md:col-span-3">
                        <select 
                            wire:model.live="year"
                            class="w-full bg-[var(--bg)] border border-[var(--border)] text-[var(--text-primary)] text-sm rounded-lg px-3.5 py-2.5 focus:outline-none focus:border-brand-blue cursor-pointer"
                            aria-label="Filter berdasarkan Tahun"
                        >
                            <option value="">Semua Tahun</option>
                            @foreach($yearsList as $yr)
                                <option value="{{ $yr }}">{{ $yr }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Sort -->
                    <div class="md:col-span-3">
                        <select 
                            wire:model.live="sort"
                            class="w-full bg-[var(--bg)] border border-[var(--border)] text-[var(--text-primary)] text-sm rounded-lg px-3.5 py-2.5 focus:outline-none focus:border-brand-blue cursor-pointer"
                            aria-label="Urutkan Berita"
                        >
                            <option value="latest">Terbaru</option>
                            <option value="oldest">Terlama</option>
                            <option value="title_asc">Judul A-Z</option>
                            <option value="title_desc">Judul Z-A</option>
                        </select>
                    </div>
                </div>

                @if($search !== '' || $year !== '' || $sort !== 'latest')
                    <div class="flex items-center justify-between pt-3 border-t border-[var(--border)] text-xs text-[var(--text-muted)]">
                        <span>Menampilkan hasil filter berita</span>
                        <button 
                            type="button" 
                            wire:click="resetFilters" 
                            class="text-brand-blue dark:text-brand-cyan hover:underline font-semibold"
                        >
                            Reset Semua Filter
                        </button>
                    </div>
                @endif
            </div>

            <!-- NEWS LISTING CONTENT -->
            @if($newsList->isNotEmpty())
                @php
                    $isFirstPage = $newsList->currentPage() === 1;
                    $featuredItem = $isFirstPage ? $newsList->first() : null;
                    $gridItems = $isFirstPage ? $newsList->slice(1) : $newsList;
                @endphp

                <!-- FEATURED ARTICLE (ONLY ON PAGE 1) -->
                @if($featuredItem)
                    <a href="{{ route('news.show', $featuredItem->slug) }}" class="news-featured-card group">
                        <div class="aspect-video lg:aspect-auto overflow-hidden bg-[var(--surface-muted)] relative">
                            <img 
                                src="{{ $featuredItem->image_url }}" 
                                alt="{{ $featuredItem->title }}" 
                                loading="eager"
                                fetchpriority="high"
                                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            >
                        </div>
                        <div class="p-6 sm:p-8 flex flex-col justify-center">
                            <div class="flex items-center gap-2.5 text-xs text-[var(--text-faint)] mb-3">
                                <span class="font-semibold text-brand-blue dark:text-brand-cyan">
                                    {{ $featuredItem->author_display_name }}
                                </span>
                                <span>&bull;</span>
                                <time datetime="{{ $featuredItem->created_at?->toIso8601String() }}">
                                    {{ $featuredItem->created_at ? $featuredItem->created_at->isoFormat('D MMMM Y') : '' }}
                                </time>
                            </div>
                            <h2 class="font-heading font-extrabold text-xl sm:text-2xl text-[var(--text-primary)] group-hover:text-brand-blue transition-colors leading-snug mb-3.5 line-clamp-3">
                                {{ $featuredItem->title }}
                            </h2>
                            <p class="text-sm text-[var(--text-muted)] leading-relaxed line-clamp-3 mb-5">
                                {{ Str::limit(strip_tags($featuredItem->content), 200) }}
                            </p>
                            <span class="text-xs font-semibold text-brand-blue dark:text-brand-cyan inline-flex items-center gap-1">
                                Baca Selengkapnya &rarr;
                            </span>
                        </div>
                    </a>
                @endif

                <!-- NEWS GRID -->
                @if($gridItems->isNotEmpty())
                    <div class="news-grid mb-10" role="list">
                        @foreach($gridItems as $item)
                            <article role="listitem">
                                <a href="{{ route('news.show', $item->slug) }}" class="news-card group">
                                    <div class="news-card-thumb">
                                        <img 
                                            src="{{ $item->image_url }}" 
                                            alt="{{ $item->title }}" 
                                            loading="lazy"
                                        >
                                    </div>
                                    <div class="p-5 flex flex-col flex-grow">
                                        <div class="flex items-center gap-2 text-[11px] text-[var(--text-faint)] mb-2">
                                            <span class="truncate max-w-[140px] font-medium text-brand-blue dark:text-brand-cyan">
                                                {{ $item->author_display_name }}
                                            </span>
                                            <span>&bull;</span>
                                            <time datetime="{{ $item->created_at?->toIso8601String() }}">
                                                {{ $item->created_at ? $item->created_at->isoFormat('D MMM Y') : '' }}
                                            </time>
                                        </div>
                                        <h3 class="font-heading font-bold text-sm text-[var(--text-primary)] leading-snug line-clamp-2 group-hover:text-brand-blue transition-colors mb-2">
                                            {{ $item->title }}
                                        </h3>
                                        <p class="text-xs text-[var(--text-muted)] leading-relaxed line-clamp-2 mb-4">
                                            {{ Str::limit(strip_tags($item->content), 100) }}
                                        </p>
                                        <span class="mt-auto text-xs font-semibold text-brand-blue dark:text-brand-cyan inline-flex items-center gap-1">
                                            Baca Berita &rarr;
                                        </span>
                                    </div>
                                </a>
                            </article>
                        @endforeach
                    </div>
                @endif

                <!-- PAGINATION -->
                <div class="mt-8">
                    {{ $newsList->links('vendor.pagination.public') }}
                </div>
            @else
                <!-- EMPTY STATE -->
                <div class="text-center py-16 px-4 bg-[var(--surface)] border border-[var(--border)] rounded-2xl max-w-lg mx-auto">
                    <div class="w-16 h-16 rounded-full bg-brand-blue/10 text-brand-blue flex items-center justify-center mx-auto mb-4">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/>
                        </svg>
                    </div>
                    <h3 class="font-heading font-bold text-lg text-[var(--text-primary)] mb-1">
                        Tidak ada berita yang ditemukan.
                    </h3>
                    <p class="text-sm text-[var(--text-muted)] max-w-xs mx-auto mb-6">
                        Coba sesuaikan kata kunci pencarian atau filter tahun Anda.
                    </p>
                    <button 
                        type="button" 
                        wire:click="resetFilters" 
                        class="pub-btn pub-btn--primary !text-xs !py-2 !px-4"
                    >
                        Reset Filter
                    </button>
                </div>
            @endif
        </div>
    </section>
</div>
