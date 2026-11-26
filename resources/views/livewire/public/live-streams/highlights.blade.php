@use('App\Models\LiveStream')
@php($isHome = $isHomepage ?? false)
<section class="pub-section {{ $isHome ? 'pub-section--muted !py-10 md:!py-12' : '' }}" id="live-streaming" aria-labelledby="liveSectionTitle">
    <div class="pub-container">
        <div class="flex items-end justify-between gap-4 flex-wrap mb-5">
            <div>
                <div class="sec-label">WPiTV On Air</div>
                <h2 class="sec-h2" id="liveSectionTitle">Live Streaming</h2>
                <p class="text-sm text-[var(--text-muted)] mt-1">Saksikan siaran langsung dan kegiatan terbaru bersama WPiTV. Status siaran dikelola tim WPiTV.</p>
            </div>
            <a href="{{ route('live.index') }}" class="sec-link">
                Lihat Semua Siaran
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7" /></svg>
            </a>
        </div>

        @unless($liveHighlights['available'])
            <div class="public-live-empty" role="status">
                <p class="font-medium text-[var(--text-primary)] mb-1">Informasi Live Streaming belum dapat dimuat</p>
                <p class="text-sm text-[var(--text-muted)]">Silakan muat ulang halaman atau buka halaman Live Streaming.</p>
            </div>
        @elseif($isHome)
            @php($groupLabel = array_key_first($liveHighlights['groups']))
            @php($items = $groupLabel ? $liveHighlights['groups'][$groupLabel] : collect())
            @php($featured = $items->first())
            @if($featured)
                @php($isLive = $featured->status === 'live')
                @php($description = $featured->display_description)
                <article class="public-live-featured {{ $isLive ? 'is-live' : '' }}">
                    <a href="{{ route('live.show', $featured->id) }}" class="public-live-featured__media group" aria-label="Tonton {{ $featured->display_title }}" x-data="{ broken: false }">
                        @if($featured->thumbnail_url)
                            <img src="{{ $featured->thumbnail_url }}" alt="Thumbnail {{ $featured->display_title }}" loading="lazy" class="public-live-featured__img" x-show="!broken" x-on:error="broken = true">
                        @endif
                        <div class="public-live-featured__placeholder" aria-hidden="true" @if($featured->thumbnail_url) x-show="broken" x-cloak @endif>
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5M19.1 4.9c3.9 3.9 3.9 10.3 0 14.2M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><circle cx="12" cy="12" r="2"/></svg>
                        </div>
                        <span class="public-live-play-icon" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="ml-0.5"><polygon points="6,4 20,12 6,20"/></svg>
                        </span>
                    </a>
                    <div class="public-live-featured__body">
                        <div class="flex flex-wrap items-center gap-2 mb-2.5">
                            <span class="public-live-badge {{ $isLive ? 'public-live-badge--live' : 'public-live-badge--scheduled' }}">
                                @if($isLive)<span class="public-live-dot" aria-hidden="true"></span>@endif
                                {{ $isLive ? 'Sedang Live' : 'Jadwal Terdekat' }}
                            </span>
                            @if($featured->display_program)
                                <a href="{{ route('program.show', $featured->display_program->slug) }}" class="public-live-program-pill">{{ $featured->display_program->name }}</a>
                            @endif
                        </div>

                        <h3 class="public-live-featured__title">
                            <a href="{{ route('live.show', $featured->id) }}">{{ $featured->display_title }}</a>
                        </h3>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-[var(--text-muted)] mt-1">
                            <span class="inline-flex items-center gap-1.5">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
                                {{ $featured->schedule_date }}
                            </span>
                            @if($featured->time_label)
                                <span class="inline-flex items-center gap-1.5">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    {{ $featured->time_label }}
                                </span>
                            @endif
                        </div>

                        @if($description)
                            <p class="text-sm text-[var(--text-muted)] leading-relaxed mt-3 public-live-clamp-3">{{ $description }}</p>
                        @endif

                        <div class="flex flex-wrap items-center gap-2.5 mt-4">
                            <a class="pub-btn pub-btn--primary" href="{{ route('live.show', $featured->id) }}">
                                <svg width="13" height="13" viewBox="0 0 14 14" fill="currentColor" aria-hidden="true"><polygon points="3,1 13,7 3,13" /></svg>
                                Tonton Siaran
                            </a>
                            @if($featured->watch_url)
                                <a class="pub-btn pub-btn--secondary" href="{{ $featured->watch_url }}" target="_blank" rel="noopener noreferrer">
                                    Buka di YouTube
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @else
                <div class="public-live-empty">
                    <div class="public-live-empty__icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5M19.1 4.9c3.9 3.9 3.9 10.3 0 14.2M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><circle cx="12" cy="12" r="2"/></svg>
                    </div>
                    <div>
                        <p class="font-heading font-semibold text-base text-[var(--text-primary)] mb-1">Belum ada siaran langsung atau jadwal terdekat</p>
                        <p class="text-sm text-[var(--text-muted)] mb-3">Jadwal siaran berikutnya akan tampil di sini setelah dipublikasikan tim WPiTV.</p>
                        <a href="{{ route('live.index') }}" class="pub-btn pub-btn--secondary">Lihat Jadwal &amp; Arsip Live Streaming</a>
                    </div>
                </div>
            @endif
        @else
            @foreach($liveHighlights['groups'] as $label => $items)
                <h3 class="sec-label mt-6 mb-3">{{ $label }}</h3>
                <div class="public-live-grid">
                    @forelse($items as $stream)
                        @php($itemUrl = $stream->status === 'ended' && $stream->hasValidArchive() ? route('video.show', $stream->archive_video_id) : route('live.show', $stream->id))
                        <article class="public-live-card">
                            <a href="{{ $itemUrl }}" class="public-live-thumb">
                                @if($stream->thumbnail_url)
                                    <img src="{{ $stream->thumbnail_url }}" alt="Thumbnail {{ $stream->display_title }}" loading="lazy">
                                @else
                                    <div class="public-live-thumb__placeholder" aria-hidden="true"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><polygon points="5,3 19,12 5,21"/></svg></div>
                                @endif
                                <span class="public-live-play-icon public-live-play-icon--sm" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="ml-0.5"><polygon points="6,4 20,12 6,20"/></svg>
                                </span>
                            </a>
                            <div class="public-live-card__body">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="public-live-badge public-live-badge--{{ $stream->status }}">
                                        @if($stream->status === 'live')<span class="public-live-dot" aria-hidden="true"></span>@endif
                                        {{ LiveStream::STATUS_LABELS[$stream->status] ?? ucfirst($stream->status) }}
                                    </span>
                                    @if($stream->display_program)<span class="public-live-program-pill">{{ $stream->display_program->name }}</span>@endif
                                </div>
                                <h4 class="public-live-card__title"><a href="{{ $itemUrl }}">{{ $stream->display_title }}</a></h4>
                                <p class="public-live-meta mb-2">{{ $stream->schedule_date }} &middot; {{ $stream->time_label }}</p>
                                @if($stream->display_description)
                                    <p class="text-xs text-[var(--text-muted)] leading-relaxed mb-3 public-live-clamp-2">{{ $stream->display_description }}</p>
                                @endif
                                <div class="mt-auto pt-1">
                                    <a class="pub-btn pub-btn--primary" href="{{ $itemUrl }}">{{ $stream->status === 'ended' ? 'Tonton Arsip' : 'Tonton Siaran' }}</a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm text-[var(--text-muted)]">Belum ada siaran pada bagian ini.</p>
                    @endforelse
                </div>
                @if($items instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator){{ $items->links() }}@endif
            @endforeach
        @endif
    </div>
</section>
