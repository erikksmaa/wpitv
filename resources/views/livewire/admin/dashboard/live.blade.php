@can('viewAny', App\Models\LiveStream::class)
<section class="card mb-3" aria-label="Ringkasan Live Streaming"><div class="card-body">
    <h2 class="h4">Live Streaming</h2><p>Status manual WPiTV · Jadwal WIB</p>
    @unless($dashboard['live']['available'])<p role="status">Ringkasan Live belum dapat dimuat. Silakan coba lagi.</p>@else
        <div class="row g-3 mb-3">@foreach(['on_air' => 'Ditandai Live', 'upcoming' => 'Jadwal mendatang', 'missing_archive' => 'Selesai tanpa arsip valid'] as $key => $label)<div class="col-sm-4"><article class="dashboard-kpi"><div class="dashboard-kpi-copy"><span>{{ $label }}</span><strong>{{ $dashboard['live'][$key] }}</strong></div></article></div>@endforeach</div>
        <h3 class="h5">Lima Jadwal Live Terdekat</h3>
        @forelse($dashboard['live']['latest'] as $stream)<div class="border-bottom py-2"><a href="{{ route('admin.live-streams.show', $stream->id) }}">{{ $stream->display_title }}</a><p class="mb-0">{{ $stream->display_program?->name }} · {{ $stream->schedule_date }} · {{ $stream->time_label }} · {{ ucfirst($stream->status) }} (Manual)</p></div>@empty<p>Belum ada jadwal Live mendatang.</p>@endforelse
    @endunless
    <div class="d-flex flex-wrap gap-2 mt-3"><a class="btn btn-outline-primary" href="{{ route('admin.live-streams.index') }}">Manajemen Live</a>@can('viewAny', App\Models\WpitvAgenda::class)<a class="btn btn-outline-primary" href="{{ route('admin.agenda.index') }}">Agenda</a>@endcan @can('viewAny', App\Models\Video::class)<a class="btn btn-outline-primary" href="{{ route('admin.videos.index') }}">Tayangan</a>@endcan</div>
</div></section>
@endcan
