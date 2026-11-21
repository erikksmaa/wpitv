<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <div>
            <h4>Pengembalian Alat</h4>
            <p class="mb-0">{{ $loan->loan_code }} — {{ $loan->borrower_name }}</p>
        </div>
        <a class="btn btn-light" href="{{ route('admin.equipment-loans.show', $loan) }}">Kembali ke Detail</a>
    </div>
    <div class="card-body">
        <p>{{ $loan->returned_count }} dari {{ $loan->items_count }} unit sudah kembali.</p><p>Batas pengembalian: {{ $loan->due_at->timezone('Asia/Jakarta')->format('d/m/Y') }} WIB.</p>
        @if($loan->version !== $expectedVersion)
            <div class="alert alert-warning" role="status">
                Transaksi sudah berubah. Retry batch yang sama tetap aman.
                Untuk mencatat batch baru, <a href="{{ route('admin.equipment-loans.return', $loan) }}">muat ulang form</a>.
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @if($items->isEmpty())
            <p class="text-muted">Seluruh unit sudah dikembalikan.</p>
        @else
            <form wire:submit.prevent="save" x-data="{uploading:false}" x-on:livewire-upload-start="uploading=true" x-on:livewire-upload-finish="uploading=false" x-on:livewire-upload-error="uploading=false" x-on:livewire-upload-cancel="uploading=false"><p role="status" x-show="uploading">Mengunggah foto…</p><p class="border rounded-3 p-3"><strong>{{ count($selectedIds) }} unit dipilih</strong> dari {{ count($initialItemIds) }} yang belum kembali. Maksimal 10 unit per pengembalian.</p>
                <p>Batas batch {{ $photoEnabled ? 10 : 50 }} unit. Pilih unit yang diterima. Catatan wajib untuk kerusakan, keterlambatan, atau pencatatan waktu kembali yang sudah lampau.</p>
                <label for="retur-qty">Jumlah kembali (sisa {{ $loan->items_count-$loan->returned_count }})</label><input type="number" class="form-control" id="retur-qty" wire:model="quantity" min="1" max="10"><button type="button" class="btn btn-light mb-3" wire:click="selectQuantity">Pilih sesuai jumlah</button> <button type="button" class="btn btn-outline-primary mb-3" wire:click="selectAll" wire:loading.attr="disabled">Pilih unit terbuka</button>
                @foreach($items as $item)
                    <fieldset class="border rounded p-3 mb-3" wire:key="return-item-{{ $item->id }}">
                        <legend class="fs-6 float-none w-auto px-1">
                            {{ $item->unit_snapshot['inventory_code'] ?? '-' }} — {{ $item->unit_snapshot['name'] ?? '-' }}
                        </legend>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="return-select-{{ $item->id }}"
                                value="{{ $item->id }}" wire:model.live="selectedIds">
                            <label class="form-check-label" for="return-select-{{ $item->id }}">Catat pengembalian unit ini</label>
                        </div>
                        @if($item->returned_at !== null)
                            <p class="text-warning">Unit ini sudah tercatat kembali. Pengiriman ulang hanya diterima untuk batch identik yang telah tersimpan.</p>
                        @endif
                        @if($item->lost_at !== null)
                            <div class="alert alert-warning">
                                Unit dilaporkan hilang pada {{ $item->lost_at->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB.
                                Catatan: {{ $item->loss_notes }}.
                                Isi catatan penemuan sebelum menerima pengembaliannya. Unit tetap nonaktif sampai inspeksi Super Admin.
                            </div>
                        @endif
                        @if($photoId=$unitPhotos->get($item->inventory_unit_id))<img width="100" loading="lazy" alt="Foto alat" src="{{ route('admin.inventory.photo',$photoId) }}">@endif
                        <p>Kondisi saat dipinjam: {{ $item->condition_out }}</p>
                        @if($photoEnabled)
                            <div x-data="{preview:null, destroy(){if(this.preview) URL.revokeObjectURL(this.preview)}}">
                                <label for="proof-{{ $item->id }}">Foto bukti unit (maksimal 5 MiB)</label>
                                <input id="proof-{{ $item->id }}" class="form-control mb-2" type="file" accept="image/jpeg,image/png,image/webp"
                                    @change="if(preview) URL.revokeObjectURL(preview); const file=$event.target.files[0]; preview=file && ['image/jpeg','image/png','image/webp'].includes(file.type) && file.size<=5242880 ? URL.createObjectURL(file) : null"
                                    wire:model="photos.{{ $item->id }}">
                                <template x-if="preview"><img width="120" class="rounded" alt="Preview foto bukti unit" :src="preview"></template>
                                @error('photos.'.$item->id)<small class="text-danger">{{ $message }}</small>@enderror
                            </div>
                        @endif
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="return-date-{{ $item->id }}">Waktu kembali aktual (WIB)</label>
                                <input type="datetime-local" step="1" class="form-control" id="return-date-{{ $item->id }}"
                                    wire:model="returns.{{ $item->id }}.returned_at">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="return-condition-{{ $item->id }}">Kondisi saat diterima</label>
                                <select class="form-select" id="return-condition-{{ $item->id }}" wire:model="returns.{{ $item->id }}.condition_in">
                                    <option value="">Pilih kondisi</option>
                                    <option value="good">Baik</option>
                                    <option value="minor_damage">Rusak ringan</option>
                                    <option value="major_damage">Rusak berat</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="return-notes-{{ $item->id }}">{{ $item->lost_at !== null ? 'Catatan penemuan (wajib)' : 'Catatan kerusakan / keterlambatan / pengembalian' }}</label>
                                <textarea class="form-control" id="return-notes-{{ $item->id }}" maxlength="5000"
                                    wire:model="returns.{{ $item->id }}.return_notes"></textarea>
                            </div>
                        </div>
                    </fieldset>
                @endforeach
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">Simpan Pengembalian</button>
            </form>
        @endif
    </div>
</div>
