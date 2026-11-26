@use('App\Services\SocialReport','Social')
<div>
    <style>
        .social-actions { position:relative; }
        .social-actions summary { list-style:none; }
        .social-actions summary::-webkit-details-marker { display:none; }
        .social-actions .dropdown-menu { position:absolute; right:0; top:100%; z-index:25; min-width:12rem; }
        .social-dialog[open] { display:block; position:fixed; border:0; background:transparent; padding:0; width:100vw; height:100dvh; max-height:100dvh; max-width:100vw; margin:0; }
        .social-dialog::backdrop { background:rgba(8,16,32,.65); }
        .social-dialog .modal-dialog { pointer-events:auto; }
    </style>
    <div class="page-heading d-flex flex-wrap justify-content-between align-items-end gap-2">
        <div>
            <nav aria-label="Breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li class="breadcrumb-item"><a href="{{ route('admin.social.reports') }}">Media Sosial</a></li><li class="breadcrumb-item active" aria-current="page">{{ $accounts ? 'Akun Media Sosial' : 'Publikasi Konten' }}</li></ol></nav>
            <h1 class="h3 mb-0">{{ $accounts ? 'Akun Media Sosial' : 'Publikasi Konten' }}</h1>
        </div>
        @can('social.manage')<button type="button" class="btn btn-primary" wire:click="edit"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah {{ $accounts ? 'akun' : 'publikasi' }}</button>@endcan
    </div>
    @if($accounts && !$visibilityReady)
        <div class="alert alert-warning" role="alert">
            Pengaturan visibilitas website belum tersedia pada database. Impor skrip <code>database/sql/website_settings_and_social_integration.sql</code> melalui phpMyAdmin untuk mengaktifkan kolom <code>show_on_website</code> dan <code>display_order</code>.
        </div>
    @endif
    @if(session('social_notice'))<div class="alert alert-success" role="status">{{ session('social_notice') }}</div>@endif
    @if(session('social_error'))<div class="alert alert-danger" role="alert">{{ session('social_error') }}</div>@endif
    <section class="card"><div class="card-body pt-3">
        <div class="row g-2 align-items-end mb-3">
            <div class="col-sm-4"><label for="social-search" class="form-label small mb-1">Pencarian</label><input id="social-search" class="form-control" placeholder="Cari {{ $accounts?'nama atau username':'judul atau nama akun' }}" wire:model.live.debounce.300ms="search"></div>
            <div class="col-sm"><label for="social-platform" class="form-label small mb-1">Platform</label><select id="social-platform" class="form-select" wire:model.live="platform"><option value="">Semua platform</option>@foreach(Social::PLATFORMS as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-sm"><label for="social-filter" class="form-label small mb-1">{{ $accounts ? 'Status' : 'Jenis konten' }}</label><select id="social-filter" class="form-select" wire:model.live="filter"><option value="">Semua {{ $accounts?'status':'jenis' }}</option>@foreach(($accounts?['active'=>'Aktif','archived'=>'Nonaktif']:Social::TYPES) as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            @if($accounts && $visibilityReady)
                <div class="col-sm"><label for="social-visibility" class="form-label small mb-1">Visibilitas Website</label><select id="social-visibility" class="form-select" wire:model.live="visibility"><option value="">Semua visibilitas</option><option value="visible">Tampil di Website</option><option value="hidden">Tersembunyi</option></select></div>
            @endif
            <div class="col-auto"><label for="social-per-page" class="form-label small mb-1">Per halaman</label><select id="social-per-page" class="form-select" wire:model.live="perPage"><option value="10">10</option><option value="15">15</option><option value="25">25</option></select></div>
            <div class="col-auto"><button type="button" class="btn btn-outline-secondary" wire:click="resetFilters">Reset filter</button></div>
        </div>
        <span wire:loading class="small text-muted" role="status">Memuat…</span>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>{{ $accounts?'Nama akun':'Publikasi' }}</th><th>Platform</th><th>{{ $accounts?'Status':'Jenis & Metrik' }}</th>@if($accounts && $visibilityReady)<th>Tampil di Website</th><th>Urutan</th>@endif @can('social.manage')<th class="text-end">Tindakan</th>@endcan</tr></thead><tbody>
        @forelse($rows as $r)
            @php($obs = !$accounts ? ($latestMetrics->get($r->id) ?? null) : null)
            <tr wire:key="social-row-{{ $r->id }}"><td><strong>{{ $accounts?$r->display_name:$r->title }}</strong><div class="small text-muted">{{ $accounts?$r->handle:$r->display_name.' · '.\Carbon\Carbon::parse($r->published_at,'UTC')->setTimezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' }}</div>@if(!$accounts && $r->responsible_label)<div class="small text-muted">Penanggung jawab: {{ $r->responsible_label }}</div>@endif</td>
                <td>{{ Social::PLATFORMS[$r->platform] ?? ucfirst($r->platform) }}</td>
                <td>@if($accounts)<span class="badge {{ $r->archived_at ? 'bg-secondary' : 'bg-success' }}">{{ $r->archived_at?'Nonaktif':'Aktif' }}</span>@else<div>{{ Social::TYPES[Social::category($r->content_type)] }}</div>@if($obs)<div class="small text-muted">Tayang {{ number_format($obs->views ?? 0,0,',','.') }} · Suka {{ number_format($obs->likes ?? 0,0,',','.') }}</div>@endif @endif</td>
                @if($accounts && $visibilityReady)
                    <td>
                        @php($shown = (bool) ($r->show_on_website ?? false))
                        <div class="form-check form-switch m-0">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                role="switch"
                                id="social-vis-{{ $r->id }}"
                                @checked($shown)
                                @disabled(!auth()->user()->can('social.manage'))
                                wire:click="togglePublicVisibility({{ $r->id }}, {{ $r->lock_version }})"
                                aria-label="Tampilkan {{ $r->display_name }} di Website"
                            >
                            <label class="form-check-label small" for="social-vis-{{ $r->id }}">
                                {{ $shown ? 'Tampil' : 'Tersembunyi' }}
                            </label>
                        </div>
                        @if($shown && $r->archived_at)
                            <div class="small text-warning">Akun nonaktif (tersembunyi otomatis)</div>
                        @elseif($shown && blank($r->profile_url))
                            <div class="small text-danger">Tautan profil belum diisi</div>
                        @endif
                    </td>
                    <td><span class="badge bg-light text-dark border">{{ (int) ($r->display_order ?? 0) }}</span></td>
                @endif
                @can('social.manage')<td class="text-end">
                    <details class="social-actions d-inline-block" x-data x-on:click.outside="$el.open = false" x-on:keydown.escape="$el.open = false; $el.querySelector('summary').focus()">
                        <summary class="btn btn-sm btn-outline-secondary" aria-label="Tindakan {{ $accounts ? $r->display_name : $r->title }}"><i class="bi bi-three-dots" aria-hidden="true"></i></summary>
                        <div class="dropdown-menu dropdown-menu-end show shadow-sm mt-1 text-start">
                            <button type="button" class="dropdown-item" x-on:click="$el.closest('details').open = false" wire:click="edit({{ $r->id }})"><i class="bi bi-pencil me-2" aria-hidden="true"></i>Edit {{ $accounts?'akun':'dan catat metrik' }}</button>
                            @unless($accounts)<button type="button" class="dropdown-item text-danger" x-on:click="$el.closest('details').open = false" wire:confirm="Arsipkan publikasi ini?" wire:click="archive({{ $r->id }},{{ $r->lock_version }})"><i class="bi bi-archive me-2" aria-hidden="true"></i>Arsipkan</button>@endunless
                        </div>
                    </details>
                </td>@endcan
            </tr>
        @empty<tr><td colspan="{{ ($accounts && $visibilityReady ? 5 : 3) + (auth()->user()->can('social.manage') ? 1 : 0) }}" class="text-center text-muted py-4">Tidak ada data yang sesuai.</td></tr>@endforelse
        </tbody></table></div>{{ $rows->links() }}
    </div></section>
    @if($open)
        <dialog wire:key="social-form-{{ $id }}" wire:ignore.self x-init="$el.showModal()" x-on:cancel.prevent="if (!document.querySelector('.swal2-container')) $wire.set('open',false)" class="modal social-dialog" aria-labelledby="social-modal-title">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="social-modal-title" class="modal-title h5">{{ $id?'Edit':'Tambah' }} {{ $accounts?'akun':'publikasi' }}</h2>
                        <button type="button" class="btn-close" aria-label="Tutup form" wire:click="$set('open',false)"></button>
                    </div>
                    <div class="modal-body">
                        <form id="social-catalog-form" wire:submit="save" data-admin-confirm-title="Simpan {{ $id ? 'perubahan' : 'data' }} {{ $accounts ? 'akun' : 'publikasi' }}?" data-admin-confirm-text="{{ $id ? 'Ya, perbarui' : 'Ya, simpan' }}">
                            @error('form')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                            @php($inputs=$accounts?['display_name'=>'Nama akun','platform'=>'Platform','handle'=>'Username','profile_url'=>'Tautan profil','active'=>'Akun aktif']:['title'=>'Judul konten','account_id'=>'Akun media sosial','published_at'=>'Tanggal publikasi (WIB)','content_type'=>'Jenis konten','url'=>'Tautan publikasi','description'=>'Catatan','responsible_label'=>'Penanggung jawab'])
                            @foreach($inputs as $key=>$label)
                                <div class="mb-3">
                                    @if($key==='active')
                                        <div class="form-check">
                                            <input id="social-active" type="checkbox" class="form-check-input" wire:model="form.active">
                                            <label class="form-check-label" for="social-active">{{ $label }}</label>
                                        </div>
                                    @else
                                        <label class="form-label" for="social-{{ $key }}">{{ $label }}</label>
                                        @if(in_array($key,['platform','account_id','content_type']))
                                            <select id="social-{{ $key }}" class="form-select" wire:model="form.{{ $key }}"><option value="">Pilih {{ strtolower($label) }}</option>
                                            @foreach(match($key){'platform'=>Social::PLATFORMS,'content_type'=>Social::TYPES,default=>collect($choices)->mapWithKeys(fn($a)=>[$a->id=>(Social::PLATFORMS[$a->platform] ?? $a->platform).' · '.$a->handle])} as $value=>$text)<option value="{{ $value }}">{{ $text }}</option>@endforeach</select>
                                        @elseif($key==='description')<textarea id="social-{{ $key }}" class="form-control" wire:model="form.description"></textarea>
                                        @else<input id="social-{{ $key }}" class="form-control" type="{{ $key==='published_at'?'datetime-local':(in_array($key,['url','profile_url'])?'url':'text') }}" wire:model="form.{{ $key }}">@endif
                                    @endif
                                    @error('form.'.$key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                            @if($accounts && $visibilityReady)
                                <div class="border-top pt-3 mt-2">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input id="social-show-on-website" type="checkbox" class="form-check-input" wire:model="form.show_on_website">
                                            <label class="form-check-label fw-semibold" for="social-show-on-website">Tampilkan di Website</label>
                                        </div>
                                        <div class="form-text">Akun harus aktif dan memiliki tautan profil valid untuk tampil di website publik.</div>
                                        @error('form.show_on_website')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label" for="social-display-order">Urutan Tampilan</label>
                                        <input id="social-display-order" type="number" min="0" max="9999" class="form-control" wire:model="form.display_order">
                                        <div class="form-text">Angka lebih kecil tampil lebih dahulu (0 sebagai urutan awal).</div>
                                        @error('form.display_order')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            @endif
                            @unless($accounts)
                                <fieldset class="border rounded p-3 mb-2"><legend class="h6 mb-2">Observasi metrik baru</legend><div class="row g-2">
                                @foreach(\App\Livewire\Admin\Social\Catalog::OBS as $key=>$label)<div class="col-6"><label class="form-label small mb-1" for="obs-{{ $key }}">{{ $label }}</label><input id="obs-{{ $key }}" class="form-control form-control-sm" type="number" min="0" wire:model="form.{{ $key }}">@error('form.'.$key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>@endforeach
                                </div></fieldset>
                                @foreach($observations as $o)<div class="small text-muted mt-1">{{ \Carbon\Carbon::parse($o->observed_at,'UTC')->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · @foreach(\App\Livewire\Admin\Social\Catalog::OBS as $key=>$label){{ $label }} {{ $o->$key ?? '—' }} @endforeach</div>@endforeach
                            @endunless
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('open',false)">Batal</button>
                        <button type="submit" form="social-catalog-form" class="btn btn-primary" wire:loading.attr="disabled">Simpan</button>
                    </div>
                </div>
            </div>
        </dialog>
    @endif
</div>
