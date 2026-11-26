<div class="live-admin-page">
    <div class="page-heading">
        <nav aria-label="Breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li class="breadcrumb-item"><a href="{{ route('admin.live-streams.index') }}">Live Streaming</a></li><li class="breadcrumb-item active" aria-current="page">{{ $streamId ? 'Edit Konfigurasi' : 'Tambah Live' }}</li></ol></nav>
        <h1 class="h3 mb-0">{{ $streamId ? 'Edit Konfigurasi Live' : 'Tambah Live Streaming' }}</h1>
    </div>
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
                    <div class="mb-3"><label class="form-label" for="live-title">Judul</label><input class="form-control" id="live-title" wire:model.live.debounce.350ms="title" maxlength="200" required>@error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                    <div class="mb-3"><label class="form-label" for="live-description">Deskripsi Siaran <span class="text-muted fw-normal">(opsional)</span></label><textarea class="form-control" id="live-description" wire:model.live.debounce.350ms="description" rows="4" maxlength="5000" placeholder="Ceritakan secara singkat kegiatan yang akan disiarkan."></textarea>@error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror<small class="text-muted">Ringkasan tampil pada beranda serta daftar live; deskripsi lengkap tampil pada halaman detail.</small></div>
                    <div class="mb-3"><label class="form-label" for="live-program">Program (opsional)</label><select class="form-select" id="live-program" wire:model="program_id"><option value="">Tanpa Program</option>@foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach</select>@error('program_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                    <div class="row g-2 mb-3">
                        <div class="col-sm-4"><label class="form-label" for="live-date">Tanggal</label><input class="form-control" id="live-date" type="date" wire:model="agenda_date" required>@error('agenda_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                        <div class="col-sm-4"><label class="form-label" for="live-start">Mulai (WIB)</label><input class="form-control" id="live-start" type="time" wire:model="start_time" required>@error('start_time')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                        <div class="col-sm-4"><label class="form-label" for="live-end">Selesai (WIB, opsional)</label><input class="form-control" id="live-end" type="time" wire:model="end_time">@error('end_time')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                    </div>
                    <p class="small text-muted">Jika jam selesai lebih awal daripada jam mulai, selesai dihitung pada hari berikutnya.</p>
                    <div class="mb-3"><label class="form-label" for="live-visibility">Visibilitas</label><select class="form-select" id="live-visibility" wire:model="visibility"><option value="internal">Internal</option><option value="public">Publik</option></select>@error('visibility')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                @elseif(!$stream && $mode === 'agenda')
                    <label for="live-agenda-search" class="form-label">Cari Agenda</label><input id="live-agenda-search" type="search" class="form-control mb-3" wire:model.live.debounce.350ms="agendaSearch" maxlength="150">
                    <label for="live-agenda" class="form-label">Agenda jenis Live yang belum terhubung</label><select id="live-agenda" wire:model.live="agendaId" class="form-select mb-2"><option value="">Pilih Agenda</option>@foreach($agendas as $agenda)<option value="{{ $agenda->id }}">{{ $agenda->agenda_date->format('d/m/Y') }} · {{ $agenda->title }} · {{ $agenda->program?->name ?? 'Tanpa Program' }}</option>@endforeach</select>@error('agendaId')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                    @if($selectedAgenda)
                        <div class="border rounded p-3 mb-2">
                            <div class="fw-semibold">{{ $selectedAgenda->title }}</div>
                            <div class="small text-muted">{{ $selectedAgenda->time_label }} · {{ $selectedAgenda->visibility_label }}</div>
                            @if($selectedAgenda->description)<p class="small mb-0 mt-2 text-break" style="white-space:pre-line">{{ $selectedAgenda->description }}</p>@endif
                        </div>
                    @endif
                    <p class="small text-muted">Pencarian menampilkan hingga 50 hasil. Judul, deskripsi, jadwal, dan Program mengikuti Agenda.</p>
                @elseif($stream && (!$stream->isManual() || $stream->status !== 'scheduled'))
                    @if($stream->display_description)
                        <div class="border rounded p-3 mb-3">
                            <div class="small text-muted mb-1">Deskripsi Siaran</div>
                            <p class="mb-0 small text-break" style="white-space:pre-line">{{ $stream->display_description }}</p>
                        </div>
                    @endif
                @endif
                <div class="mb-3"><label for="live-youtube" class="form-label">URL atau ID YouTube (opsional saat persiapan)</label><input id="live-youtube" wire:model.live.blur="youtubeInput" class="form-control" maxlength="2048" autocomplete="off" placeholder="https://www.youtube.com/live/…">@error('youtubeInput')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror<small class="text-muted">Mendukung URL watch, youtu.be, /live/, /embed/, atau ID 11 karakter. Input ini tidak memverifikasi ketersediaan YouTube.</small></div>
                <div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Simpan konfigurasi</button><a href="{{ $stream ? route('admin.live-streams.show', $stream->id) : route('admin.live-streams.index') }}" class="btn btn-outline-secondary">Batal</a></div>
            </div></section></div>
            <div class="col-lg-5"><section class="card"><div class="card-body"><h4 class="h5">Preview YouTube</h4>
                @if($previewUrl)<div class="live-player" wire:key="live-preview-{{ md5($previewUrl) }}" wire:ignore><iframe src="{{ $previewUrl }}" title="Preview YouTube Live" referrerpolicy="strict-origin-when-cross-origin" allow="encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe></div><a href="{{ $watchUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary mt-3">Buka di YouTube <i class="bi bi-box-arrow-up-right"></i></a>
                @elseif(trim($youtubeInput) !== '')<div class="alert alert-warning mb-0" role="alert"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Tautan atau ID YouTube tidak valid. Gunakan format URL YouTube atau ID 11 karakter.</div>
                @else<p class="text-muted mb-0">Masukkan URL/ID yang valid lalu tinggalkan kolom untuk menampilkan pratinjau.</p>@endif
                @php($previewTitle = $stream ? ($stream->isManual() && $stream->status === 'scheduled' ? $title : $stream->display_title) : ($mode === 'agenda' ? $selectedAgenda?->title : $title))
                @php($previewDesc = $stream ? ($stream->isManual() && $stream->status === 'scheduled' ? trim($description) : $stream->display_description) : ($mode === 'agenda' ? $selectedAgenda?->description : trim($description)))
                @if(trim((string) $previewTitle) !== '' || trim((string) $previewDesc) !== '')
                    <div class="border-top pt-3 mt-3">
                        @if(trim((string) $previewTitle) !== '')<div class="fw-semibold mb-1">{{ $previewTitle }}</div>@endif
                        @if(trim((string) $previewDesc) !== '')<p class="small text-muted mb-0 text-break" style="white-space:pre-line">{{ $previewDesc }}</p>@endif
                    </div>
                @endif
                <p class="small text-muted mt-3 mb-0">Pemutar menampilkan kondisi YouTube. Status aplikasi tetap manual; pratinjau tidak mengubah alur siaran.</p>
            </div></section></div>
        </form>
    @endif
</div>
