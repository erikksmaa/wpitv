@if($operation)
    @php($title = ['category.create'=>'Tambah kategori','category.update'=>'Edit kategori','model.create'=>'Tambah peralatan dan stok awal','model.update'=>'Edit peralatan','stock.add'=>'Tambah stok','unit.condition'=>'Edit unit','unit.maintenance_start'=>'Mulai perawatan','unit.maintenance_finish'=>'Selesaikan perawatan','unit.lost'=>'Tandai hilang','unit.found'=>'Catat unit ditemukan','unit.retire'=>'Nonaktifkan permanen','model.photo_add'=>'Unggah foto peralatan','unit.photo_add'=>'Unggah foto unit'][$operation])
    {{-- Preserve the browser-managed open attribute while Livewire updates the form. --}}
    <dialog class="modal inventory-dialog" wire:ignore.self wire:key="inventory-modal-{{ $token }}" aria-labelledby="inventory-modal-title"
        x-init="$el.showModal()" x-on:cancel.prevent="$wire.cancel()">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div><h2 id="inventory-modal-title" class="modal-title h5">{{ $title }}</h2>@if($target)<div class="small text-muted">{{ $target }}</div>@endif</div>
                    <button type="button" class="btn-close" aria-label="Tutup form" wire:click="cancel" wire:loading.attr="disabled" wire:target="save"></button>
                </div>
                @php($hint = match($operation) {
                    'unit.retire' => 'Unit tidak dapat diaktifkan kembali. Riwayatnya tetap tersimpan.',
                    'unit.found' => 'Unit masuk perawatan dan harus diperiksa sebelum bisa dipinjam kembali.',
                    'unit.lost' => 'Unit tidak dapat dipinjam sampai ditemukan dan selesai diperiksa.',
                    'unit.maintenance_start' => 'Unit tidak tersedia selama perawatan.',
                    'unit.maintenance_finish' => 'Unit harus berkondisi baik untuk menyelesaikan perawatan.',
                    default => null,
                })
                @if($hint)<p class="small text-muted px-3 pt-3 mb-0">{{ $hint }}</p>@endif
            <div class="modal-body">
                <form @if($operation !== 'unit.retire') novalidate @endif x-data="{ saving: false }" x-on:submit.prevent="if (!saving) { saving = true; $wire.save().finally(() => saving = false) }">
                    @include('livewire.admin.equipment-field-error', ['field'=>'action'])
                    <div class="row g-3">
                        
@if(in_array($operation,['category.create','category.update','model.create','model.update']))
                            <div class="col-md-6"><label for="eq-name" class="form-label">Nama</label><input id="eq-name" wire:model="form.name" maxlength="150" required class="form-control">@include('livewire.admin.equipment-field-error', ['field'=>'form.name'])</div>
                            
@if(str_ends_with($operation,'.create'))<div class="col-md-6"><label for="eq-code" class="form-label">Kode unik (huruf kapital, angka, tanda hubung)</label><input id="eq-code" wire:model="form.code" x-on:input="$el.value = $el.value.toUpperCase()" maxlength="{{ $operation==='category.create'?40:60 }}" pattern="[A-Z0-9]+(\-[A-Z0-9]+)*" required class="form-control text-uppercase">@include('livewire.admin.equipment-field-error', ['field'=>'form.code'])</div>
@endif

                            
@if(str_starts_with($operation,'model.'))
                                <div class="col-md-6"><label for="eq-category" class="form-label">Kategori</label><select id="eq-category" wire:model="form.category_id" required class="form-select"><option value="">Pilih kategori</option>
@foreach($categories as $item)<option value="{{ $item->id }}" @disabled(!$item->is_active && (string)$item->id !== (string)($form['category_id'] ?? ''))>{{ $item->name }}{{ $item->is_active?'':' (nonaktif)' }}</option>
@endforeach
</select>@include('livewire.admin.equipment-field-error', ['field'=>'form.category_id'])</div>
                            
