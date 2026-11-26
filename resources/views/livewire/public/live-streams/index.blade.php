@use('App\Models\LiveStream')
<div class="public-live-page" x-data="{ activeTab: @js($activeTab) }">
    <section class="pub-hero public-live-hero !py-8 md:!py-10">
        <div class="pub-container">
            <div class="hero-eyebrow mb-2">WPiTV On Air</div>
            <h1 class="hero-title !text-3xl md:!text-4xl !text-white mb-2">Live Streaming</h1>
            <p class="text-sm md:text-base text-white/85 max-w-2xl">
                Saksikan siaran langsung, jadwal mendatang, dan arsip tayangan bersama WPiTV. Status siaran dikelola tim WPiTV.
            </p>
        </div>
    </section>

    <section class="pub-section !py-8 md:!py-10">
        <div class="pub-container">
            <div class="public-live-tabs mb-6" role="tablist" aria-label="Kategori Live Streaming">
                @foreach($sections as $key => $section)
                    <button type="button"
                        id="live-tab-{{ $key }}"
                        role="tab"
                        aria-controls="live-panel-{{ $key }}"
                        x-bind:aria-selected="(activeTab === '{{ $key }}').toString()"
                        x-bind:class="activeTab === '{{ $key }}' ? 'is-active' : ''"
                        x-on:click="activeTab = '{{ $key }}'"
                        class="public-live-tab">
                        <span>{{ $section['title'] }}</span>
                        <span class="public-live-tab__count">{{ $section['streams']->total() }}</span>
                    </button>
                @endforeach
            </div>

            @foreach($sections as $key => $section)
                <div id="live-panel-{{ $key }}"
                    role="tabpanel"
                    aria-labelledby="live-tab-{{ $key }}"
                    x-show="activeTab === '{{ $key }}'"
                    @if($activeTab !== $key) x-cloak @endif>
                    @if($section['streams']->isNotEmpty())
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                            @foreach($section['streams'] as $stream)
                                @php($targetUrl = $key === 'archive' && $stream->hasValidArchive() ? route('video.show', $stream->archive_video_id) : route('live.show', $stream->id))
                                @php($isPastDue = $key === 'upcoming' && (!$stream->scheduled_starts_at || $stream->scheduled_starts_at->lte(now('UTC'))))
                                <article class="public-live-card" wire:key="public-live-{{ $key }}-{{ $stream->id }}">
                                    <a href="{{ route('live.show', $stream->id) }}" class="public-live-thumb" aria-label="Tonton {{ $stream->display_title }}" x-data="{ broken: false }">
                                        @if($stream->thumbnail_url)
                                            <img src="{{ $stream->thumbnail_url }}" alt="Thumbnail {{ $stream->display_title }}" loading="lazy" x-show="!broken" x-on:error="broken = true">
                                        @endif
                                        <div class="public-live-thumb__placeholder" aria-hidden="true" @if($stream->thumbnail_url) x-show="broken" x-cloak @endif>
                                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5M19.1 4.9c3.9 3.9 3.9 10.3 0 14.2M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><circle cx="12" cy="12" r="2"/></svg>
                                        </div>
                                        <span class="public-live-play-icon public-live-play-icon--sm" aria-hidden="true">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="ml-0.5"><polygon points="6,4 20,12 6,20"/></svg>
                                        </span>
                                    </a>
                                    <div class="public-live-card__body">
                                        <div class="flex flex-wrap items-center gap-2 mb-2">
                                            <span class="public-live-badge public-live-badge--{{ $stream->status }}">
                                                @if($stream->status === 'live')<span class="public-live-dot" aria-hidden="true"></span>@endif
                                                {{ $isPastDue ? 'Menunggu Pembaruan' : (LiveStream::STATUS_LABELS[$stream->status] ?? ucfirst($stream->status)) }}
                                            </span>
                                            @if($stream->display_program)
                                                <a href="{{ route('program.show', $stream->display_program->slug) }}" class="public-live-program-pill">{{ $stream->display_program->name }}</a>
                                            @endif
                                        </div>

                                        <h2 class="public-live-card__title">
                                            <a href="{{ route('live.show', $stream->id) }}">{{ $stream->display_title }}</a>
                                        </h2>

                                        <p class="public-live-meta mb-2">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
                                            <span>{{ $stream->schedule_date }} &middot; {{ $stream->time_label }}</span>
                                        </p>

                                        @if($stream->display_description)
                                            <p class="text-xs text-[var(--text-muted)] leading-relaxed mb-3 public-live-clamp-2">{{ $stream->display_description }}</p>
                                        @endif

                                        <div class="flex flex-wrap items-center gap-2 mt-auto pt-2">
                                            <a class="pub-btn pub-btn--primary" href="{{ route('live.show', $stream->id) }}">
                                                <svg width="12" height="12" viewBox="0 0 14 14" fill="currentColor" aria-hidden="true"><polygon points="3,1 13,7 3,13" /></svg>
                                                {{ $key === 'archive' ? 'Lihat Detail' : 'Tonton Siaran' }}
                                            </a>
                                            @if($key === 'archive' && $stream->hasValidArchive())
                                                <a class="pub-btn pub-btn--secondary" href="{{ $targetUrl }}">Tonton Arsip</a>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <div class="mt-6">{{ $section['streams']->appends(['tab' => $key])->links() }}</div>
                    @else
                        <div class="public-live-empty">
                            <div class="public-live-empty__icon" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                            </div>
                            <div>
                                <p class="font-medium text-[var(--text-primary)] mb-0.5">
                                    {{ $key === 'live' ? 'Belum ada siaran langsung.' : ($key === 'upcoming' ? 'Belum ada jadwal siaran mendatang.' : 'Belum ada arsip tayangan.') }}
                                </p>
                                <p class="text-sm text-[var(--text-muted)]">
                                    {{ $key === 'live' ? 'Cek tab Jadwal Mendatang atau Arsip Tayangan untuk melihat siaran lainnya.' : 'Informasi terbaru akan tampil di sini setelah dijadwalkan tim WPiTV.' }}
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
</div>
