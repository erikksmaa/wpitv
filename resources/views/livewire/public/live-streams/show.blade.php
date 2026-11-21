<div class="public-live-page"><section class="pub-section"><div class="pub-container">
    <a href="{{ route('live.index') }}">← Semua Live Streaming</a>
    <h1 class="sec-h2">{{ $stream->display_title }}</h1>
    <p>{{ ucfirst($stream->status) }} · Status manual WPiTV, bukan verifikasi otomatis YouTube.</p>
    <p>{{ $stream->schedule_date }} · {{ $stream->time_label }}</p>
    @if($stream->display_program)<a href="{{ route('program.show', $stream->display_program->slug) }}">{{ $stream->display_program->name }}</a>@endif
    @if($stream->agenda?->description)<p class="public-live-description">{{ $stream->agenda->description }}</p>@endif
    @if($stream->status === 'cancelled')
        <div class="public-live-card">Siaran dibatalkan.</div>
    @elseif($stream->status === 'ended')
        <div class="public-live-card">Siaran telah selesai. @if($stream->hasValidArchive())<a class="pub-btn pub-btn--primary" href="{{ route('video.show', $stream->archive_video_id) }}">Tonton arsip Tayangan</a>@else Rekaman belum siap atau belum ditautkan. @endif</div>
    @else
        @if($stream->status === 'scheduled' && (!$stream->scheduled_starts_at || $stream->scheduled_starts_at->lte(now('UTC'))))<p>Jadwal mulai telah lewat; menunggu pembaruan operator, bukan otomatis selesai.</p>@endif
        @if($stream->embed_url)
            <div class="public-live-player" wire:ignore><iframe src="{{ $stream->embed_url }}" title="Siaran {{ $stream->display_title }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe></div>
        @else<div class="public-live-card">Tautan YouTube belum tersedia.</div>@endif
        <p>Siaran mungkin belum dimulai atau video tidak tersedia. Jika player gagal, buka YouTube; kondisi player tidak mengubah status WPiTV.</p>
    @endif
    @if($stream->status !== 'cancelled' && $stream->watch_url)<a class="pub-btn pub-btn--primary" href="{{ $stream->watch_url }}" target="_blank" rel="noopener noreferrer">Buka di YouTube</a>@endif
</div></section></div>
