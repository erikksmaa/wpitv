<section class="card dashboard-panel h-100" aria-labelledby="dashboard-agenda-title">
    <div class="card-header dashboard-panel-header"><div><span class="dashboard-section-kicker">Jadwal terdekat</span><h2 id="dashboard-agenda-title"><i class="bi bi-calendar2-week me-2"></i>Agenda Terdekat</h2></div><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.agenda.index') }}">Lihat agenda</a></div>
    <div class="card-body dashboard-list">
        @forelse($dashboard['agenda']['latest'] as $agenda)
            <div class="dashboard-list-item">
                <div class="list-icon primary"><i class="bi bi-calendar-event"></i></div>
                <div class="list-copy"><strong>{{ $agenda->title }}</strong><span>{{ $agenda->agenda_date->locale('id')->translatedFormat('d M Y') }} · {{ $agenda->time_label }} · {{ $agenda->visibility_label }}</span></div>
                @can('update', $agenda)<a href="{{ route('admin.agenda.edit', $agenda) }}" aria-label="Buka {{ $agenda->title }}"><i class="bi bi-arrow-right"></i></a>@endcan
            </div>
        @empty
            <div class="dashboard-empty compact"><i class="bi bi-calendar-check"></i><span>Belum ada agenda terjadwal mendatang.</span></div>
        @endforelse
    </div>
</section>
