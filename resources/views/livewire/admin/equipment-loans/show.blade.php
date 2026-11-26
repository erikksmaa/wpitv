@use('App\Livewire\Admin\Inventory\InventoryActivity', 'Activity')
<div x-on:equipment-feedback.window="window.WPiAlert?.toast($event.detail.message, $event.detail.type)">
    <div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-2">
        <div>
            <nav aria-label="Breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li class="breadcrumb-item"><a href="{{ route('admin.equipment-loans.index') }}">Peminjaman</a></li><li class="breadcrumb-item active" aria-current="page">{{ $loan->loan_number }}</li></ol></nav>
            <h1 class="h3 text-break mb-1">{{ $loan->loan_number }}</h1>
            <p class="text-muted mb-0">{{ ['active'=>'Aktif','partially_resolved'=>'Selesai sebagian','closed'=>'Selesai','voided'=>'Dibatalkan','integrity_error'=>'Perlu pemeriksaan'][$loan->progress] }} · {{ $loan->total_units }} unit</p>
        </div>
        <div><a href="{{ route('admin.equipment-loans.index') }}" class="btn btn-outline-secondary">Daftar peminjaman</a></div>
    </div>
    @if($loan->overdue)
        <div class="border border-danger rounded p-3 mb-3 text-danger">Terlambat: masih ada {{ $loan->open_units }} unit belum diselesaikan setelah akhir hari jatuh tempo WIB.</div>
    @endif
    <div class="row g-3 mb-3">
        <div class="col-lg-6"><section class="card h-100 mb-0"><div class="card-header"><h5>Peminjam</h5></div><div class="card-body"><dl class="row mb-0"><dt class="col-sm-4">Nama</dt><dd class="col-sm-8">{{ $loan->borrower_name }}</dd><dt class="col-sm-4">Jenis / identitas</dt><dd class="col-sm-8">{{ ['student'=>'Mahasiswa','lecturer'=>'Dosen','staff'=>'Staf'][$loan->borrower_type] }} · {{ $loan->identity_number }}</dd><dt class="col-sm-4">Kontak</dt><dd class="col-sm-8">{{ $loan->borrower_contact ?: '—' }}</dd><dt class="col-sm-4">Instansi</dt><dd class="col-sm-8">{{ $loan->borrower_affiliation ?: '—' }}</dd><dt class="col-sm-4">Keperluan</dt><dd class="col-sm-8 text-break">{{ $loan->purpose }}</dd></dl></div></section></div>
        <div class="col-lg-6"><section class="card h-100 mb-0"><div class="card-header"><h5>Tanggal dan tindakan</h5></div><div class="card-body"><p>Dipinjam: <strong>{{ \App\Services\Equipment\LoanRules::wib($loan->borrowed_at) }}</strong></p><p>Jatuh tempo: <strong>{{ \Carbon\CarbonImmutable::parse($loan->due_date)->format('d/m/Y') }} pukul 23:59:59 WIB</strong></p><p class="small text-muted">Jatuh tempo awal: {{ $originalDue ?? 'Tidak tersedia pada audit histori sebelumnya' }}</p>
            @if(in_array($loan->progress,['active','partially_resolved']))
                <div class="d-flex flex-wrap gap-2">
                    @can('equipment.loan.return')
                        <button type="button" class="btn btn-primary" wire:click="begin('return.create')">Kembalikan unit</button>
                    @endcan
                    @can('equipment.loan.extend')
                        <button type="button" class="btn btn-outline-primary" wire:click="begin('loan.extend')">Perpanjang</button>
                    @endcan
                    @can('equipment.loan.loss')
                        <button type="button" class="btn btn-outline-danger" wire:click="begin('loan.loss')">Catat kehilangan</button>
                    @endcan
                    @if($loan->progress==='active')
                        @can('equipment.loan.void')
                            <button type="button" class="btn btn-outline-danger" wire:click="begin('loan.void')">Batalkan salah input</button>
                        @endcan
                    @endif
                </div>
            @endif
        </div></section></div>
    </div>
    @if($operation)
        <section class="card border border-primary"><div class="card-header"><h5>{{ ['return.create'=>'Pengembalian unit','loan.extend'=>'Perpanjangan jatuh tempo','loan.loss'=>'Pencatatan kehilangan','loan.void'=>'Pembatalan kesalahan input'][$operation] }}</h5></div><div class="card-body">
            <form wire:submit="save" data-admin-confirm-title="{{ ['return.create'=>'Catat pengembalian unit terpilih beserta bukti?', 'loan.extend'=>'Perpanjang jatuh tempo peminjaman?', 'loan.loss'=>'Catat unit terpilih sebagai hilang?', 'loan.void'=>'Batalkan seluruh peminjaman sebelum serah terima?'][$operation] }}">
                @include('livewire.admin.equipment-field-error', ['field'=>'action'])
                @if(in_array($operation,['return.create','loan.loss']))
                    <p>Dipilih {{ count($selected) }} dari maksimal 10 unit. Unit lain tetap dipinjam.</p><button type="button" class="btn btn-sm btn-outline-primary mb-3" wire:click="selectAll">Pilih hingga 10 unit</button>
                    @include('livewire.admin.equipment-field-error', ['field'=>'selected','nested'=>true])@include('livewire.admin.equipment-field-error', ['field'=>'photos'])
                    @foreach($loanUnits->where('outcome','on_loan') as $unit)
                        <div class="border rounded p-3 mb-3" wire:key="resolution-{{ $unit->id }}">
                            <label class="form-check-label"><input class="form-check-input me-2" type="checkbox" @disabled(count($selected)>=10 && !in_array($unit->id,$selected)) wire:model.live="selected" value="{{ $unit->id }}">{{ $unit->snapshot['asset_code'] ?? 'Unit #'.$unit->unit_id }} · {{ $unit->snapshot['model_name'] ?? 'Model snapshot #'.$unit->model_id_at_checkout }}</label>
                            @if(in_array((string)$unit->id,array_map('strval',$selected),true) && $operation==='return.create')
                                <div class="row g-3 mt-1"><div class="col-md-4"><label for="condition-{{ $unit->id }}" class="form-label">Kondisi akhir</label><select id="condition-{{ $unit->id }}" class="form-select" wire:model="items.{{ $unit->id }}.condition"><option value="good">Baik</option><option value="minor_damage">Rusak ringan</option><option value="major_damage">Rusak berat</option></select>@include('livewire.admin.equipment-field-error', ['field'=>'items.'.$unit->id.'.condition'])</div><div class="col-md-8"><label for="notes-{{ $unit->id }}" class="form-label">Catatan unit (wajib jika rusak / terlambat)</label><textarea id="notes-{{ $unit->id }}" wire:model="items.{{ $unit->id }}.notes" class="form-control" maxlength="1000"></textarea>@include('livewire.admin.equipment-field-error', ['field'=>'items.'.$unit->id.'.notes'])</div><div class="col-12"><label for="photos-{{ $unit->id }}" class="form-label">Foto bukti khusus unit ini (1–8 foto, wajib)</label><input id="photos-{{ $unit->id }}" type="file" multiple wire:model="photos.{{ $unit->id }}" accept="image/jpeg,image/png,image/webp" class="form-control"><div class="small text-muted mt-1">JPG/PNG/WebP, 64–4096 px, maksimal 5 MB per foto. {{ count($photos[$unit->id] ?? []) }} file terpilih.</div>
                                @if(!empty($photos[$unit->id]))
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        @foreach($photos[$unit->id] as $file)
                                            @if(method_exists($file, 'temporaryUrl'))
                                                <img src="{{ $file->temporaryUrl() }}" alt="Pratinjau foto" class="rounded border object-fit-cover" width="64" height="48">
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                                @include('livewire.admin.equipment-field-error', ['field'=>'photos.'.$unit->id,'nested'=>true])</div></div>
                            @endif
                        </div>
                    @endforeach
                    @if($operation==='return.create')
                        <p class="small text-muted">Sekali simpan: maksimal 10 unit, 40 foto, dan total 100 MB. Unit rusak baru tersedia setelah diperbaiki.</p>
                        <label for="receipt-notes" class="form-label">Catatan serah terima (opsional)</label><textarea id="receipt-notes" wire:model="notes" maxlength="1000" class="form-control mb-3"></textarea>@include('livewire.admin.equipment-field-error', ['field'=>'notes'])
                    @endif
                @endif
                @if($operation==='loan.extend')
                    <label for="new-due" class="form-label">Jatuh tempo baru (akhir hari WIB)</label><input id="new-due" type="date" wire:model="dueDate" class="form-control mb-3" required>@include('livewire.admin.equipment-field-error', ['field'=>'dueDate'])<p class="small text-muted">Pilih tanggal setelah jatuh tempo sebelumnya.</p>
                @endif
                @if($operation==='loan.void')
                    <div class="border border-warning rounded p-3 mb-3">Hanya untuk kesalahan input sebelum barang benar-benar diserahkan, seluruh unit masih dipinjam dan belum ada retur/kehilangan. Histori tetap tersimpan. Gunakan menu pengembalian jika unit sudah diserahkan.</div>
                    <label class="form-check-label mb-3"><input type="checkbox" wire:model="notHandedOver" class="form-check-input me-2" required>Saya memastikan seluruh barang belum benar-benar diserahkan kepada peminjam.</label>@include('livewire.admin.equipment-field-error', ['field'=>'notHandedOver'])
                @endif
                @if($operation!=='return.create')
                    <label for="loan-reason" class="form-label">Alasan tindakan (wajib)</label><textarea id="loan-reason" wire:model="reason" maxlength="1000" class="form-control mb-3" required></textarea>@include('livewire.admin.equipment-field-error', ['field'=>'reason'])
                @endif
                <div wire:loading wire:target="photos" role="status">Memproses unggahan…</div>
                <div class="d-flex gap-2"><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save,photos,selected,selectAll" @disabled(in_array($operation,['return.create','loan.loss']) && !$selected)>Simpan</button><button type="button" class="btn btn-secondary" wire:click="cancel">Batal</button></div>
            </form>
        </div></section>
    @endif
    <section class="card"><div class="card-header"><h5>Unit dan histori penyelesaian</h5></div><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Aset / snapshot checkout</th><th>Kondisi keluar → masuk</th><th>Status unit pinjaman</th><th>Penyelesaian</th><th>Catatan</th></tr></thead><tbody>
        @foreach($loanUnits as $unit)
            <tr wire:key="history-{{ $unit->id }}"><td><strong>{{ $unit->snapshot['asset_code'] ?? 'Unit #'.$unit->unit_id }}</strong><div class="small">{{ $unit->snapshot['model_name'] ?? 'Model snapshot #'.$unit->model_id_at_checkout }} / {{ $unit->snapshot['category_name'] ?? 'Kategori snapshot #'.$unit->category_id_at_checkout }}</div></td><td>{{ \App\Services\Equipment\EquipmentReports::CONDITIONS[$unit->condition_out] }} → {{ \App\Services\Equipment\EquipmentReports::CONDITIONS[$unit->condition_in] ?? '—' }}</td><td>{{ ['on_loan'=>'Dipinjam','returned'=>'Kembali','lost'=>'Hilang','voided'=>'Dibatalkan'][$unit->outcome] }}</td><td>{{ \App\Services\Equipment\LoanRules::wib($unit->resolution_at) }}
                @if($unit->is_late)
                    <span class="badge bg-danger">Terlambat saat penyelesaian</span>
                @endif
                @if($unit->due_date_at_resolution)
                    <div class="small">Jatuh tempo saat itu: {{ $unit->due_date_at_resolution }} WIB</div>
                @endif
            </td><td class="text-break">{{ $unit->resolution_notes ?: '—' }}</td></tr>
        @endforeach
    </tbody></table></div></div></section>
    <section class="card"><div class="card-header"><h5>Batch pengembalian dan bukti privat</h5></div><div class="card-body">
        @php($unitsById = $loanUnits->keyBy('id'))
        @forelse($receipts as $receipt)
            <div class="border rounded p-3 mb-3"><h6>Retur #{{ $receipt->id }} · {{ \App\Services\Equipment\LoanRules::wib($receipt->returned_at) }}</h6><p>{{ $receipt->notes }}</p><div class="row g-3">
                @foreach($evidence->where('return_id',$receipt->id) as $photo)
                    @can('equipment.photo.view')
                        @php($photoUrl = route('admin.equipment-loans.photos',['loan'=>$loanId,'photo'=>$photo->id]))
                        @php($unitCode = $unitsById[$photo->loan_unit_id]->snapshot['asset_code'] ?? 'Unit pinjaman #'.$photo->loan_unit_id)
                        <div class="col-6 col-md-3" x-data>
                            <button type="button" class="btn p-0 w-100 border rounded overflow-hidden" x-on:click="$refs.evidencePreview.showModal()" aria-label="Perbesar bukti {{ $unitCode }}">
                                <img src="{{ $photoUrl }}" alt="Bukti {{ $unitCode }}" class="w-100 object-fit-cover" style="aspect-ratio:4/3" loading="lazy">
                            </button>
                            <small class="d-block mt-1 text-muted">{{ $unitCode }}</small>
                            <dialog x-ref="evidencePreview" class="modal" style="background:transparent;border:0;padding:0;max-width:100vw;max-height:100dvh;" aria-label="Pratinjau bukti {{ $unitCode }}">
                                <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
                                    <div class="modal-header"><strong>{{ $unitCode }} · Retur #{{ $receipt->id }}</strong><button type="button" class="btn-close" x-on:click="$refs.evidencePreview.close()" aria-label="Tutup foto"></button></div>
                                    <div class="modal-body text-center"><img src="{{ $photoUrl }}" alt="Bukti {{ $unitCode }}" class="img-fluid rounded" style="max-height:70dvh"></div>
                                </div></div>
                            </dialog>
                        </div>
                    @endcan
                @endforeach
            </div></div>
        @empty
            <p class="text-muted">Belum ada pengembalian.</p>
        @endforelse
    </div></section>
    @can('equipment.audit.view')
        <section class="card"><div class="card-header"><h5>Riwayat aktivitas transaksi</h5></div><div class="card-body"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Waktu WIB</th><th>Petugas</th><th>Aktivitas</th><th>Keterangan</th></tr></thead><tbody>
            @foreach($audit as $event)
                <tr wire:key="audit-{{ $event->id }}"><td>{{ \App\Services\Equipment\LoanRules::wib($event->occurred_at) }}</td><td>{{ $event->actor_name_snapshot }}</td><td>{{ Activity::LABELS[$event->event_name] ?? 'Aktivitas peminjaman' }}</td><td class="text-break">{{ Activity::reason($event->reason) }}</td></tr>
            @endforeach
        </tbody></table></div></div></section>
    @endcan
</div>
