<div>
    <h4>Tambah Peminjaman Alat</h4>
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if(!$allocation)
        <section class="card p-3 p-md-4">
            <h5>Informasi peminjaman</h5>
            <div class="row g-3">
                @foreach(['borrower_name'=>'Nama peminjam','student_number'=>'NIM','study_program'=>'Program studi','whatsapp_number'=>'WhatsApp','borrowed_at'=>'Tanggal pinjam (WIB)','due_at'=>'Jatuh tempo (WIB)'] as $field=>$label)
                    <div class="col-12 col-md-6 col-xl-4">
                        <label for="loan-{{ $field }}">{{ $label }}</label>
                        <input id="loan-{{ $field }}" class="form-control" wire:model="data.{{ $field }}" type="{{ in_array($field,['borrowed_at','due_at']) ? 'date' : ($field==='whatsapp_number' ? 'tel' : 'text') }}" required>
                        @error($field)<small class="text-danger">{{ $message }}</small>@enderror
                    </div>
                @endforeach
                @foreach(['purpose'=>'Keperluan','notes'=>'Catatan (opsional)'] as $field=>$label)
                    <div class="col-md-6"><label for="loan-{{ $field }}">{{ $label }}</label>
                        <textarea id="loan-{{ $field }}" rows="2" class="form-control" wire:model="data.{{ $field }}"></textarea>
                        @error($field)<small class="text-danger">{{ $message }}</small>@enderror
                    </div>
                @endforeach
            </div>
        </section>
        <section class="card p-3 p-md-4">
            <h5>Daftar alat dipinjam</h5>
            <label for="product-search">Cari barang berdasarkan nama atau kode</label>
            <input id="product-search" type="search" class="form-control mb-3" maxlength="150" wire:model.live.debounce.300ms="search">
            @if($catalog->isEmpty())<p class="text-muted">Barang tidak ditemukan.</p>@endif
            @foreach($rows as $index=>$row)
                @php($product=$chosen->get((int)($row['product'] ?? 0)))
                <article wire:key="line-{{ $index }}" class="border rounded-3 p-3 mb-3">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label for="product-{{ $index }}">Barang {{ $loop->iteration }}</label>
                            <select id="product-{{ $index }}" class="form-select" wire:model.live="rows.{{ $index }}.product">
                                <option value="">Pilih barang</option>
                                @foreach($catalog->merge($product ? [$product] : []) as $option)
                                    <option value="{{ $option->id }}" @disabled(!$option->available)>{{ $option->name }} · {{ $option->inventory_code }} ({{ $option->available }} tersedia)</option>
                                @endforeach
                            </select>
                            @error("rows.$index.product")<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="col-6 col-md-3">
                            <label for="qty-{{ $index }}">Jumlah</label>
                            <div class="input-group">
                                <button type="button" class="btn btn-outline-secondary" aria-label="Kurangi jumlah" @click="$wire.set('rows.{{ $index }}.quantity',Math.max(1,Number($refs.qty{{ $index }}.value)-1))">−</button>
                                <input type="number" id="qty-{{ $index }}" x-ref="qty{{ $index }}" min="1" max="{{ min(50,$product?->available ?? 0) }}" step="1" wire:model.live.blur="rows.{{ $index }}.quantity" class="form-control">
                                <button type="button" class="btn btn-outline-secondary" @disabled(!$product?->available) aria-label="Tambah jumlah" @click="$wire.set('rows.{{ $index }}.quantity',Math.min({{ min(50,$product?->available ?? 0) }},Number($refs.qty{{ $index }}.value)+1))">+</button>
                            </div>
                            @error("rows.$index.quantity")<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                        <div class="col-6 col-md-3"><label for="mode-{{ $index }}">Cara memilih unit fisik</label>
                            <select id="mode-{{ $index }}" aria-describedby="mode-help-{{ $index }}" class="form-select" wire:model.live="rows.{{ $index }}.mode"><option value="auto">Otomatis</option><option value="manual">Pilih manual</option></select>
                        </div>
                    </div>
                    <p id="mode-help-{{ $index }}" class="form-text mb-0">
                        {{ ($row['mode'] ?? 'auto') === 'manual'
                            ? 'Centang kode inventaris pada alat yang akan diserahkan. Jumlah pilihan harus sesuai jumlah pinjaman.'
                            : 'Sistem memilih unit yang tersedia sesuai jumlah pinjaman. Kodenya dapat diperiksa sebelum konfirmasi.' }}
                    </p>
                    <div class="d-flex align-items-center flex-wrap gap-2 mt-3">
                        @if($product)
                            @if($photo=$photos->get($product->id))
                                <img width="64" height="48" class="object-fit-cover rounded" src="{{ route('admin.inventory.photo',$photo) }}" alt="Foto {{ $product->name }}">
                            @else <span class="text-muted"><i class="bi bi-image"></i> Belum ada foto</span>@endif
                            <span>{{ $product->category?->name }}</span>
                            <x-equipment-status :status="!$product->is_active ? 'inactive' : ($product->available ? 'available' : 'unavailable')"/>
                            <span>{{ $product->available }} unit tersedia</span>
                        @endif
                        <button type="button" wire:click="removeRow({{ $index }})" class="btn btn-sm btn-outline-danger ms-auto">Hapus item</button>
                    </div>
                    @isset($manual[$index])
                        <label class="mt-3" for="filter-{{ $index }}">Cari kode unit fisik</label>
                        <input id="filter-{{ $index }}" class="form-control mb-2" wire:model.live.debounce.300ms="rows.{{ $index }}.filter">
                        <div style="max-height:16rem;overflow:auto">
                            @forelse($manual[$index] as $unit)
                                <label class="list-group-item py-1 d-flex flex-wrap align-items-center gap-2">
                                    <input type="checkbox" wire:model="rows.{{ $index }}.units" value="{{ $unit->id }}" @disabled($unit->stock_status !== 'available')>
                                    @if($photo=$photos->get($unit->id))<img width="40" src="{{ route('admin.inventory.photo',$photo) }}" alt="Foto unit">@endif
                                    {{ $unit->inventory_code }} <x-equipment-status :status="$unit->condition"/><x-equipment-status :status="$unit->stock_status"/>
                                </label>
                            @empty <p>Tidak ada unit yang cocok.</p>@endforelse
                        </div>
                        @error("rows.$index.units")<small class="text-danger">{{ $message }}</small>@enderror
                    @endisset
                </article>
            @endforeach
            <button type="button" class="btn btn-outline-primary align-self-start" wire:click="addRow" @disabled(count($rows)>=50)>+ Tambah Alat</button>
        </section>
    @endif
    @if($allocation)
        <section class="card overflow-hidden" aria-labelledby="loan-review-title">
            <header class="card-header border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h5 id="loan-review-title" class="mb-0">Review peminjaman</h5>
                <span class="fw-semibold text-primary">{{ count($allocation) }} jenis alat · {{ array_sum(array_map('count', $allocation)) }} unit dipinjam</span>
            </header>
            <div class="card-body pt-4">
                <div class="row g-4">
                    <section class="col-12 col-lg-7">
                        <h6 class="mb-3"><i class="bi bi-person me-1" aria-hidden="true"></i> Peminjam</h6>
                        <p class="fs-5 fw-semibold text-break mb-2">{{ $data['borrower_name'] }}</p>
                        <dl class="row small mb-0">
                            @foreach(['student_number'=>'NIM','study_program'=>'Program studi','whatsapp_number'=>'WhatsApp'] as $field=>$label)
                                <dt class="col-5 col-sm-4 text-body-secondary">{{ $label }}</dt>
                                <dd class="col-7 col-sm-8 text-break">{{ $data[$field] }}</dd>
                            @endforeach
                        </dl>
                    </section>
                    <section class="col-12 col-lg-5">
                        <div class="bg-body-tertiary border rounded-3 p-3 h-100">
                            <h6 class="mb-3"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i> Jadwal peminjaman</h6>
                            <dl class="mb-0">
                                @foreach(['borrowed_at'=>'Tanggal pinjam','due_at'=>'Jatuh tempo'] as $field=>$label)
                                    <dt class="small text-body-secondary">{{ $label }}</dt>
                                    <dd @class(['fw-semibold','mb-0'=>$loop->last])>
                                        <time datetime="{{ substr($data[$field],0,10) }}">{{ \Carbon\CarbonImmutable::parse($data[$field], 'Asia/Jakarta')->locale('id')->translatedFormat('d F Y') }}</time>
                                    </dd>
                                @endforeach
                            </dl>
                        </div>
                    </section>
                </div>
                <dl class="border-top mt-3 pt-3 mb-4">
                    <dt class="small text-body-secondary">Keperluan</dt>
                    <dd class="text-break">{{ $data['purpose'] }}</dd>
                    @if(filled($data['notes']))
                        <dt class="small text-body-secondary">Catatan</dt>
                        <dd class="text-break">{{ $data['notes'] }}</dd>
                    @endif
                </dl>
                <h6>Alat yang akan diserahkan</h6>
                <p class="small text-body-secondary mb-2">Cocokkan kode berikut dengan label pada setiap alat.</p>
                <ul class="list-group">
                    @foreach($allocation as $index=>$units)
                        <li class="list-group-item p-3">
                            <div class="d-flex align-items-baseline justify-content-between gap-2">
                                <strong class="text-break">{{ $chosen->get($rows[$index]['product'])?->name }}</strong>
                                <span class="text-nowrap fw-semibold">{{ count($units) }} unit</span>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                @foreach($units as $unit)
                                    <span class="border rounded px-2 py-1 font-monospace small text-break">{{ $unit['code'] }}</span>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
            <form wire:submit="save" class="card-footer border-top d-grid gap-2 d-sm-flex justify-content-sm-end" data-admin-confirm-title="Catat peminjaman dan serahkan unit yang tercantum?" data-admin-confirm-text="Ya, proses peminjaman">
                <button type="button" wire:click="edit" class="btn btn-outline-secondary">Ubah pilihan</button>
                <button type="submit" wire:loading.attr="disabled" class="btn btn-primary"><i class="bi bi-check2 me-1" aria-hidden="true"></i> Proses peminjaman</button>
                <span wire:loading wire:target="save" role="status">Memproses…</span>
            </form>
        </section>
    @else
        <section class="card p-3 p-md-4" aria-live="polite">
            <h5>Ringkasan transaksi</h5>
            <p>{{ $chosen->count() }} jenis alat · {{ collect($rows)->filter(fn($row)=>!empty($row['product']))->sum(fn($row)=>is_numeric($row['quantity'] ?? null) ? (int)$row['quantity'] : 0) }} unit dipinjam · {{ $chosen->sum('available') }} unit tersedia pada barang pilihan</p>
            <p><span x-text="$wire.data.borrowed_at"></span> – <span x-text="$wire.data.due_at"></span> </p>
            @if($chosen->isNotEmpty())
                <ul class="list-group list-group-flush mb-3">
                    @foreach($rows as $row)
                        @if($selected = $chosen->get((int) ($row['product'] ?? 0)))
                            <li class="list-group-item px-0 d-flex justify-content-between gap-2">
                                <span class="text-break">{{ $selected->name }}</span>
                                <strong class="text-nowrap">{{ is_numeric($row['quantity'] ?? null) ? (int) $row['quantity'] : '—' }} unit</strong>
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
            <button class="btn btn-primary align-self-start" wire:click="review" wire:loading.attr="disabled">Periksa peminjaman</button>
            <small wire:loading role="status">Memproses…</small>
        </section>
    @endif
</div>
