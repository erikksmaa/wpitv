<div id="globalPublicSearch" class="global-search" data-global-search wire:ignore.self aria-hidden="true">
    <section class="global-search__panel" role="dialog" aria-modal="true" aria-labelledby="globalSearchTitle">
        <div class="global-search__header">
            <strong id="globalSearchTitle">Cari Konten WPiTV</strong>
            <button type="button" class="global-search__close" data-global-search-close wire:click="clear" aria-label="Tutup pencarian">×</button>
        </div>

        <div class="global-search__field">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
            <input type="search" wire:model.live.debounce.300ms="query" data-global-search-input placeholder="Cari program, tayangan, atau berita..." autocomplete="off" aria-describedby="globalSearchHint">
            <span class="global-search__loading" wire:loading wire:target="query" aria-label="Mencari"></span>
        </div>

        <div class="global-search__results" aria-live="polite">
            @if(mb_strlen($term) < 2)
                <p class="global-search__hint" id="globalSearchHint">Ketik minimal 2 karakter untuk mulai mencari.</p>
            @elseif(! $hasResults)
                <div class="global-search__empty" role="status"><strong>Tidak ada hasil yang ditemukan.</strong><span>Coba gunakan kata kunci yang berbeda.</span></div>
            @else
                @if($programs->isNotEmpty())
                    <section class="global-search__group" aria-labelledby="globalSearchPrograms">
                        <h2 class="global-search__group-label" id="globalSearchPrograms">Program</h2>
                        <div class="global-search__list">
                            @foreach($programs as $program)
                                <a class="global-search__result" href="{{ route('program.show', $program->slug) }}">
                                    <span class="global-search__thumb"><img src="{{ $program->image_url }}" alt="" loading="lazy"></span>
                                    <span class="global-search__copy"><strong>{{ $program->name }}</strong><small>Program WPiTV</small></span><span class="global-search__arrow" aria-hidden="true">→</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if($videos->isNotEmpty())
                    <section class="global-search__group" aria-labelledby="globalSearchVideos">
                        <h2 class="global-search__group-label" id="globalSearchVideos">Tayangan</h2>
                        <div class="global-search__list">
                            @foreach($videos as $video)
                                <a class="global-search__result" href="{{ route('video.show', $video) }}">
                                    <span class="global-search__thumb"><img src="{{ $video->thumbnail_url }}" alt="" loading="lazy"></span>
                                    <span class="global-search__copy"><strong>{{ $video->title }}</strong><small>{{ $video->program?->name ?? 'Tayangan WPiTV' }}</small></span><span class="global-search__arrow" aria-hidden="true">→</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if($news->isNotEmpty())
                    <section class="global-search__group" aria-labelledby="globalSearchNews">
                        <h2 class="global-search__group-label" id="globalSearchNews">Berita</h2>
                        <div class="global-search__list">
                            @foreach($news as $item)
                                <a class="global-search__result" href="{{ route('news.show', $item->slug) }}">
                                    <span class="global-search__thumb"><img src="{{ $item->image_url }}" alt="" loading="lazy"></span>
                                    <span class="global-search__copy"><strong>{{ $item->title }}</strong><small>{{ $item->author_name ?: 'Redaksi WPiTV' }}</small></span><span class="global-search__arrow" aria-hidden="true">→</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endif
        </div>
    </section>
</div>
