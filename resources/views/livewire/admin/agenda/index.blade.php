<div class="agenda-admin-page">
    <div class="page-heading agenda-admin-heading">
        <div><span class="agenda-eyebrow">Operasional WPiTV</span><h3>Agenda WPiTV</h3><p>Kelola jadwal produksi, peliputan, siaran, rapat, dan kegiatan operasional WPiTV.</p></div>
        @can('create', App\Models\WpitvAgenda::class)
            <a href="{{ route('admin.agenda.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tambah Agenda</a>
        @endcan
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif

    <section class="card agenda-admin-shell">
        <div class="agenda-tabs" role="tablist">
            <button wire:click="setView('calendar')" class="{{ $viewMode === 'calendar' ? 'active' : '' }}"><i class="bi bi-calendar3"></i> Kalender</button>
            <button wire:click="setView('list')" class="{{ $viewMode === 'list' ? 'active' : '' }}"><i class="bi bi-list-ul"></i> Daftar</button>
        </div>

        <div class="agenda-filters">
            <label class="agenda-search"><i class="bi bi-search"></i><input type="search" wire:model.live.debounce.350ms="search" placeholder="Cari judul atau lokasi..."></label>
            <select wire:model.live="typeFilter"><option value="">Semua Jenis</option>@foreach(App\Models\WpitvAgenda::TYPES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
            <select wire:model.live="statusFilter"><option value="">Semua Status</option>@foreach(App\Models\WpitvAgenda::STATUSES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
            <select wire:model.live="visibilityFilter"><option value="">Semua Visibilitas</option>@foreach(App\Models\WpitvAgenda::VISIBILITIES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
            @if($search || $typeFilter || $statusFilter || $visibilityFilter)<button wire:click="resetFilters" class="btn btn-light btn-sm">Reset</button>@endif
        </div>

        @if($viewMode === 'calendar')
            <div class="agenda-monthbar">
                <div><button wire:click="previousMonth" aria-label="Bulan sebelumnya"><i class="bi bi-chevron-left"></i></button><button wire:click="nextMonth" aria-label="Bulan berikutnya"><i class="bi bi-chevron-right"></i></button></div>
                <h4>{{ $monthStart->locale('id')->translatedFormat('F Y') }}</h4>
                <button wire:click="currentMonth" class="agenda-today">Hari Ini</button>
            </div>
            <div class="agenda-calendar" aria-label="Kalender agenda {{ $monthStart->locale('id')->translatedFormat('F Y') }}">
                @foreach(['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $weekday)<div class="agenda-weekday">{{ $weekday }}</div>@endforeach
                @foreach($days as $day)
                    @php($items = $calendarAgendas->get($day->format('Y-m-d'), collect()))
                    <div class="agenda-day {{ $day->month !== $monthStart->month ? 'is-outside' : '' }} {{ $day->isToday() ? 'is-today' : '' }}">
                        <div class="agenda-day-number"><span>{{ $day->day }}</span>@can('create', App\Models\WpitvAgenda::class)<a href="{{ route('admin.agenda.create', ['date' => $day->format('Y-m-d')]) }}" aria-label="Tambah agenda {{ $day->format('d-m-Y') }}"><i class="bi bi-plus"></i></a>@endcan</div>
                        <div class="agenda-day-items">
                            @foreach($items->take(3) as $agenda)
                                <button wire:click="showAgenda({{ $agenda->id }})" class="agenda-calendar-item status-{{ $agenda->status }}" title="{{ $agenda->title }}"><span>{{ $agenda->start_time ? substr($agenda->start_time,0,5) : '—' }}</span>{{ $agenda->title }}</button>
                            @endforeach
                            @if($items->count() > 3)<span class="agenda-more">+{{ $items->count()-3 }} agenda lainnya</span>@endif
                        </div>
                        @if($items->isNotEmpty())<span class="agenda-mobile-count">{{ $items->count() }}</span>@endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="table-responsive agenda-table-wrap">
                <table class="table agenda-table align-middle mb-0">
                    <thead><tr><th>Tanggal</th><th>Agenda</th><th>Jenis</th><th>Keterkaitan</th><th>Lokasi</th><th>Status</th><th>Visibilitas</th><th>Aksi</th></tr></thead>
                    <tbody>
                    @forelse($agendas as $agenda)
                        <tr>
                            <td data-label="Tanggal"><strong>{{ $agenda->agenda_date->locale('id')->translatedFormat('d M Y') }}</strong><small>{{ $agenda->time_label }}</small></td>
                            <td data-label="Agenda"><strong>{{ $agenda->title }}</strong></td>
                            <td data-label="Jenis"><span class="agenda-type-badge">{{ $agenda->type_label }}</span></td>
                            <td data-label="Keterkaitan"><small>{{ $agenda->program?->name ?? $agenda->coverage?->event_name ?? $agenda->group?->name ?? '—' }}</small></td>
                            <td data-label="Lokasi">{{ $agenda->location ?: '—' }}</td>
                            <td data-label="Status"><span class="agenda-status status-{{ $agenda->status }}">{{ $agenda->status_label }}</span></td>
                            <td data-label="Visibilitas"><span class="agenda-visibility">{{ $agenda->visibility_label }}</span></td>
                            <td data-label="Aksi"><div class="btn-group btn-group-sm"><button wire:click="showAgenda({{ $agenda->id }})" class="btn btn-outline-primary" title="Lihat"><i class="bi bi-eye"></i></button>@can('update',$agenda)<a href="{{ route('admin.agenda.edit',$agenda) }}" class="btn btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>@endcan</div></td>
                        </tr>
                    @empty<tr><td colspan="8"><div class="agenda-empty"><i class="bi bi-calendar2-week"></i><strong>Belum ada agenda</strong><span>Tambahkan agenda operasional pertama atau ubah filter.</span></div></td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $agendas->links() }}</div>
        @endif
    </section>

    @if($showDetailModal && $selectedAgenda)
        <div class="modal fade show d-block agenda-modal" role="dialog" aria-modal="true" wire:click.self="closeDetail">
            <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><div><span class="agenda-eyebrow">Rincian Agenda</span><h4>{{ $selectedAgenda->title }}</h4></div><button wire:click="closeDetail" class="btn-close" aria-label="Tutup"></button></div>
                <div class="modal-body">
                    <div class="agenda-detail-lead"><div class="agenda-date-block"><strong>{{ $selectedAgenda->agenda_date->format('d') }}</strong><span>{{ $selectedAgenda->agenda_date->locale('id')->translatedFormat('M Y') }}</span></div><div><span class="agenda-status status-{{ $selectedAgenda->status }}">{{ $selectedAgenda->status_label }}</span><p>{{ $selectedAgenda->agenda_date->locale('id')->translatedFormat('l, d F Y') }} · {{ $selectedAgenda->time_label }}</p><p><i class="bi bi-geo-alt"></i> {{ $selectedAgenda->location ?: 'Lokasi menyusul' }}</p></div></div>
                    <div class="agenda-detail-grid">
                        <div><small>Jenis</small><strong>{{ $selectedAgenda->type_label }}</strong></div><div><small>Visibilitas</small><strong>{{ $selectedAgenda->visibility_label }}</strong></div>
                        <div><small>Program</small><strong>{{ $selectedAgenda->program?->name ?? '—' }}</strong></div><div><small>Kelompok</small><strong>{{ $selectedAgenda->group?->name ?? '—' }}</strong></div>
                        <div><small>Peliputan</small><strong>{{ $selectedAgenda->coverage?->event_name ?? '—' }}</strong>@if($selectedAgenda->coverage)<a href="{{ route('admin.peliputan.index', ['agenda_coverage' => $selectedAgenda->coverage_id]) }}">Buka Peliputan</a>@endif</div><div><small>Dibuat oleh</small><strong>{{ $selectedAgenda->creator?->name ?? 'Sistem' }}</strong></div>
                    </div>
                    @if($selectedAgenda->description)<div class="agenda-description"><small>Deskripsi</small><p>{{ $selectedAgenda->description }}</p></div>@endif
                    @if($selectedLiveStatus !== null)
                        <div class="border rounded p-3 mt-3"><strong>Live Streaming: {{ $selectedLiveStatus }}</strong><p>Agenda ini tidak dapat dihapus karena memiliki histori Live Streaming. Gunakan pembatalan sebelum siaran dimulai atau ubah visibilitas melalui Edit Agenda.</p>
                            @if($selectedLiveStatus === 'scheduled')
                                <label for="agenda-cancel-reason">Alasan pembatalan</label><textarea id="agenda-cancel-reason" wire:model="cancellationReason" maxlength="1000" class="form-control" rows="2"></textarea>
                                @error('reason')<div class="text-danger">{{ $message }}</div>@enderror
                            @endif
                            @if($selectedLiveStatus === 'live')<p class="mb-0">Untuk menutup siaran, pilih status Selesai melalui Edit Agenda.</p>@endif
                        </div>
                    @endif
                </div>
                <div class="modal-footer"><small>Gunakan status Dibatalkan untuk menyimpan riwayat agenda nyata.</small><div>
                    @can('delete',$selectedAgenda)<button wire:click="delete({{ $selectedAgenda->id }})" data-admin-confirm class="btn btn-outline-danger" @disabled($selectedLiveStatus !== null) wire:loading.attr="disabled">Hapus</button>@endcan
                    @can('update',$selectedAgenda)
                        @if($selectedAgenda->status !== 'cancelled' && in_array($selectedLiveStatus, [null, 'scheduled'], true))
                            <form wire:submit="cancel({{ $selectedAgenda->id }})" class="d-inline" data-admin-confirm-title="Batalkan Agenda{{ $selectedLiveStatus ? ' dan siaran terkait' : '' }}?" data-admin-confirm-text="Ya, batalkan"><button type="submit" class="btn btn-outline-warning" wire:loading.attr="disabled">Batalkan Agenda</button></form>
                        @endif
                        <a href="{{ route('admin.agenda.edit',$selectedAgenda) }}" class="btn btn-primary">Edit Agenda</a>
                    @endcan
                </div></div>
            </div></div>
        </div>
    @endif
</div>
