@use('App\Models\LiveStream')
<div class="public-live-page">
    <section class="pub-section !pt-6 md:!pt-8">
        <div class="pub-container">
            <nav aria-label="Breadcrumb" class="mb-4">
                <a href="{{ route('live.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-brand-blue dark:text-brand-cyan hover:underline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    Kembali ke Live Streaming
                </a>
            </nav>

            <!-- PLAYER FOKUS UTAMA -->
            <div class="public-live-player-wrap mb-6">
                @if($stream->status === 'cancelled')
                    <div class="public-live-player-fallback">
                        <span class="public-live-badge public-live-badge--cancelled mb-3">Dibatalkan</span>
                        <p class="font-heading font-bold text-lg text-white mb-1">Siaran dibatalkan.</p>
                        <p class="text-sm text-slate-300 max-w-md">Siaran ini dibatalkan oleh tim WPiTV. Silakan cek jadwal Live Streaming lainnya.</p>
                    </div>
                @elseif($stream->embed_url)
                    <div class="public-live-player" wire:ignore>
                        <iframe src="{{ $stream->embed_url }}" title="Siaran {{ $stream->display_title }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
                    </div>
                @else
                    <div class="public-live-player-fallback" x-data="{ broken: false }">
                        @if($stream->thumbnail_url)
                            <img src="{{ $stream->thumbnail_url }}" alt="Thumbnail {{ $stream->display_title }}" class="w-full h-full object-cover opacity-35" x-show="!broken" x-on:error="broken = true">
                        @endif
                        <div class="relative z-10 p-6">
                            <span class="public-live-badge public-live-badge--{{ $stream->status }} mb-3">{{ LiveStream::STATUS_LABELS[$stream->status] ?? ucfirst($stream->status) }}</span>
                            <p class="font-heading font-bold text-lg text-white mb-1">Tautan YouTube belum tersedia.</p>
                            <p class="text-sm text-slate-300 max-w-md">Pemutar video akan tampil setelah tautan siaran ditambahkan oleh tim WPiTV.</p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- INFORMASI & CTA -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <div class="lg:col-span-8">
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="public-live-badge public-live-badge--{{ $stream->status }}">
                            @if($stream->status === 'live')<span class="public-live-dot" aria-hidden="true"></span>@endif
                            {{ LiveStream::STATUS_LABELS[$stream->status] ?? ucfirst($stream->status) }}
                        </span>
                        @if($stream->display_program)
                            <a href="{{ route('program.show', $stream->display_program->slug) }}" class="public-live-program-pill">{{ $stream->display_program->name }}</a>
                        @endif
                        <span class="text-xs text-[var(--text-muted)]">Dikelola tim WPiTV</span>
                    </div>

                    <h1 class="font-heading font-bold text-2xl md:text-3xl text-[var(--text-primary)] leading-tight mb-3">{{ $stream->display_title }}</h1>

                    <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm text-[var(--text-muted)] mb-5">
                        <span class="inline-flex items-center gap-1.5">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
                            {{ $stream->schedule_date }}
                        </span>
                        @if($stream->time_label)
                            <span class="inline-flex items-center gap-1.5">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                {{ $stream->time_label }}
                            </span>
                        @endif
                        @if($stream->agenda?->location)
                            <span class="inline-flex items-center gap-1.5">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                {{ $stream->agenda->location }}
                            </span>
                        @endif
                    </div>

                    @if($stream->display_description)
                        <div class="public-live-card public-live-card--static mb-5">
                            <div class="public-live-card__body">
                                <h2 class="font-heading font-semibold text-base text-[var(--text-primary)] mb-2">Tentang Siaran</h2>
                                <p class="public-live-description text-sm text-[var(--text-muted)] leading-relaxed">{{ $stream->display_description }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                <aside class="lg:col-span-4">
                    <div class="public-live-card public-live-card--static">
                        <div class="public-live-card__body">
                            <h2 class="font-heading font-semibold text-base text-[var(--text-primary)] mb-2">Tonton &amp; Informasi</h2>

                            @if($stream->status === 'ended')
                                <p class="text-sm text-[var(--text-muted)] mb-4">
                                    @if($stream->hasValidArchive())
                                        Siaran telah selesai. Rekaman arsip tersedia di katalog Tayangan WPiTV.
                                    @else
                                        Siaran telah selesai. Rekaman belum siap atau belum ditautkan ke katalog Tayangan.
                                    @endif
                                </p>
                            @elseif($stream->status === 'scheduled' && (!$stream->scheduled_starts_at || $stream->scheduled_starts_at->lte(now('UTC'))))
                                <p class="text-sm text-[var(--text-muted)] mb-4">Jadwal mulai telah lewat dan sedang menunggu pembaruan status dari tim WPiTV.</p>
                            @elseif($stream->status !== 'cancelled')
                                <p class="text-sm text-[var(--text-muted)] mb-4">Bila siaran belum dimulai atau pemutar gagal dimuat, buka tayangan langsung melalui YouTube.</p>
                            @endif

                            <div class="flex flex-col gap-2.5">
                                @if($stream->status === 'ended' && $stream->hasValidArchive())
                                    <a class="pub-btn pub-btn--primary justify-center" href="{{ route('video.show', $stream->archive_video_id) }}">Tonton Arsip Tayangan</a>
                                @endif
                                @if($stream->status !== 'cancelled' && $stream->watch_url)
                                    <a class="pub-btn {{ $stream->status === 'ended' && $stream->hasValidArchive() ? 'pub-btn--secondary' : 'pub-btn--primary' }} justify-center" href="{{ $stream->watch_url }}" target="_blank" rel="noopener noreferrer">
                                        Buka di YouTube
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    </a>
                                @endif
                                <a class="pub-btn pub-btn--secondary justify-center" href="{{ route('live.index') }}">Semua Live Streaming</a>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    @if($relatedStreams->isNotEmpty())
        <section class="pub-section pub-section--muted !py-10" aria-labelledby="relatedLiveTitle">
            <div class="pub-container">
                <div class="flex items-end justify-between gap-4 flex-wrap mb-5">
                    <div>
                        <span class="sec-label">Jadwal &amp; Siaran Lain</span>
                        <h2 class="sec-h2" id="relatedLiveTitle">Siaran &amp; Jadwal Lainnya</h2>
                    </div>
                    <a href="{{ route('live.index') }}" class="sec-link">Lihat Semua →</a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($relatedStreams as $related)
                        <article class="public-live-card">
                            <a href="{{ route('live.show', $related->id) }}" class="public-live-thumb">
                                @if($related->thumbnail_url)
                                    <img src="{{ $related->thumbnail_url }}" alt="Thumbnail {{ $related->display_title }}" loading="lazy">
                                @else
                                    <div class="public-live-thumb__placeholder" aria-hidden="true"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><polygon points="5,3 19,12 5,21"/></svg></div>
                                @endif
                                <span class="public-live-play-icon public-live-play-icon--sm" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="ml-0.5"><polygon points="6,4 20,12 6,20"/></svg>
                                </span>
                            </a>
                            <div class="public-live-card__body">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="public-live-badge public-live-badge--{{ $related->status }}">{{ LiveStream::STATUS_LABELS[$related->status] ?? ucfirst($related->status) }}</span>
                                    @if($related->display_program)<span class="public-live-program-pill">{{ $related->display_program->name }}</span>@endif
                                </div>
                                <h3 class="public-live-card__title"><a href="{{ route('live.show', $related->id) }}">{{ $related->display_title }}</a></h3>
                                <p class="public-live-meta mb-3">{{ $related->schedule_date }} &middot; {{ $related->time_label }}</p>
                                <div class="mt-auto"><a class="pub-btn pub-btn--primary" href="{{ route('live.show', $related->id) }}">Tonton siaran</a></div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
