<div class="agenda-admin-page">
    <div class="page-heading agenda-admin-heading"><div><a href="{{ route('admin.agenda.index') }}" class="agenda-back"><i class="bi bi-arrow-left"></i> Agenda WPiTV</a><h3>{{ $agendaId ? 'Edit Agenda' : 'Tambah Agenda' }}</h3><p>Tinjau informasi sebelum agenda disimpan.</p></div></div>
    @if($fromCoverage)<div class="alert alert-info"><i class="bi bi-info-circle"></i> Data dasar telah diisi dari permohonan peliputan. Agenda belum tersimpan sampai Anda menekan Simpan.</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    @if($liveStream)
        <div class="card p-3 border" role="status">
            <strong>Terhubung dengan Live Streaming · {{ $liveStream->status }} · versi form {{ $expectedLiveVersion }}</strong>
            <p class="mb-0">Perubahan disimpan bersama audit siaran. Jenis tetap Live; jadwal yang sudah selesai tidak dapat diubah. Status YouTube belum diverifikasi.</p>
            @if($liveStream->version !== $expectedLiveVersion)<p class="text-danger mb-0">Data telah berubah. Muat ulang halaman sebelum menyimpan.</p>@endif
        </div>
    @endif
    <form wire:submit="save" class="agenda-form" data-admin-confirm-title="Simpan perubahan Agenda{{ $liveStream ? ' dan lifecycle siaran terkait' : '' }}?">
        <section class="card agenda-form-section">
            <div class="agenda-form-title"><span>01</span><div><h4>Informasi Agenda</h4><p>Identitas dan konteks utama kegiatan.</p></div></div>
            <div class="row g-3">
                <div class="col-12"><label>Judul Agenda</label><input wire:model="title" class="form-control @error('title') is-invalid @enderror" maxlength="200">@error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label>Jenis Agenda</label><select wire:model.live="type" class="form-select" @disabled($liveStream !== null)>@foreach(App\Models\WpitvAgenda::TYPES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>@error('type')<div class="text-danger">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label>Deskripsi</label><textarea wire:model="description" class="form-control" rows="4" placeholder="Ringkasan operasional yang diperlukan..."></textarea></div>
            </div>
        </section>
        <section class="card agenda-form-section">
            <div class="agenda-form-title"><span>02</span><div><h4>Jadwal & Lokasi</h4><p>{{ $type === 'live' ? 'Jam akhir lebih kecil berarti hari berikutnya WIB; jam yang sama tidak diperbolehkan.' : 'Waktu selesai bersifat opsional.' }}</p></div></div>
            <div class="row g-3">
                <div class="col-md-4"><label>Tanggal</label><input type="date" wire:model="agenda_date" class="form-control @error('agenda_date') is-invalid @enderror" @disabled($liveStream?->status === 'ended')>@error('agenda_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label>Waktu Mulai</label><input type="time" wire:model="start_time" class="form-control @error('start_time') is-invalid @enderror" @disabled($liveStream?->status === 'ended')>@error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label>Waktu Selesai</label><input type="time" wire:model="end_time" class="form-control @error('end_time') is-invalid @enderror" @disabled($liveStream?->status === 'ended')>@error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label>Lokasi</label><input wire:model="location" class="form-control" maxlength="255" placeholder="Studio WPiTV, Aula Kampus, atau lokasi lainnya"></div>
            </div>
        </section>
        <section class="card agenda-form-section">
            <div class="agenda-form-title"><span>03</span><div><h4>Keterkaitan</h4><p>Semua pilihan di bagian ini bersifat opsional.</p></div></div>
            <div class="row g-3">
                <div class="col-md-4"><label>Program</label><select wire:model="program_id" class="form-select"><option value="">— Tanpa Program —</option>@foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label>Peliputan</label><select wire:model="coverage_id" class="form-select"><option value="">— Tanpa Peliputan —</option>@foreach($coverages as $coverage)<option value="{{ $coverage->id }}">{{ $coverage->submission_code }} — {{ $coverage->event_name }} — {{ $coverage->event_date->locale('id')->translatedFormat('d M Y') }}</option>@endforeach</select></div>
                <div class="col-md-4"><label>Kelompok Produksi</label><select wire:model="group_id" class="form-select"><option value="">— Tanpa Kelompok —</option>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->name }} · {{ $group->program?->name }} · {{ $group->generation?->name }}</option>@endforeach</select></div>
            </div>
        </section>
        <section class="card agenda-form-section">
            <div class="agenda-form-title"><span>04</span><div><h4>Status & Publikasi</h4><p>Draf dan agenda internal tidak akan muncul kepada publik.</p></div></div>
            <div class="row g-3">
                <div class="col-md-6"><label>Status</label><select wire:model="status" class="form-select">@foreach(App\Models\WpitvAgenda::STATUSES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-6"><label>Visibilitas</label><select wire:model="visibility" class="form-select">@foreach(App\Models\WpitvAgenda::VISIBILITIES as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                @if($liveStream)
                    <div class="col-12"><label for="live-agenda-reason">Alasan perubahan lifecycle</label><textarea id="live-agenda-reason" wire:model="reason" class="form-control" maxlength="1000" rows="2"></textarea><small>Wajib untuk pembatalan, penjadwalan ulang setelah batal, atau penyelesaian tanpa penandaan mulai. Siaran yang sedang live diselesaikan dengan status Selesai, bukan Dibatalkan.</small>@error('reason')<div class="text-danger">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><small>Mengubah Agenda menjadi internal tidak menarik Tayangan arsip yang sudah dipublikasikan.</small></div>
                @endif
            </div>
        </section>
        <div class="agenda-form-actions"><a href="{{ route('admin.agenda.index') }}" class="btn btn-light">Batal</a><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="bi bi-check2"></i> Simpan Agenda</button></div>
    </form>
</div>