@endif

                            <div class="col-md-6"><label for="eq-active" class="form-label">Status</label><select id="eq-active" wire:model="form.is_active" class="form-select"><option value="1">Aktif</option><option value="0">Nonaktif</option></select>@include('livewire.admin.equipment-field-error', ['field'=>'form.is_active'])</div>
                            <div class="col-12"><label for="eq-description" class="form-label">Deskripsi</label><textarea id="eq-description" wire:model="form.description" maxlength="10000" class="form-control"></textarea>@include('livewire.admin.equipment-field-error', ['field'=>'form.description'])</div>
                        
@endif

                        
@if(in_array($operation,['model.create','stock.add']))
                            <div class="col-md-6"><label for="eq-quantity" class="form-label">Jumlah unit {{ $operation==='model.create'?'awal':'tambahan' }}</label><input id="eq-quantity" type="number" min="1" max="100" wire:model="form.quantity" class="form-control" required>@include('livewire.admin.equipment-field-error', ['field'=>'form.quantity'])<small>Nomor aset diterbitkan otomatis dan tidak digunakan ulang.</small></div>
                        
@endif

                        
@if(in_array($operation,['model.create','stock.add','unit.condition']))
                            <div class="col-md-6"><label for="eq-condition" class="form-label">Kondisi fisik</label><select id="eq-condition" wire:model="form.condition" class="form-select"><option value="good">Baik</option><option value="minor_damage">Rusak ringan</option><option value="major_damage">Rusak berat</option></select>@include('livewire.admin.equipment-field-error', ['field'=>'form.condition'])</div>
                        
@endif

                        
@if($operation==='unit.condition')<div class="col-md-6"><label for="eq-serial" class="form-label">Nomor serial (opsional)</label><input id="eq-serial" wire:model="form.serial_number" maxlength="150" class="form-control">@include('livewire.admin.equipment-field-error', ['field'=>'form.serial_number'])</div>
@endif

                        
@if(str_ends_with($operation,'.photo_add'))
                            <div class="col-12"><label for="eq-photos" class="form-label">Foto {{ $operation==='model.photo_add'?'(satu foto sampul)':'(1–8 foto)' }}</label><input id="eq-photos" type="file" wire:model="photos" multiple accept="image/jpeg,image/png,image/webp" class="form-control">@include('livewire.admin.equipment-field-error', ['field'=>'photos','nested'=>true])<small>JPG, PNG, atau WebP. Maksimal 5 MB per foto. Isi alasan jika menambahkan atau mengganti foto yang sudah ada.</small><div wire:loading wire:target="photos">Memvalidasi unggahan…</div></div>
                        
@endif

                        <div class="col-12"><label for="eq-reason" class="form-label">Alasan {{ in_array($operation,['category.create','model.create'])?'(opsional pada pendaftaran biasa)':'' }}</label><textarea id="eq-reason" wire:model="form.reason" maxlength="1000" class="form-control" @required(!in_array($operation,['category.create','model.create','model.photo_add','unit.photo_add']))></textarea>@include('livewire.admin.equipment-field-error', ['field'=>'form.reason'])</div>
                    </div>
                    
@if($operation === 'unit.retire')
                    <div class="form-check mt-3"><input id="eq-retire-confirm" type="checkbox" class="form-check-input" required><label for="eq-retire-confirm" class="form-check-label">Saya memahami unit tidak dapat diaktifkan kembali.</label></div>
                    @endif
                    <div class="modal-footer px-0 pb-0 mt-3"><button class="btn btn-primary" type="submit" wire:loading.attr="disabled" wire:target="save,photos" wire:loading.class="opacity-75"><span wire:loading.remove wire:target="save">{{ $operation === 'unit.retire' ? 'Nonaktifkan permanen' : 'Simpan' }}</span><span wire:loading wire:target="save">Menyimpan…</span></button><button type="button" class="btn btn-secondary" wire:click="cancel" wire:loading.attr="disabled" wire:target="save">Batal</button></div>
                </form>
            </div>
            </div>
        </div>
    </dialog>
@endif
