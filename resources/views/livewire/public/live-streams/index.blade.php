<div class="public-live-page">
    <section class="pub-hero !py-12"><div class="pub-container"><span class="sec-label">WPiTV On Air</span><h1 class="hero-title !text-4xl">Live Streaming</h1><p>Status ditandai manual oleh WPiTV, bukan verifikasi otomatis YouTube.</p></div></section>
    @foreach($sections as $key => $section)
        <section class="pub-section" aria-labelledby="live-{{ $key }}"><div class="pub-container">
            <h2 class="sec-h2" id="live-{{ $key }}">{{ $section['title'] }}</h2>
            @if($key === 'pending')<p>Waktu mulai rencana telah lewat. Siaran belum otomatis dianggap selesai; tunggu pembaruan petugas.</p>@endif
            <div class="public-live-grid">
                @forelse($section['streams'] as $stream)
                    <article class="public-live-card"><span class="sec-label">{{ ucfirst($stream->status) }} · Manual WPiTV</span><h3><a href="{{ route('live.show', $stream->id) }}">{{ $stream->display_title }}</a></h3><p>{{ $stream->schedule_date }} · {{ $stream->time_label }}</p>@if($stream->display_program)<a href="{{ route('program.show', $stream->display_program->slug) }}">{{ $stream->display_program->name }}</a>@endif<a class="pub-btn pub-btn--primary" href="{{ route('live.show', $stream->id) }}">{{ $key === 'archive' ? 'Lihat arsip' : 'Lihat siaran' }}</a></article>
                @empty<p class="public-live-card">Belum ada siaran pada bagian ini.</p>@endforelse
            </div>
            {{ $section['streams']->links() }}
        </div></section>
    @endforeach
</div>
