<section class="pub-section" aria-label="Live Streaming"><div class="pub-container">
    <h2 class="sec-h2">Live Streaming</h2><p>Status manual WPiTV, bukan verifikasi otomatis YouTube.</p>
    @unless($liveHighlights['available'])<p role="status">Informasi Live belum dapat dimuat. Silakan coba lagi.</p>@else
        @foreach($liveHighlights['groups'] as $label => $items)
            <h3 class="sec-label">{{ $label }}</h3><div class="public-live-grid">
            @forelse($items as $stream)
                <article class="public-live-card"><h3>{{ $stream->display_title }}</h3><p>{{ $stream->schedule_date }} · {{ $stream->time_label }}</p><p>{{ ucfirst($stream->status) }} · Manual WPiTV</p>@if($stream->display_program)<span>{{ $stream->display_program->name }}</span>@endif
                    <a class="pub-btn pub-btn--primary" href="{{ $stream->status === 'ended' ? route('video.show', $stream->archive_video_id) : route('live.show', $stream->id) }}">{{ $stream->status === 'ended' ? 'Tonton arsip Tayangan' : 'Lihat siaran' }}</a>
                </article>
            @empty<p>Belum ada siaran pada bagian ini.</p>@endforelse</div>
            @if($items instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator){{ $items->links() }}@endif
        @endforeach
    @endunless
    <a href="{{ route('live.index') }}" class="pub-btn pub-btn--primary">Lihat semua siaran dan jadwal Live</a>
</div></section>
