<div class="public-listing-page public-listing-page--videos">
    <!-- COMPACT HERO -->
    <section class="pub-hero !py-12 md:!py-16 border-b border-[var(--border)]" aria-labelledby="videoIndexTitle">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container relative z-10">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-4" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                <span class="text-[var(--text-faint)]">/</span>
                <span class="text-brand-blue font-medium" aria-current="page">Tayangan</span>
            </nav>

            <div class="max-w-2xl">
                <div class="hero-eyebrow !mb-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-blue" aria-hidden="true"></span>
                    Katalog Tayangan &amp; Produksi
                </div>
                <h1 class="hero-title !text-3xl md:!text-4xl !mb-2.5" id="videoIndexTitle">
                    Tayangan WPiTV
                </h1>
                <p class="text-sm md:text-base text-[var(--text-muted)] leading-relaxed">
                    Jelajahi tayangan, program, berita video, dan produksi audiovisual WPiTV.
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
                    <div class="md:col-span-4 relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[var(--text-faint)]">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        </div>
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="search"
                            placeholder="Cari judul tayangan..." 
                            class="w-full pl-10 pr-4 py-2.5 bg-[var(--bg)] border border-[var(--border)] rounded-lg text-sm text-[var(--text-primary)] placeholder-[var(--text-faint)] focus:outline-none focus:border-brand-blue transition-colors"
                            aria-label="Cari tayangan"
                        >
                    </div>

                    <!-- Program Filter -->
                    <div class="md:col-span-3">
                        <select 
                            wire:model.live="program"
                            class="w-full bg-[var(--bg)] border border-[var(--border)] text-[var(--text-primary)] text-sm rounded-lg px-3.5 py-2.5 focus:outline-none focus:border-brand-blue cursor-pointer"
                            aria-label="Filter berdasarkan Program"
                        >
                            <option value="">Semua Program</option>
                            <option value="without_program">Tanpa Program</option>
                            @foreach($programsList as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Year Filter -->
                    <div class="md:col-span-2">
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
                            aria-label="Urutkan Tayangan"
                        >
                            <option value="latest">Terbaru Tayang</option>
                            <option value="oldest">Terlama Tayang</option>
                            <option value="title_asc">Judul A-Z</option>
                            <option value="title_desc">Judul Z-A</option>
                            <option value="newest_created">Terbaru Ditambahkan</option>
                        </select>
                    </div>
                </div>

                @if($search !== '' || $program !== '' || $year !== '' || $sort !== 'latest')
                    <div class="flex items-center justify-between pt-3 border-t border-[var(--border)] text-xs text-[var(--text-muted)]">
                        <span>Menampilkan hasil filter tayangan</span>
                        <button 
                            type="button" 
                            wire:click="resetFilters" 
                            class="text-brand-blue dark:text-brand-cyan hover:underline font-semibold"
                        >
                            Reset Filter
                        </button>
                    </div>
                @endif
            </div>

            <!-- VIDEO GRID -->
            @if($videos->isNotEmpty())
                <div class="public-card-grid public-card-grid--video grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-10" role="list">
                    @foreach($videos as $video)
                        <article class="video-card group" role="listitem">
                            <a href="{{ route('video.show', $video) }}" class="block">
                                <div class="video-thumb">
                                    <img 
                                        src="{{ $video->thumbnail_url }}" 
                                        alt="{{ $video->title }}" 
                                        loading="lazy"
                                        class="w-full h-full object-cover"
                                    >
                                    <div class="vc-play" aria-hidden="true">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="white" class="ml-0.5"><polygon points="3,1 13,7 3,13"/></svg>
                                    </div>
                                </div>
                                <div class="text-[11px] font-semibold tracking-wider uppercase text-brand-blue dark:text-brand-cyan mb-1.5">
                                    {{ $video->program?->name ?? 'WPiTV' }}
                                </div>
                                <h2 class="font-heading font-bold text-sm text-[var(--text-primary)] leading-snug mb-1.5 line-clamp-2 group-hover:text-brand-blue transition-colors">
                                    {{ $video->title }}
                                </h2>
                                <div class="text-xs text-[var(--text-muted)]">
                                    {{ $video->air_date ? $video->air_date->isoFormat('D MMMM Y') : 'Tanggal tidak tersedia' }}
                                </div>
                            </a>
                        </article>
                    @endforeach
                </div>

                <!-- PAGINATION -->
                <div class="mt-8">
                    {{ $videos->links('vendor.pagination.public') }}
                </div>
            @else
                <!-- EMPTY STATE -->
                <div class="text-center py-16 px-4 bg-[var(--surface)] border border-[var(--border)] rounded-2xl max-w-lg mx-auto">
                    <div class="w-16 h-16 rounded-full bg-brand-blue/10 text-brand-blue flex items-center justify-center mx-auto mb-4">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polygon points="5,3 19,12 5,21"/>
                        </svg>
                    </div>
                    <h3 class="font-heading font-bold text-lg text-[var(--text-primary)] mb-1">
                        {{ $search !== '' || $program !== '' || $year !== '' ? 'Tidak ada hasil yang sesuai.' : 'Tayangan belum tersedia.' }}
                    </h3>
                    <p class="text-sm text-[var(--text-muted)] max-w-xs mx-auto mb-6">
                        {{ $search !== '' || $program !== '' || $year !== '' ? 'Coba ubah kata kunci atau filter pencarian.' : 'Tayangan terbaru akan ditampilkan di sini.' }}
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
