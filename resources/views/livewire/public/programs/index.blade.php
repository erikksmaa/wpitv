<div class="public-listing-page public-listing-page--programs">
    <!-- COMPACT HERO -->
    <section class="pub-hero !py-12 md:!py-16 border-b border-[var(--border)]" aria-labelledby="programIndexTitle">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container relative z-10">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-4" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                <span class="text-[var(--text-faint)]">/</span>
                <span class="text-brand-blue font-medium" aria-current="page">Program</span>
            </nav>

            <div class="max-w-2xl">
                <div class="hero-eyebrow !mb-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-blue" aria-hidden="true"></span>
                    Produksi &amp; Siaran Kampus
                </div>
                <h1 class="hero-title !text-3xl md:!text-4xl !mb-2.5" id="programIndexTitle">
                    Program Acara
                </h1>
                <p class="text-sm md:text-base text-[var(--text-muted)] leading-relaxed">
                    Temukan berbagai program dan tayangan produksi WPiTV.
                </p>
            </div>
        </div>
    </section>

    <!-- CONTENT SECTION -->
    <section class="pub-section">
        <div class="pub-container">
            <!-- CONTROLS: SEARCH & SORT -->
            <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4 mb-8 bg-[var(--surface)] p-4 rounded-xl border border-[var(--border)] shadow-sm">
                <!-- Search -->
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[var(--text-faint)]">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    </div>
                    <input 
                        type="text" 
                        wire:model.live.debounce.300ms="search"
                        placeholder="Cari Program..." 
                        class="w-full pl-10 pr-4 py-2.5 bg-[var(--bg)] border border-[var(--border)] rounded-lg text-sm text-[var(--text-primary)] placeholder-[var(--text-faint)] focus:outline-none focus:border-brand-blue transition-colors"
                        aria-label="Cari Program"
                    >
                </div>

                <!-- Sort -->
                <div class="flex items-center gap-2.5 flex-shrink-0">
                    <label for="sortSelect" class="text-xs font-semibold uppercase tracking-wider text-[var(--text-muted)] whitespace-nowrap">
                        Urutkan:
                    </label>
                    <select 
                        id="sortSelect"
                        wire:model.live="sort"
                        class="bg-[var(--bg)] border border-[var(--border)] text-[var(--text-primary)] text-sm rounded-lg px-3.5 py-2.5 focus:outline-none focus:border-brand-blue cursor-pointer"
                        aria-label="Urutkan Program"
                    >
                        <option value="latest">Terbaru</option>
                        <option value="name_asc">Nama A-Z</option>
                        <option value="name_desc">Nama Z-A</option>
                        <option value="most_videos">Tayangan Terbanyak</option>
                    </select>
                </div>
            </div>

            <!-- PROGRAM GRID -->
            @if($programs->isNotEmpty())
                <div class="public-card-grid public-card-grid--portrait grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-10" role="list">
                    @foreach($programs as $program)
                        <article class="bg-[var(--surface)] border border-[var(--border)] rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 flex flex-col group hover:-translate-y-1" role="listitem">
                            <a href="{{ route('program.show', $program->slug) }}" class="block aspect-[3/4] overflow-hidden relative bg-[var(--surface-muted)]">
                                <img 
                                    src="{{ $program->image_url }}" 
                                    alt="Poster Program {{ $program->name }}" 
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    loading="lazy"
                                >
                                <div class="absolute bottom-2.5 left-2.5 bg-black/75 backdrop-blur-sm text-white text-[11px] font-semibold px-2.5 py-1 rounded-md">
                                    {{ $program->videos_count }} Tayangan
                                </div>
                                <div class="absolute inset-0 bg-brand-blue/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <div class="w-11 h-11 rounded-full bg-brand-blue text-white flex items-center justify-center shadow-lg transform scale-75 group-hover:scale-100 transition-transform">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="white" class="ml-0.5"><polygon points="3,1 13,7 3,13"/></svg>
                                    </div>
                                </div>
                            </a>

                            <div class="p-5 flex flex-col flex-grow">
                                <h2 class="font-heading font-bold text-base text-[var(--text-primary)] group-hover:text-brand-blue transition-colors mb-2 line-clamp-1">
                                    <a href="{{ route('program.show', $program->slug) }}">
                                        {{ $program->name }}
                                    </a>
                                </h2>

                                <p class="text-xs text-[var(--text-muted)] leading-relaxed line-clamp-2 mb-5 flex-grow">
                                    {{ $program->description ? strip_tags($program->description) : 'Program siaran resmi dari WPiTV Institut Widya Pratama.' }}
                                </p>

                                <a 
                                    href="{{ route('program.show', $program->slug) }}" 
                                    class="w-full pub-btn pub-btn--secondary !text-xs !py-2.5 justify-center mt-auto"
                                >
                                    Lihat Program
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                <!-- PAGINATION -->
                <div class="mt-8">
                    {{ $programs->links('vendor.pagination.public') }}
                </div>
            @else
                <!-- EMPTY STATE -->
                <div class="text-center py-16 px-4 bg-[var(--surface)] border border-[var(--border)] rounded-2xl max-w-lg mx-auto">
                    <div class="w-16 h-16 rounded-full bg-brand-blue/10 text-brand-blue flex items-center justify-center mx-auto mb-4">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                    </div>
                    <h3 class="font-heading font-bold text-lg text-[var(--text-primary)] mb-1">
                        Program tidak ditemukan.
                    </h3>
                    <p class="text-sm text-[var(--text-muted)] max-w-xs mx-auto mb-6">
                        Coba gunakan kata kunci yang berbeda.
                    </p>
                    @if($search !== '')
                        <button 
                            type="button" 
                            wire:click="$set('search', '')" 
                            class="pub-btn pub-btn--primary !text-xs !py-2 !px-4"
                        >
                            Reset Pencarian
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </section>
</div>
