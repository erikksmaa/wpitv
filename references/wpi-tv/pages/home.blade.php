<div>
    <!-- TICKER -->
    <div class="pub-ticker" role="marquee" aria-label="Berita terbaru WPiTV">
        <div class="ticker-track" id="tickerTrack">
            <span class="ticker-badge"><span class="blink-dot" aria-hidden="true"></span>TERBARU</span>
            @foreach($tickerItems as $item)
                <span class="ticker-item">{{ $item }} <span class="ticker-sep">◆</span></span>
            @endforeach
            <!-- Duplikasi untuk loop mulus -->
            <span class="ticker-badge" aria-hidden="true"><span class="blink-dot"></span>TERBARU</span>
            @foreach($tickerItems as $item)
                <span class="ticker-item" aria-hidden="true">{{ $item }} <span class="ticker-sep">◆</span></span>
            @endforeach
        </div>
    </div>

    <!-- HERO -->
    @php($activeFeatured = $defaultFeaturedVideo)
    <section class="pub-hero home-featured-hero" id="featuredHero" aria-labelledby="heroTitle">
        <div class="hero-deco" aria-hidden="true"></div>
        <div class="pub-container">
            <div class="home-featured-hero-grid">
                <div data-reveal>
                    <div class="hero-eyebrow">
                        <span class="w-1.5 h-1.5 rounded-full bg-brand-blue" aria-hidden="true"></span>
                        WPiTV &bull; Televisi Digital Kampus
                    </div>
                    <h1 class="hero-title" id="heroTitle">
                        Cerita Kampus.<br>
                        <span class="text-brand-blue dark:text-brand-cyan">Dalam Satu Layar.</span>
                    </h1>
                    <p class="hero-slogan">Brings The World Into You</p>
                    <p class="text-base text-[var(--text-muted)] max-w-lg mb-8 leading-relaxed">
                        WPiTV menghadirkan tayangan, berita, dan cerita dari Institut Widya Pratama melalui karya
                        mahasiswa di bidang broadcasting dan media digital.
                    </p>
                    <div class="flex gap-3 flex-wrap mb-9">
                        <a href="{{ route('video.index') }}" class="pub-btn pub-btn--primary">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor" aria-hidden="true">
                                <polygon points="3,1 13,7 3,13" />
                            </svg>
                            Tonton Tayangan
                        </a>
                        <a href="{{ route('program.index') }}" class="pub-btn pub-btn--secondary">Jelajahi Program</a>
                    </div>
                    @if($activeFeatured)
                        <div class="home-featured-meta" data-home-featured-meta>
                            <div class="home-featured-meta-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="18" rx="2" />
                                    <path d="M3 10h18M8 2v4M16 2v4" />
                                </svg>
                                <span data-home-featured-meta-primary>{{ $activeFeatured['metaPrimary'] }}</span>
                            </div>
                            <div class="home-featured-meta-item {{ $activeFeatured['metaSecondary'] ? '' : 'hidden' }}"
                                data-home-featured-meta-secondary-wrap>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <span data-home-featured-meta-secondary>
                                    @if($activeFeatured['program'])
                                        Program: {{ $activeFeatured['program'] }}
                                    @else
                                        {{ $activeFeatured['metaSecondary'] }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="home-featured-column" data-reveal data-d="1">
                    @if($activeFeatured)
                        <div class="home-featured-video" data-home-featured data-active-key="{{ $activeFeatured['key'] }}">
                            <div class="home-featured-player" id="homeFeaturedPlayer" data-home-featured-player>
                                <img src="{{ $activeFeatured['thumbnailUrl'] }}"
                                    alt="Cuplikan {{ $activeFeatured['title'] }}"
                                    class="home-featured-poster"
                                    data-home-featured-poster
                                    loading="eager"
                                    fetchpriority="high">
                                <iframe width="1280" height="720"
                                    data-hero-video
                                    data-home-featured-frame
                                    data-src="{{ $activeFeatured['embedUrl'] }}?playsinline=1&controls=1&rel=0&modestbranding=1"
                                    title="{{ $activeFeatured['title'] }} — WPiTV"
                                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    allowfullscreen></iframe>
                                <div class="home-featured-player-label" aria-hidden="true">
                                    <span data-home-featured-player-label>{{ $activeFeatured['label'] }}</span>
                                </div>
                                <div class="frame-corner fc-tl" aria-hidden="true"></div>
                                <div class="frame-corner fc-tr" aria-hidden="true"></div>
                                <div class="frame-corner fc-bl" aria-hidden="true"></div>
                                <div class="frame-corner fc-br" aria-hidden="true"></div>
                            </div>

                            <div class="home-video-selector" role="tablist" aria-label="Pilih video unggulan WPiTV">
                                @foreach($featuredVideos as $featuredVideo)
                                    @php($isActive = $featuredVideo['key'] === $activeFeatured['key'])
                                    <button type="button"
                                        id="home-video-tab-{{ $featuredVideo['key'] }}"
                                        class="home-video-option {{ $isActive ? 'is-active' : '' }}"
                                        role="tab"
                                        aria-selected="{{ $isActive ? 'true' : 'false' }}"
                                        aria-controls="homeFeaturedPlayer"
                                        tabindex="{{ $isActive ? '0' : '-1' }}"
                                        data-home-video-option
                                        data-key="{{ $featuredVideo['key'] }}"
                                        data-label="{{ $featuredVideo['label'] }}"
                                        data-title="{{ $featuredVideo['title'] }}"
                                        data-embed-url="{{ $featuredVideo['embedUrl'] }}"
                                        data-thumbnail-url="{{ $featuredVideo['thumbnailUrl'] }}"
                                        data-detail-url="{{ $featuredVideo['detailUrl'] }}"
                                        data-external-url="{{ $featuredVideo['externalUrl'] }}"
                                        data-meta-primary="{{ $featuredVideo['metaPrimary'] }}"
                                        data-meta-secondary="{{ $featuredVideo['metaSecondary'] }}"
                                        data-program="{{ $featuredVideo['program'] }}">
                                        <span class="home-video-option-thumb" aria-hidden="true">
                                            <img src="{{ $featuredVideo['thumbnailUrl'] }}" alt="" loading="lazy">
                                        </span>
                                        <span class="home-video-option-content">
                                            <span class="home-video-option-label">{{ $featuredVideo['label'] }}</span>
                                            <strong>{{ $featuredVideo['title'] }}</strong>
                                            <small>{{ $featuredVideo['context'] }}</small>
                                        </span>
                                        <span class="home-video-option-state" aria-hidden="true">Sedang diputar</span>
                                    </button>
                                @endforeach
                            </div>

                            <div class="home-featured-footer">
                                <p class="sr-only" aria-live="polite" data-home-featured-status>
                                    Video aktif: {{ $activeFeatured['title'] }}
                                </p>
                                <a href="{{ $activeFeatured['detailUrl'] ?? $activeFeatured['externalUrl'] }}"
                                    class="home-featured-detail-link"
                                    data-home-featured-link
                                    @if(!$activeFeatured['detailUrl']) target="_blank" rel="noopener noreferrer" @endif>
                                    <span data-home-featured-link-label>
                                        {{ $activeFeatured['detailUrl'] ? 'Lihat detail tayangan' : 'Tonton di YouTube' }}
                                    </span>
                                    <span aria-hidden="true">→</span>
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="home-featured-empty">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.5" aria-hidden="true">
                                <polygon points="5,3 19,12 5,21" />
                            </svg>
                            <p class="font-heading font-semibold text-lg text-white mb-1">WPiTV Official Broadcast</p>
                            <p class="text-xs">Tayangan akan segera diperbarui</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>



    <!-- PROGRAM ACARA -->
    <section class="pub-section" id="program" aria-labelledby="programH">
        <div class="pub-container">
            <div class="flex items-end justify-between gap-5 flex-wrap mb-8" data-reveal>
                <div>
                    <div class="sec-label">Program Acara</div>
                    <h2 class="sec-h2" id="programH">Program Acara WPiTV</h2>
                </div>
                <a href="{{ route('program.index') }}" class="sec-link">
                    Lihat Semua
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <div class="prog-scroller" data-reveal data-d="1" role="list">
                @forelse($programs as $program)
                    <article class="prog-card" role="listitem">
                        <a href="{{ route('program.show', $program->slug) }}" class="block group">
                            <div class="prog-thumb">
                                <img src="{{ $program->image_url }}" alt="Program {{ $program->name }}" loading="lazy">
                                <div class="prog-ep-badge">{{ $program->videos_count }} Tayangan</div>
                                <div class="prog-play-overlay" aria-hidden="true">
                                    <svg width="12" height="12" viewBox="0 0 12 12" fill="white">
                                        <polygon points="2,1 11,6 2,11" />
                                    </svg>
                                </div>
                            </div>
                            <div
                                class="font-heading font-bold text-sm text-[var(--text-primary)] truncate group-hover:text-brand-blue transition-colors">
                                {{ $program->name }}</div>
                        </a>
                    </article>
                @empty
                    <p class="text-sm text-[var(--text-muted)] py-4">Belum ada program acara aktif.</p>
                @endforelse
            </div>
        </div>
    </section>

    <!-- TAYANGAN TERBARU -->
    <section class="pub-section pub-section--muted" id="tayangan" aria-labelledby="tayanganH">
        <div class="pub-container">
            <div class="flex items-end justify-between gap-5 flex-wrap mb-8" data-reveal>
                <div>
                    <div class="sec-label">Tayangan Terbaru</div>
                    <h2 class="sec-h2" id="tayanganH">Tayangan Terbaru</h2>
                </div>
                <a href="{{ route('video.index') }}" class="sec-link">
                    Lihat Semua
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" data-reveal data-d="1">
                @forelse($latestVideos as $video)
                    <article class="video-card group">
                        <a href="{{ route('video.show', $video) }}" class="block">
                            <div class="video-thumb">
                                <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" loading="lazy">
                                <div class="vc-play" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="white" class="ml-0.5">
                                        <polygon points="3,1 13,7 3,13" />
                                    </svg>
                                </div>
                            </div>
                            <div
                                class="text-[11px] font-semibold tracking-wider uppercase text-brand-blue dark:text-brand-cyan mb-1.5">
                                {{ $video->program?->name ?? 'WPiTV' }}
                            </div>
                            <div
                                class="font-heading font-bold text-sm text-[var(--text-primary)] leading-snug mb-1.5 line-clamp-2 group-hover:text-brand-blue transition-colors">
                                {{ $video->title }}
                            </div>
                            <div class="text-xs text-[var(--text-muted)]">
                                {{ $video->air_date ? $video->air_date->isoFormat('D MMMM Y') : '' }}
                            </div>
                        </a>
                    </article>
                @empty
                    <p class="text-sm text-[var(--text-muted)] col-span-full py-4">Belum ada tayangan video yang tersedia.
                    </p>
                @endforelse
            </div>
        </div>
    </section>

    <!-- WPITV IN NUMBERS -->
    <section aria-label="WPiTV dalam Angka" class="py-0">
        <div class="pub-container">
            <div class="stats-grid" data-reveal>
                <div class="stat-card">
                    <b class="block font-heading font-extrabold text-3xl sm:text-4xl text-brand-blue leading-none mb-2 tracking-tight"
                        data-count data-target="{{ $stats['programs'] }}" data-suffix="+">
                        {{ $stats['programs'] }}+
                    </b>
                    <span class="text-xs sm:text-sm font-medium text-[var(--text-muted)]">Program Aktif</span>
                </div>
                <div class="stat-card">
                    <b class="block font-heading font-extrabold text-3xl sm:text-4xl text-brand-blue leading-none mb-2 tracking-tight"
                        data-count data-target="{{ $stats['videos'] }}" data-suffix="+">
                        {{ $stats['videos'] }}+
                    </b>
                    <span class="text-xs sm:text-sm font-medium text-[var(--text-muted)]">Tayangan Diproduksi</span>
                </div>
                <div class="stat-card">
                    <b class="block font-heading font-extrabold text-3xl sm:text-4xl text-brand-blue leading-none mb-2 tracking-tight"
                        data-count data-target="{{ $stats['news'] }}" data-suffix="+">
                        {{ $stats['news'] }}+
                    </b>
                    <span class="text-xs sm:text-sm font-medium text-[var(--text-muted)]">Berita Diterbitkan</span>
                </div>
                <div class="stat-card">
                    <b class="block font-heading font-extrabold text-3xl sm:text-4xl text-brand-blue leading-none mb-2 tracking-tight"
                        data-count data-target="{{ $stats['members'] }}" data-suffix="">
                        {{ $stats['members'] }}
                    </b>
                    <span class="text-xs sm:text-sm font-medium text-[var(--text-muted)]">Anggota TV Club Aktif</span>
                </div>
            </div>
        </div>
    </section>

    <!-- BERITA TERKINI -->
    <section class="pub-section" id="berita" aria-labelledby="beritaH">
        <div class="pub-container">
            <div class="flex items-end justify-between gap-5 flex-wrap mb-8" data-reveal>
                <div>
                    <div class="sec-label">Berita Kampus</div>
                    <h2 class="sec-h2" id="beritaH">Berita Terkini</h2>
                </div>
                <a href="{{ route('news.index') }}" class="sec-link">
                    Lihat Semua
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-7" data-reveal data-d="1">
                @if($featuredNews)
                    <div class="lg:col-span-7">
                        <a href="{{ route('news.show', $featuredNews->slug) }}" class="block h-full group">
                            <article class="news-feat h-full">
                                <div class="aspect-video overflow-hidden bg-[var(--surface-muted)]">
                                    <img src="{{ $featuredNews->image_url }}" alt="{{ $featuredNews->title }}"
                                        class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                        loading="lazy">
                                </div>
                                <div class="p-6 flex flex-col flex-grow">
                                    <span class="text-xs text-[var(--text-faint)] mb-2 block">
                                        {{ $featuredNews->created_at ? $featuredNews->created_at->isoFormat('D MMMM Y') : '' }}
                                    </span>
                                    <h3
                                        class="font-heading font-bold text-lg md:text-xl leading-snug text-[var(--text-primary)] group-hover:text-brand-blue transition-colors mb-2.5">
                                        {{ $featuredNews->title }}
                                    </h3>
                                    <p class="text-sm text-[var(--text-muted)] leading-relaxed line-clamp-3 mb-4">
                                        {{ Str::limit(strip_tags($featuredNews->content), 180) }}
                                    </p>
                                    <span
                                        class="mt-auto text-xs font-semibold text-brand-blue dark:text-brand-cyan inline-flex items-center gap-1">
                                        Oleh {{ $featuredNews->author_display_name }}
                                    </span>
                                </div>
                            </article>
                        </a>
                    </div>
                @endif

                <div class="{{ $featuredNews ? 'lg:col-span-5' : 'lg:col-span-12' }} flex flex-col gap-3.5">
                    @forelse($sideNews as $news)
                        <a href="{{ route('news.show', $news->slug) }}" class="block group">
                            <article class="news-sm">
                                <div
                                    class="flex-shrink-0 w-[90px] aspect-[4/3] rounded-lg overflow-hidden bg-[var(--surface-muted)]">
                                    <img src="{{ $news->image_url }}" alt="{{ $news->title }}"
                                        class="w-full h-full object-cover" loading="lazy">
                                </div>
                                <div class="flex-1 min-w-0 flex flex-col justify-center">
                                    <span class="text-[11px] text-[var(--text-faint)] mb-1">
                                        {{ $news->created_at ? $news->created_at->isoFormat('D MMM Y') : '' }}
                                    </span>
                                    <h4
                                        class="font-heading font-semibold text-xs sm:text-sm text-[var(--text-primary)] leading-snug line-clamp-2 group-hover:text-brand-blue transition-colors">
                                        {{ $news->title }}
                                    </h4>
                                </div>
                            </article>
                        </a>
                    @empty
                        @if(!$featuredNews)
                            <p class="text-sm text-[var(--text-muted)] py-4">Belum ada berita yang diterbitkan.</p>
                        @endif
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <!-- TV CLUB — DI BALIK LAYAR -->
    <section class="pub-section pub-section--navy relative overflow-hidden" id="tvclub" aria-labelledby="tvclubH">
        <div class="pub-container">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
                <div class="lg:col-span-6" data-reveal>
                    <div class="sec-label sec-label--navy mb-4">Di Balik Layar</div>
                    <h2 class="sec-h2 !text-[#EDF4FF] mb-3.5" id="tvclubH">Di Balik Layar WPiTV</h2>
                    <p class="text-sm sm:text-base text-white/70 leading-relaxed mb-7">
                        Kenali mahasiswa yang berada di balik produksi program, berita, dan tayangan WPiTV. TV Club
                        adalah komunitas media kampus yang menggerakkan siaran dari semester ke semester.
                    </p>

                    <div class="grid grid-cols-2 gap-3.5 mb-8">
                        <div class="bg-white/5 border border-white/10 rounded-md p-4">
                            <div class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-cyan leading-tight mb-1"
                                data-count data-target="{{ $tvclubStats['generations'] }}" data-suffix="">
                                {{ $tvclubStats['generations'] }}
                            </div>
                            <div class="text-xs text-white/55 font-medium">Generasi TV Club</div>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-md p-4">
                            <div class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-cyan leading-tight mb-1"
                                data-count data-target="{{ $tvclubStats['members'] }}" data-suffix="">
                                {{ $tvclubStats['members'] }}
                            </div>
                            <div class="text-xs text-white/55 font-medium">Anggota Aktif</div>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-md p-4">
                            <div class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-cyan leading-tight mb-1"
                                data-count data-target="{{ $tvclubStats['groups'] }}" data-suffix="">
                                {{ $tvclubStats['groups'] }}
                            </div>
                            <div class="text-xs text-white/55 font-medium">Kelompok Produksi</div>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-md p-4">
                            <div class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-cyan leading-tight mb-1"
                                data-count data-target="{{ $tvclubStats['assignments'] }}" data-suffix="">
                                {{ $tvclubStats['assignments'] }}
                            </div>
                            <div class="text-xs text-white/55 font-medium">Penugasan Kru</div>
                        </div>
                    </div>

                    <div class="flex gap-3 flex-wrap">
                        <a href="{{ route('tvclub.index') }}" class="pub-btn pub-btn--primary">Jelajahi TV Club</a>
                        <a href="{{ route('tvclub.index') }}" class="pub-btn pub-btn--ghost-white">Lihat Semua Kru</a>
                    </div>
                </div>

                <div class="lg:col-span-6" data-reveal data-d="1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @forelse($crewMembers as $member)
                            <a href="{{ route('tvclub.members.show', $member->id) }}" class="crew-card group">
                                <div class="crew-avatar" aria-hidden="true">
                                    @if($member->photo_url)
                                        <img src="{{ $member->photo_url }}" alt="{{ $member->name }}" loading="lazy">
                                    @else
                                        {{ $member->initials }}
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="font-heading font-bold text-xs sm:text-sm text-white group-hover:text-brand-cyan transition-colors truncate mb-0.5">
                                        {{ $member->name }}
                                    </div>
                                    <div class="text-[11px] text-white/50 truncate">
                                        {{ $member->groups->first()?->pivot->role ?? 'Crew Member' }}
                                    </div>
                                    @if($member->groups->first())
                                        <span
                                            class="inline-block text-[10px] font-semibold tracking-wider uppercase text-brand-cyan bg-brand-cyan/15 px-2 py-0.5 rounded-full mt-1">
                                            {{ $member->groups->first()->name }}
                                        </span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <p class="text-sm text-white/60 col-span-full py-4">Belum ada data anggota TV Club.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PELIPUTAN CTA -->
    @if($upcomingAgendas->isNotEmpty())
    <section class="pub-section pub-section--muted home-agenda" id="agenda" aria-labelledby="agendaH">
        <div class="pub-container">
            <div class="sec-head"><div><div class="sec-label">Kalender Publik</div><h2 class="sec-h2" id="agendaH">Agenda Terdekat</h2><p class="sec-sub">Jadwal kegiatan publik WPiTV yang dapat Anda ikuti.</p></div><a href="{{ route('agenda.index') }}" class="pub-btn pub-btn--secondary">Lihat Semua Agenda</a></div>
            <div class="home-agenda-grid">
                @foreach($upcomingAgendas as $agenda)
                    <article class="home-agenda-card"><div class="home-agenda-date"><strong>{{ $agenda->agenda_date->format('d') }}</strong><span>{{ $agenda->agenda_date->locale('id')->translatedFormat('M Y') }}</span></div><div><span>{{ $agenda->type_label }}</span><h3>{{ $agenda->title }}</h3><p><i class="bi bi-clock"></i> {{ $agenda->time_label }} @if($agenda->location) · <i class="bi bi-geo-alt"></i> {{ $agenda->location }} @endif</p></div></article>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <section class="pub-section" id="peliputan" aria-labelledby="peliputanH">
        <div class="pub-container">
            <div class="coverage-cta" data-reveal>
                <div>
                    <div class="text-brand-blue dark:text-brand-cyan opacity-75 mb-4" aria-hidden="true">
                        <svg width="44" height="44" viewBox="0 0 48 48" fill="none" stroke="currentColor"
                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="12" width="28" height="24" rx="4" />
                            <path d="M30 18l12-6v24l-12-6V18z" />
                            <circle cx="16" cy="24" r="5" />
                        </svg>
                    </div>
                    <h2 class="font-heading font-extrabold text-2xl md:text-3xl text-[var(--text-primary)] leading-tight mb-2.5"
                        id="peliputanH">
                        Punya kegiatan yang ingin diliput?
                    </h2>
                    <p class="text-sm sm:text-base text-[var(--text-muted)] leading-relaxed max-w-xl mb-7">
                        WPiTV menerima pengajuan peliputan kegiatan kampus Institut Widya Pratama. Dari seminar, lomba,
                        wisuda, hingga acara komunitas — tim kami siap meliput dan mendokumentasikan untuk kamu.
                    </p>
                    <div class="flex gap-3 flex-wrap">
                        <a href="{{ route('peliputan.create') }}" class="pub-btn pub-btn--primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path
                                    d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13.23 19.79 19.79 0 0 1 1.62 4.7 2 2 0 0 1 3.59 2.52h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 10.1a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                            </svg>
                            Ajukan Peliputan
                        </a>
                        <a href="{{ route('peliputan.status') }}" class="pub-btn pub-btn--secondary">Cek Status
                            Pengajuan</a>
                    </div>
                </div>

                <div class="coverage-cam hidden lg:block" aria-hidden="true">
                    <svg width="160" height="160" viewBox="0 0 160 160" fill="none" stroke="currentColor"
                        stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                        style="color:rgba(21,93,172,.15);">
                        <rect x="10" y="40" width="100" height="80" rx="12" />
                        <path d="M110 60l40-20v80l-40-20V60z" />
                        <circle cx="60" cy="80" r="22" />
                        <circle cx="60" cy="80" r="12" />
                        <rect x="25" y="28" width="40" height="14" rx="4" />
                    </svg>
                </div>
            </div>
        </div>
    </section>
</div>
