<div>
    @if($tab === 'units' && $model)
        <p class="small">Unit model #{{ $model }} <a href="{{ route('admin.inventory.index', ['tab'=>'units']) }}">Tampilkan semua unit</a></p>
    @endif
    <div class="page-heading"><h3>Inventory Peralatan</h3><p class="text-muted">Kategori, model, unit fisik, dan histori peralatan WPiTV.</p></div>
    
@if(session()->has('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif

    
@if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">
@foreach($errors->all() as $error)<li>{{ $error }}</li>
@endforeach
</ul></div>
@endif

    <div class="row g-2 mb-3">
        
@foreach(['available'=>'Tersedia','on_loan'=>'Dipinjam','maintenance'=>'Perawatan','damaged'=>'Rusak','lost'=>'Hilang','retired'=>'Retired','model_inactive'=>'Model nonaktif','integrity_error'=>'Perlu pemeriksaan'] as $key=>$label)
            <div class="col-6 col-md-3"><div class="card mb-0"><div class="card-body p-3"><small>{{ $label }}</small><div class="fs-4 fw-bold {{ $key==='integrity_error' && $stock[$key] ? 'text-danger' : '' }}">{{ $stock[$key] }}</div></div></div></div>
        
@endforeach

    </div>
    <nav class="nav nav-pills gap-2 mb-3" aria-label="Inventory">
        
@foreach(['models'=>'Model peralatan','categories'=>'Kategori','units'=>'Unit fisik','audit'=>'Audit log'] as $value=>$label)
            
@if($value!=='audit' || auth()->user()->can('equipment.audit.view'))
                <a class="nav-link {{ $activeTab===$value?'active':'' }}" href="{{ route('admin.inventory.index',['tab'=>$value]) }}">{{ $label }}</a>
            
@endif

        
@endforeach

    </nav>
    
@if($operation)
        <section class="card border border-primary" aria-label="Form Inventory">
            <div class="card-header"><h5>{{ ['category.create'=>'Tambah kategori','category.update'=>'Ubah kategori','model.create'=>'Tambah model dan stok awal','model.update'=>'Ubah model','stock.add'=>'Tambah stok','unit.condition'=>'Ubah kondisi dan serial','unit.maintenance_start'=>'Mulai perawatan','unit.maintenance_finish'=>'Selesaikan perawatan','unit.lost'=>'Catat unit hilang','unit.found'=>'Catat unit ditemukan','unit.retire'=>'Retire unit','model.photo_add'=>'Tambah foto model','unit.photo_add'=>'Tambah foto unit'][$operation] }} 
@if($recordId)<small>#{{ $recordId }}</small>
@endif
</h5></div>
            <div class="card-body">
                <form wire:submit="save" wire:confirm="Simpan tindakan ini beserta audit?">
                    <div class="row g-3">
                        
@if(in_array($operation,['category.create','category.update','model.create','model.update']))
                            <div class="col-md-6"><label for="eq-name" class="form-label">Nama</label><input id="eq-name" wire:model="form.name" maxlength="150" required class="form-control"></div>
                            
@if(str_ends_with($operation,'.create'))<div class="col-md-6"><label for="eq-code" class="form-label">Kode unik (huruf kapital, angka, tanda hubung)</label><input id="eq-code" wire:model="form.code" maxlength="{{ $operation==='category.create'?40:60 }}" pattern="[A-Z0-9]+(-[A-Z0-9]+)*" required class="form-control"></div>
@endif

                            
@if(str_starts_with($operation,'model.'))
                                <div class="col-md-6"><label for="eq-category" class="form-label">Kategori</label><select id="eq-category" wire:model="form.category_id" required class="form-select"><option value="">Pilih kategori</option>
@foreach($categories as $item)<option value="{{ $item->id }}">{{ $item->name }}{{ $item->is_active?'':' (nonaktif)' }}</option>
@endforeach
</select></div>
                            
@endif

                            <div class="col-md-6"><label for="eq-active" class="form-label">Status</label><select id="eq-active" wire:model="form.is_active" class="form-select"><option value="1">Aktif</option><option value="0">Nonaktif</option></select></div>
                            <div class="col-12"><label for="eq-description" class="form-label">Deskripsi</label><textarea id="eq-description" wire:model="form.description" maxlength="10000" class="form-control"></textarea></div>
                        
@endif

                        
@if(in_array($operation,['model.create','stock.add']))
                            <div class="col-md-6"><label for="eq-quantity" class="form-label">Jumlah unit {{ $operation==='model.create'?'awal':'tambahan' }}</label><input id="eq-quantity" type="number" min="1" max="100" wire:model="form.quantity" class="form-control" required><small>Nomor aset diterbitkan otomatis dan tidak digunakan ulang.</small></div>
                        
@endif

                        
@if(in_array($operation,['model.create','stock.add','unit.condition']))
                            <div class="col-md-6"><label for="eq-condition" class="form-label">Kondisi fisik</label><select id="eq-condition" wire:model="form.condition" class="form-select"><option value="good">Baik</option><option value="minor_damage">Rusak ringan</option><option value="major_damage">Rusak berat</option></select></div>
                        
@endif

                        
@if($operation==='unit.condition')<div class="col-md-6"><label for="eq-serial" class="form-label">Nomor serial (opsional)</label><input id="eq-serial" wire:model="form.serial_number" maxlength="150" class="form-control"></div>
@endif

                        
@if(str_ends_with($operation,'.photo_add'))
                            <div class="col-12"><label for="eq-photos" class="form-label">Foto privat {{ $operation==='model.photo_add'?'(satu cover)':'(1–8 foto)' }}</label><input id="eq-photos" type="file" wire:model="photos" multiple accept="image/jpeg,image/png,image/webp" class="form-control"><small>64–4096 px, maksimal 5.000.000 byte/file. Foto tambahan/revisi wajib alasan. Histori foto dipertahankan.</small><div wire:loading wire:target="photos">Memvalidasi unggahan…</div></div>
                        
@endif

                        <div class="col-12"><label for="eq-reason" class="form-label">Alasan {{ in_array($operation,['category.create','model.create'])?'(opsional pada pendaftaran biasa)':'' }}</label><textarea id="eq-reason" wire:model="form.reason" maxlength="1000" class="form-control" @required(!in_array($operation,['category.create','model.create','model.photo_add','unit.photo_add']))></textarea></div>
                    </div>
                    
@if(str_starts_with($operation,'unit.') && !in_array($operation,['unit.condition','unit.photo_add']))<p class="alert alert-warning mt-3">Unit dipinjam tidak dapat diubah. Retired terminal; unit hilang yang ditemukan masuk maintenance. Alasan dan audit wajib.</p>
@endif

                    <div class="d-flex gap-2 mt-3"><button class="btn btn-primary" type="submit" wire:loading.attr="disabled" wire:target="save,photos">Simpan</button><button type="button" class="btn btn-secondary" wire:click="cancel">Batal</button></div>
                </form>
            </div>
        </section>
    
@endif

    <section class="card">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2">
            <h5 class="mb-0">{{ ['models'=>'Model peralatan','categories'=>'Kategori','units'=>'Unit fisik','audit'=>'Audit log'][$activeTab] }}</h5>
            
@if($activeTab==='categories')
@can('equipment.category.create')<button class="btn btn-primary btn-sm" wire:click="begin('category.create')">Tambah kategori</button>
@endcan

@endif

            
@if($activeTab==='models')
@can('equipment.model.create')<button class="btn btn-primary btn-sm" wire:click="begin('model.create')">Tambah model & stok awal</button>
@endcan

@endif

        </div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-4"><label class="visually-hidden" for="eq-search">Pencarian</label><input id="eq-search" wire:model.live.debounce.300ms="search" placeholder="Cari nama, kode, serial / audit…" maxlength="100" class="form-control"></div>
                
@if(in_array($activeTab,['models','units']))<div class="col-md-3"><label class="visually-hidden" for="eq-filter-category">Filter kategori</label><select id="eq-filter-category" wire:model.live="category" class="form-select"><option value="">Semua kategori</option>
@foreach($categories as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>
@endforeach
</select></div>
@endif

                
@if($activeTab!=='audit')<div class="col-md-3"><label class="visually-hidden" for="eq-filter-status">Filter status</label><select id="eq-filter-status" wire:model.live="status" class="form-select"><option value="">Semua status</option>
@if($activeTab==='units')
@foreach(['active','maintenance','lost','retired','on_loan'] as $state)<option value="{{ $state }}">{{ $state }}</option>
@endforeach
 
@else
<option value="1">Aktif</option><option value="0">Nonaktif</option>
@endif
</select></div>
@endif

                
@if($activeTab==='units')<div class="col-md-3"><label class="visually-hidden" for="eq-filter-condition">Filter kondisi</label><select id="eq-filter-condition" wire:model.live="condition" class="form-select"><option value="">Semua kondisi</option>
@foreach(['good','minor_damage','major_damage'] as $state)<option value="{{ $state }}">{{ $state }}</option>
@endforeach
</select></div>
@endif

                <div class="col-auto"><label class="visually-hidden" for="eq-page-size">Baris per halaman</label><select id="eq-page-size" wire:model.live="perPage" class="form-select">
@foreach([10,25,50] as $size)<option value="{{ $size }}">{{ $size }} / halaman</option>
@endforeach
</select></div>
            </div>
            <div class="table-responsive"><table class="table align-middle"><thead><tr>
                
@if($activeTab==='categories')<th>Kode</th><th>Nama / deskripsi</th><th>Status</th><th>Tindakan</th>
                
@elseif($activeTab==='models')<th>Model</th><th>Kategori</th><th>Unit / status</th><th>Tindakan</th>
                
@elseif($activeTab==='units')<th>Aset / serial</th><th>Model / kategori</th><th>Kondisi / status</th><th>Tindakan</th>
                
@else
<th>Waktu UTC</th><th>Pelaku / aksi</th><th>Target</th><th>Alasan</th>
@endif

            </tr></thead><tbody>
            
@forelse($rows as $row)<tr wire:key="{{ $activeTab }}-{{ $row->id }}">
                
@if($activeTab==='categories')
                    <td>{{ $row->code }}</td><td><strong>{{ $row->name }}</strong><div class="small text-muted">{{ $row->description }}</div></td><td>{{ $row->is_active?'Aktif':'Nonaktif' }}</td><td>
@can('equipment.category.update')<button class="btn btn-sm btn-outline-primary" wire:click="begin('category.update',{{ $row->id }})">Ubah / nonaktifkan</button>
@endcan
</td>
                
@elseif($activeTab==='models')
                    <td>
@if($row->cover_id)
@can('equipment.photo.view')<img src="{{ route('admin.inventory.photos',['kind'=>'model','photo'=>$row->cover_id]) }}" alt="Cover {{ $row->name }}" width="64" height="64" class="object-fit-cover rounded me-2" loading="lazy">
@endcan
 
@endif
<strong>{{ $row->name }}</strong><div class="small text-muted">{{ $row->model_code }}</div></td><td>{{ $row->category_name }}</td><td>{{ $row->units_count }} unit<br>{{ $row->is_active?'Aktif':'Nonaktif' }}</td>
                    <td><div class="d-flex flex-wrap gap-1">
@can('equipment.model.update')<button class="btn btn-sm btn-outline-primary" wire:click="begin('model.update',{{ $row->id }})">Ubah</button>
@endcan
 
@can('equipment.stock.add')<button class="btn btn-sm btn-outline-success" wire:click="begin('stock.add',{{ $row->id }})">Tambah stok</button>
@endcan
 
@can('equipment.model.photo.add')<button class="btn btn-sm btn-outline-primary" wire:click="begin('model.photo_add',{{ $row->id }})">Tambah foto</button>
@endcan
<button class="btn btn-sm btn-outline-secondary" wire:click="inspect('model',{{ $row->id }})">Detail / foto</button></div></td>
                
@elseif($activeTab==='units')
                    <td><strong>{{ $row->asset_code }}</strong><div class="small text-muted">{{ $row->serial_number ?: 'Tanpa serial' }}</div></td><td>{{ $row->model_name }}<div class="small text-muted">{{ $row->category_name }}</div></td><td>{{ $row->physical_condition }}<br><span class="badge bg-{{ $row->borrowed?'warning':'secondary' }}">{{ $row->borrowed?'Dipinjam':$row->operational_status }}</span>
@if($row->closed_reason)<div class="small">{{ $row->closed_reason }}</div>
@endif
</td>
                    <td><div class="d-flex flex-wrap gap-1">
                        
@if(!$row->borrowed && in_array($row->operational_status,['active','maintenance']))
@can('equipment.unit.condition')<button class="btn btn-sm btn-outline-primary" wire:click="begin('unit.condition',{{ $row->id }})">Kondisi / serial</button>
@endcan

@endif

                        
@if(!$row->borrowed && $row->operational_status!=='retired')
                            
@foreach(['unit.maintenance_start'=>['equipment.unit.maintenance','Perawatan','active'],'unit.maintenance_finish'=>['equipment.unit.maintenance','Selesai perawatan','maintenance'],'unit.found'=>['equipment.unit.recover','Ditemukan','lost']] as $op=>$button)
                                
@if($row->operational_status===$button[2])
@can($button[0])<button class="btn btn-sm btn-outline-warning" wire:click="begin('{{ $op }}',{{ $row->id }})">{{ $button[1] }}</button>
@endcan

@endif

                            
@endforeach

                            
@if(in_array($row->operational_status,['active','maintenance']))
@can('equipment.unit.loss')<button class="btn btn-sm btn-outline-danger" wire:click="begin('unit.lost',{{ $row->id }})">Hilang</button>
@endcan

@endif

                            
@can('equipment.unit.retire')<button class="btn btn-sm btn-outline-danger" wire:click="begin('unit.retire',{{ $row->id }})">Retire</button>
@endcan

                            
@can('equipment.unit.photo.add')<button class="btn btn-sm btn-outline-primary" wire:click="begin('unit.photo_add',{{ $row->id }})">Tambah foto</button>
@endcan

                        
@endif

                        <button class="btn btn-sm btn-outline-secondary" wire:click="inspect('unit',{{ $row->id }})">Detail / foto</button>
                    </div></td>
                
@else
<td>{{ $row->occurred_at }}</td><td>{{ $row->actor_name_snapshot }}<div>{{ $row->event_name }}</div></td><td>{{ $row->subject_type }} #{{ $row->subject_id }}</td><td>{{ $row->reason }}</td>
@endif

            </tr>
@empty
<tr><td colspan="4" class="text-center text-muted py-4">Belum ada data yang sesuai. Tambahkan kategori, lalu model dan unit awal.</td></tr>
@endforelse

            </tbody></table></div>
            {{ $rows->links() }}
        </div>
    </section>
    
@if($detailId)
        <section class="card"><div class="card-header d-flex justify-content-between"><h5>Foto {{ $detailKind }} #{{ $detailId }} · histori terbaru</h5><button class="btn btn-sm btn-secondary" wire:click="inspect('{{ $detailKind }}',0)">Tutup</button></div><div class="card-body"><div class="row g-3">
            
@forelse($gallery as $photo)<div class="col-6 col-md-3"><a href="{{ route('admin.inventory.photos',['kind'=>$detailKind,'photo'=>$photo->id]) }}" target="_blank" rel="noopener"><img class="img-fluid rounded" alt="Dokumentasi peralatan #{{ $photo->id }}" loading="lazy" src="{{ route('admin.inventory.photos',['kind'=>$detailKind,'photo'=>$photo->id]) }}"></a><small>{{ $photo->created_at }} UTC</small></div>
@empty
<p class="text-muted">Belum ada foto.</p>
@endforelse

        </div></div></section>
    
@endif

</div>
