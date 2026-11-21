<div class="live-admin-page">
    <div class="page-heading"><a href="{{ route('admin.live-streams.index') }}" class="btn btn-link px-0"><i class="bi bi-arrow-left"></i> Live Streaming</a><h3>{{ $streamId ? 'Edit konfigurasi Live' : 'Tambah Live Streaming' }}</h3></div>
    @include('livewire.admin.live-streams.feedback')
    @if(!$loadError)
        <form wire:submit="save" data-admin-confirm-title="Simpan konfigurasi Live Streaming?" class="row g-4">
            <div class="col-lg-7"><section class="card"><div class="card-body">
                @if($stream)
                    <h4 class="h5">{{ $stream->display_title }}</h4>
                    <p>Sumber jadwal {{ $stream->isManual() ? 'Manual' : 'Agenda' }} · {{ $stream->display_program?->name ?? 'Tanpa Program' }} · {{ $stream->schedule_date }} · {{ $stream->time_label }}</p>
                    <p class="text-muted">Versi form: {{ $expectedVersion }}. ID YouTube hanya dapat diganti sebelum siaran dimulai.</p>
                    @if($expectedVersion !== $stream->version)<p class="text-danger">Data telah berubah. Muat ulang form sebelum menyimpan.</p>@endif
                    @if(!$stream->isManual())<a href="{{ route('admin.agenda.edit', $stream->agenda_id) }}" class="btn btn-outline-secondary btn-sm mb-3">Edit Agenda</a>@endif
                @else
                    <fieldset class="mb-3">
                        <legend class="h6">Sumber jadwal</legend>
                        <div class="form-check form-check-inline"><input class="form-check-input" id="live-mode-manual" type="radio" name="live-mode" value="manual" wire:model.live="mode"><label class="form-check-label" for="live-mode-manual">Manual</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input" id="live-mode-agenda" type="radio" name="live-mode" value="agenda" wire:model.live="mode"><label class="form-check-label" for="live-mode-agenda">Berdasarkan Agenda</label></div>
                    </fieldset>
                @endif
                @if((!$stream && $mode === 'manual') || ($stream?->isManual() && $stream->status === 'scheduled'))
                    <div class="mb-3"><label class="form-label" for="live-title">Judul</label><input class="form-control" id="live-title" wire:model="title" maxlength="200" required></div>
                    <div class="mb-3"><label class="form-label" for="live-program">Program (opsional)</label><select class="form-select" id="live-program" wire:model="program_id"><option value="">Tanpa Program</option>@foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach</select></div>
                    <div class="row g-2 mb-3">
                        <div class="col-sm-4"><label class="form-label" for="live-date">Tanggal</label><input class="form-control" id="live-date" type="date" wire:model="agenda_date" required></div>
                        <div class="col-sm-4"><label class="form-label" for="live-start">Mulai (WIB)</label><input class="form-control" id="live-start" type="time" wire:model="start_time" required></div>
                        <div class="col-sm-4"><label class="form-label" for="live-end">Selesai (WIB, opsional)</label><input class="form-control" id="live-end" type="time" wire:model="end_time"></div>
                    </div>
                    <p class="small text-muted">Jika jam selesai lebih awal daripada jam mulai, selesai dihitung pada hari berikutnya.</p>
                    <div class="mb-3"><label class="form-label" for="live-visibility">Visibilitas</label><select class="form-select" id="live-visibility" wire:model="visibility"><option value="internal">Internal</option><option value="public">Publik</option></select></div>
                @elseif(!$stream && $mode === 'agenda')
                    <label for="live-agenda-search" class="form-label">Cari Agenda</label><input id="live-agenda-search" type="search" class="form-control mb-3" wire:model.live.debounce.350ms="agendaSearch" maxlength="150">
                    <label for="live-agenda" class="form-label">Agenda jenis Live yang belum terhubung</label><select id="live-agenda" wire:model.live="agendaId" class="form-select mb-2"><option value="">Pilih Agenda</option>@foreach($agendas as $agenda)<option value="{{ $agenda->id }}">{{ $agenda->agenda_date->format('d/m/Y') }} · {{ $agenda->title }} · {{ $agenda->program?->name ?? 'Tanpa Program' }}</option>@endforeach</select>
                    @if($selectedAgenda)<p class="mb-2">{{ $selectedAgenda->title }} · {{ $selectedAgenda->time_label }} · {{ $selectedAgenda->visibility_label }}</p>@endif
                    <p class="small text-muted">Pencarian menampilkan hingga 50 hasil. Judul, jadwal dan Program mengikuti Agenda.</p>
                @endif
                <div class="mb-3"><label for="live-youtube" class="form-label">URL atau ID YouTube (opsional saat persiapan)</label><input id="live-youtube" wire:model.live.blur="youtubeInput" class="form-control" maxlength="2048" autocomplete="off" placeholder="https://www.youtube.com/live/…"><small class="text-muted">Mendukung URL watch, youtu.be, /live/, /embed/, atau ID 11 karakter. Input ini tidak memverifikasi ketersediaan YouTube.</small></div>
                <div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Simpan konfigurasi</button><a href="{{ $stream ? route('admin.live-streams.show', $stream->id) : route('admin.live-streams.index') }}" class="btn btn-light">Batal</a></div>
            </div></section></div>
            <div class="col-lg-5"><section class="card"><div class="card-body"><h4 class="h5">Preview YouTube</h4>
                @if($previewUrl)<div class="live-player" wire:key="live-preview-{{ md5($previewUrl) }}" wire:ignore><iframe src="{{ $previewUrl }}" title="Preview YouTube Live" referrerpolicy="strict-origin-when-cross-origin" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe></div><a href="{{ $watchUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary mt-3">Buka di YouTube <i class="bi bi-box-arrow-up-right"></i></a>
                @else<p class="text-muted mb-0">Masukkan URL/ID yang valid lalu tinggalkan kolom untuk menampilkan preview.</p>@endif
                <p class="small text-muted mt-3 mb-0">Player menampilkan kondisi YouTube. Status aplikasi tetap manual; preview tidak mengubah lifecycle.</p>
            </div></section></div>
        </form>
    @endif
</div>
