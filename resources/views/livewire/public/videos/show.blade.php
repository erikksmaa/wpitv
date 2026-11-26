<div>
    <!-- VIDEO DETAIL SECTION -->
    <section class="pub-section !pt-6 md:!pt-10">
        <div class="pub-container">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-6 flex-wrap" aria-label="Breadcrumb">
                <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                <span class="text-[var(--text-faint)]">/</span>
                <a href="{{ route('video.index') }}" class="hover:text-brand-blue transition-colors">Tayangan</a>
                @if($video->program)
                    <span class="text-[var(--text-faint)]">/</span>
                    <a href="{{ route('program.show', $video->program->slug) }}" class="hover:text-brand-blue transition-colors truncate max-w-[200px]">
                        {{ $video->program->name }}
                    </a>
                @endif
                <span class="text-[var(--text-faint)]">/</span>
                <span class="text-brand-blue font-medium truncate max-w-sm" aria-current="page">{{ $video->title }}</span>
            </nav>

            <!-- DETERMINISTIC 2-COLUMN GRID (MAIN 2FR + SIDEBAR 1FR MIN 320PX) -->
            <div class="video-detail-layout">
                <!-- MAIN CONTENT COLUMN -->
                <div class="video-detail-main">
                    <!-- RESPONSIVE 16:9 YOUTUBE PLAYER WRAPPER -->
                    <div class="video-player-wrap">
                        <iframe 
                            src="{{ $video->embed_url }}" 
                            title="{{ $video->title }}" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                            allowfullscreen
                        ></iframe>
                    </div>

                    <!-- VIDEO INFORMATION CARD -->
                    <div class="video-info-card">
                        <!-- Metadata Badges -->
                        <div class="video-meta-bar">
                            @if($video->program)
                                <a 
                                    href="{{ route('program.show', $video->program->slug) }}" 
                                    class="inline-flex items-center gap-1.5 bg-brand-blue/10 hover:bg-brand-blue/20 text-brand-blue dark:text-brand-cyan text-xs font-semibold px-3 py-1 rounded-full transition-colors"
                                >
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="20" height="15" rx="2" ry="2"/><polyline points="17 2 12 7 7 2"/></svg>
                                    {{ $video->program->name }}
                                </a>
                            @else
                                <span class="inline-flex items-center gap-1.5 bg-gray-100 dark:bg-gray-800 text-[var(--text-muted)] text-xs font-semibold px-3 py-1 rounded-full">
                                    Tayangan Lepas WPiTV
                                </span>
                            @endif

                            <span class="text-xs text-[var(--text-muted)] flex items-center gap-1.5 whitespace-nowrap">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
                                {{ $video->air_date ? $video->air_date->isoFormat('D MMMM Y') : 'Tanggal tidak tersedia' }}
                            </span>
                        </div>

                        <!-- Video Title (H1) -->
                        <h1 class="font-heading font-extrabold text-xl sm:text-2xl md:text-3xl text-[var(--text-primary)] leading-snug mb-4 break-words">
                            {{ $video->title }}
                        </h1>

                        <!-- Action Buttons -->
                        <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-[var(--border)]">
                            <a 
                                href="{{ $video->watch_url }}" 
                                target="_blank" 
                                rel="noopener noreferrer" 
                                class="pub-btn pub-btn--secondary !text-xs !py-2.5 !px-4 inline-flex items-center gap-2"
                            >
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="text-brand-red" aria-hidden="true"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="white"/></svg>
                                Tonton di YouTube
                            </a>

                            @if($video->program)
                                <a 
                                    href="{{ route('program.show', $video->program->slug) }}" 
                                    class="pub-btn pub-btn--secondary !text-xs !py-2.5 !px-4"
                                >
                                    Lihat Semua Tayangan Program &rarr;
                                </a>
                            @endif
                        </div>

                        <!-- Description (Graceful rendering) -->
                        @if(!empty($video->description))
                            <div class="mt-6 pt-6 border-t border-[var(--border)]">
                                <h2 class="font-heading font-bold text-xs uppercase tracking-wider text-[var(--text-faint)] mb-2.5">
                                    Tentang Tayangan
                                </h2>
                                <div class="text-sm text-[var(--text-secondary)] leading-relaxed whitespace-pre-line break-words">
                                    {{ $video->description }}
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- PREVIOUS / NEXT NAVIGATION -->
                    @if($prevVideo || $nextVideo)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                            @if($prevVideo)
                                <a href="{{ route('video.show', $prevVideo) }}" class="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] hover:border-brand-blue transition-colors group block min-w-0">
                                    <span class="text-[11px] font-semibold text-[var(--text-faint)] block mb-1">&larr; Tayangan Sebelumnya</span>
                                    <span class="font-heading font-bold text-xs sm:text-sm text-[var(--text-primary)] group-hover:text-brand-blue transition-colors line-clamp-1">
                                        {{ $prevVideo->title }}
                                    </span>
                                </a>
                            @else
                                <div class="hidden sm:block"></div>
                            @endif

                            @if($nextVideo)
                                <a href="{{ route('video.show', $nextVideo) }}" class="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] hover:border-brand-blue transition-colors group block text-left sm:text-right min-w-0">
                                    <span class="text-[11px] font-semibold text-[var(--text-faint)] block mb-1">Tayangan Selanjutnya &rarr;</span>
                                    <span class="font-heading font-bold text-xs sm:text-sm text-[var(--text-primary)] group-hover:text-brand-blue transition-colors line-clamp-1">
                                        {{ $nextVideo->title }}
                                    </span>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- SIDEBAR: RELATED PROGRAM & VIDEOS -->
                <aside class="video-detail-sidebar">
                    @if($video->program)
                        <!-- Program Card Widget -->
                        <div class="bg-[var(--surface)] p-5 rounded-2xl border border-[var(--border)] shadow-sm flex gap-4 items-center">
                            <img 
                                src="{{ $video->program->image_url }}" 
                                alt="Poster {{ $video->program->name }}" 
                                class="w-14 h-18 rounded-lg object-cover flex-shrink-0 border border-[var(--border)]"
                                loading="lazy"
                            >
                            <div class="min-w-0 flex-1">
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-brand-blue dark:text-brand-cyan block mb-0.5">Program Acara</span>
                                <h3 class="font-heading font-bold text-sm text-[var(--text-primary)] truncate mb-1">
                                    {{ $video->program->name }}
                                </h3>
                                <a href="{{ route('program.show', $video->program->slug) }}" class="text-xs text-brand-blue dark:text-brand-cyan hover:underline font-semibold inline-flex items-center gap-1">
                                    Jelajahi Program &rarr;
                                </a>
                            </div>
                        </div>
                    @endif

                    <!-- Related Videos Card List -->
                    <div class="bg-[var(--surface)] p-5 rounded-2xl border border-[var(--border)] shadow-sm">
                        <div class="flex items-center justify-between mb-4 pb-3 border-b border-[var(--border)]">
                            <h2 class="font-heading font-bold text-sm text-[var(--text-primary)]">
                                Tayangan Terkait
                            </h2>
                            <a href="{{ route('video.index') }}" class="text-xs text-brand-blue dark:text-brand-cyan hover:underline">
                                Lihat Semua
                            </a>
                        </div>

                        <div class="space-y-4">
                            @forelse($relatedVideos as $rel)
                                <a href="{{ route('video.show', $rel) }}" class="related-item group">
                                    <div class="related-thumb">
                                        <img 
                                            src="{{ $rel->thumbnail_url }}" 
                                            alt="{{ $rel->title }}" 
                                            loading="lazy"
                                        >
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="text-[10px] text-brand-blue dark:text-brand-cyan font-semibold uppercase truncate block mb-0.5">
                                            {{ $rel->program?->name ?? 'WPiTV' }}
                                        </span>
                                        <h3 class="font-heading font-semibold text-xs text-[var(--text-primary)] leading-snug line-clamp-2 group-hover:text-brand-blue transition-colors mb-1">
                                            {{ $rel->title }}
                                        </h3>
                                        <span class="text-[10px] text-[var(--text-faint)] block">
                                            {{ $rel->air_date ? $rel->air_date->isoFormat('D MMM Y') : '' }}
                                        </span>
                                    </div>
                                </a>
                            @empty
                                <p class="text-xs text-[var(--text-muted)] py-2">Tidak ada tayangan terkait.</p>
                            @endforelse
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</div>
