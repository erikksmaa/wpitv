<div>
    <!-- ARTICLE SECTION -->
    <article class="pub-section !pt-6 md:!pt-10" itemscope itemtype="https://schema.org/NewsArticle">
        <div class="pub-container">
            <div class="news-detail-container">
                <!-- Breadcrumb -->
                <nav class="flex items-center gap-2 text-xs text-[var(--text-muted)] mb-6 flex-wrap" aria-label="Breadcrumb">
                    <a href="{{ route('public.home') }}" class="hover:text-brand-blue transition-colors">Beranda</a>
                    <span class="text-[var(--text-faint)]">/</span>
                    <a href="{{ route('news.index') }}" class="hover:text-brand-blue transition-colors">Berita</a>
                    <span class="text-[var(--text-faint)]">/</span>
                    <span class="text-brand-blue font-medium truncate max-w-xs sm:max-w-md" aria-current="page">{{ $news->title }}</span>
                </nav>

                <!-- Article Header -->
                <header class="mb-8">
                    <!-- Title H1 -->
                    <h1 class="font-heading font-extrabold text-2xl sm:text-3xl md:text-4xl text-[var(--text-primary)] leading-tight mb-4 break-words" itemprop="headline">
                        {{ $news->title }}
                    </h1>

                    <!-- Metadata Bar -->
                    <div class="flex flex-wrap items-center gap-4 text-xs sm:text-sm text-[var(--text-muted)] pb-6 border-b border-[var(--border)]">
                        <div class="flex items-center gap-2" itemprop="author" itemscope itemtype="https://schema.org/Person">
                            <div class="w-8 h-8 rounded-full bg-brand-blue/10 text-brand-blue flex items-center justify-center font-bold text-xs" aria-hidden="true">
                                {{ Str::upper(Str::substr($news->author_display_name, 0, 1)) }}
                            </div>
                            <div>
                                <span class="text-[10px] text-[var(--text-faint)] block leading-none">Penulis</span>
                                <span class="font-semibold text-[var(--text-primary)]" itemprop="name">
                                    {{ $news->author_display_name }}
                                </span>
                            </div>
                        </div>

                        <span class="text-[var(--text-faint)]">&bull;</span>

                        <div class="flex items-center gap-1.5">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-[var(--text-faint)]" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
                            <time datetime="{{ $news->created_at?->toIso8601String() }}" itemprop="datePublished">
                                {{ $news->created_at ? $news->created_at->isoFormat('dddd, D MMMM Y') : 'Tanggal tidak tersedia' }}
                            </time>
                        </div>
                    </div>
                </header>

                <!-- Featured Hero Image -->
                <div class="mb-8 rounded-2xl overflow-hidden border border-[var(--border)] bg-[var(--surface-muted)] shadow-md aspect-video sm:aspect-[21/10]">
                    <img 
                        src="{{ $news->image_url }}" 
                        alt="{{ $news->title }}" 
                        class="w-full h-full object-cover"
                        loading="eager"
                        fetchpriority="high"
                        itemprop="image"
                    >
                </div>

                <!-- Article Body Content -->
                <div class="news-article-content mb-12" itemprop="articleBody">
                    {!! $news->content !!}
                </div>

                <!-- PREVIOUS / NEXT NAVIGATION -->
                @if($prevNews || $nextNews)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 py-8 border-t border-[var(--border)] mb-12">
                        @if($prevNews)
                            <a href="{{ route('news.show', $prevNews->slug) }}" class="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] hover:border-brand-blue transition-colors group block min-w-0">
                                <span class="text-[11px] font-semibold text-[var(--text-faint)] block mb-1">&larr; Berita Sebelumnya</span>
                                <span class="font-heading font-bold text-xs sm:text-sm text-[var(--text-primary)] group-hover:text-brand-blue transition-colors line-clamp-1">
                                    {{ $prevNews->title }}
                                </span>
                            </a>
                        @else
                            <div class="hidden sm:block"></div>
                        @endif

                        @if($nextNews)
                            <a href="{{ route('news.show', $nextNews->slug) }}" class="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] hover:border-brand-blue transition-colors group block text-left sm:text-right min-w-0">
                                <span class="text-[11px] font-semibold text-[var(--text-faint)] block mb-1">Berita Selanjutnya &rarr;</span>
                                <span class="font-heading font-bold text-xs sm:text-sm text-[var(--text-primary)] group-hover:text-brand-blue transition-colors line-clamp-1">
                                    {{ $nextNews->title }}
                                </span>
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </article>

    <!-- RELATED NEWS SECTION -->
    @if($relatedNews->isNotEmpty())
        <section class="pub-section pub-section--muted border-t border-[var(--border)]" aria-labelledby="relatedNewsHeading">
            <div class="pub-container">
                <div class="flex items-end justify-between gap-5 flex-wrap mb-8">
                    <div>
                        <div class="sec-label">Redaksi WPiTV</div>
                        <h2 class="sec-h2" id="relatedNewsHeading">Berita Lainnya</h2>
                    </div>
                    <a href="{{ route('news.index') }}" class="sec-link">
                        Lihat Semua Berita
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                </div>

                <div class="news-grid" role="list">
                    @foreach($relatedNews as $rel)
                        <article role="listitem">
                            <a href="{{ route('news.show', $rel->slug) }}" class="news-card group">
                                <div class="news-card-thumb">
                                    <img 
                                        src="{{ $rel->image_url }}" 
                                        alt="{{ $rel->title }}" 
                                        loading="lazy"
                                    >
                                </div>
                                <div class="p-5 flex flex-col flex-grow">
                                    <div class="flex items-center gap-2 text-[11px] text-[var(--text-faint)] mb-2">
                                        <span class="truncate max-w-[140px] font-medium text-brand-blue dark:text-brand-cyan">
                                            {{ $rel->author_display_name }}
                                        </span>
                                        <span>&bull;</span>
                                        <time datetime="{{ $rel->created_at?->toIso8601String() }}">
                                            {{ $rel->created_at ? $rel->created_at->isoFormat('D MMM Y') : '' }}
                                        </time>
                                    </div>
                                    <h3 class="font-heading font-bold text-sm text-[var(--text-primary)] leading-snug line-clamp-2 group-hover:text-brand-blue transition-colors mb-2">
                                        {{ $rel->title }}
                                    </h3>
                                    <p class="text-xs text-[var(--text-muted)] leading-relaxed line-clamp-2 mb-4">
                                        {{ Str::limit(strip_tags($rel->content), 100) }}
                                    </p>
                                    <span class="mt-auto text-xs font-semibold text-brand-blue dark:text-brand-cyan inline-flex items-center gap-1">
                                        Baca Berita &rarr;
                                    </span>
                                </div>
                            </a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
