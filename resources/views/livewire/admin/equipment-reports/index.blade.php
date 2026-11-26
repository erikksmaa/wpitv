@use('App\Services\Equipment\EquipmentReports', 'Reports')
<div>
    @php($pages = ['inventory' => 'Laporan Inventaris', 'loans' => 'Laporan Peminjaman dan Pengembalian'])
    <div class="page-heading">
        <nav aria-label="Breadcrumb"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li><li class="breadcrumb-item"><a href="{{ route('admin.inventory.equipment') }}">Inventaris</a></li><li class="breadcrumb-item active" aria-current="page">{{ $pages[$section] }}</li></ol></nav>
        <h1 class="h3 mb-0">{{ $pages[$section] }}</h1>
    </div>
    <nav class="nav nav-pills gap-1 mb-3" aria-label="Laporan Peralatan">
        @foreach($pages as $key => $label)
            <a class="nav-link {{ $section === $key ? 'active' : '' }}" href="{{ route('admin.equipment-reports.index', $key) }}" @if($section === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div wire:loading class="small text-muted mb-2" role="status">Memuat laporan…</div>
    @if(isset($failed))
        <div class="border border-danger rounded p-3 text-danger" role="alert">Data peralatan belum dapat dimuat. <button class="btn btn-sm btn-outline-danger" wire:click="$refresh">Coba lagi</button></div>
    @else
        <section class="card"><div class="card-body pt-3">
            <form x-on:submit.prevent="$wire.apply()" class="row g-2 align-items-end">
                @if($section === 'inventory')
                    <div class="col-md-4"><label class="form-label" for="report-search">Cari kategori, peralatan, kode aset, atau serial</label>
                        <input class="form-control" id="report-search" wire:model="draft.search" maxlength="100">@include('livewire.admin.equipment-field-error', ['field'=>'search'])</div>
                    @php($options = ['category'=>['Kategori',$categories->pluck('name','id')], 'model'=>['Peralatan',$models->mapWithKeys(fn ($m) => [$m->id => $m->name.' · '.$m->model_code])], 'condition'=>['Kondisi',Reports::CONDITIONS], 'status'=>['Status operasional',Reports::STATUS]])
                @else
                    <div class="col-md-3"><label for="report-period" class="form-label">Filter berdasarkan</label><select class="form-select" id="report-period" wire:model="draft.period"><option value="checkout">Tanggal peminjaman</option><option value="return">Tanggal pengembalian</option></select>@include('livewire.admin.equipment-field-error', ['field'=>'period'])</div>
                    @foreach(['from'=>'Dari tanggal','until'=>'Sampai tanggal'] as $key => $label)
                        <div class="col-md-3"><label for="report-{{ $key }}" class="form-label">{{ $label }}</label><input type="date" class="form-control" id="report-{{ $key }}" wire:model="draft.{{ $key }}">@include('livewire.admin.equipment-field-error', ['field'=>$key])</div>
                    @endforeach
                    @php($options = ['borrower'=>['Jenis peminjam',Reports::BORROWERS], 'progress'=>['Status peminjaman',Reports::PROGRESS], 'late'=>['Keterlambatan',['overdue'=>'Masih terlambat','resolved_late'=>'Selesai terlambat','none'=>'Tidak terlambat']]])
                @endif
                @foreach($options as $key => [$label, $choices])
                    <div class="col-sm-6 col-md-3">
                        <label class="form-label" for="report-{{ $key }}">{{ $label }}</label>
                        <select class="form-select" id="report-{{ $key }}" wire:model="draft.{{ $key }}" @if($key === 'category') x-on:change="$wire.$set('draft.model', '', false)" @endif>
                            <option value="">Semua</option>
                            @foreach($choices as $value => $name)
                                <option value="{{ $value }}" @if($key === 'model') x-bind:hidden="!!$wire.draft.category && Number($wire.draft.category) !== {{ (int)$models[$value]->category_id }}" @endif>{{ $name }}</option>
                            @endforeach
                        </select>
                        @include('livewire.admin.equipment-field-error', ['field'=>$key])
                    </div>
                @endforeach
                <div class="col-12 d-flex gap-2"><button class="btn btn-primary" wire:loading.attr="disabled">Terapkan filter</button><button class="btn btn-outline-secondary" type="button" wire:click="clear">Reset filter</button></div>
                <div class="col-12">@include('livewire.admin.equipment-field-error', ['field'=>'action'])</div>
            </form>
        </div></section>
        <dl class="row g-2 mb-3">
            @foreach($section === 'loans' ? Reports::LOAN_COUNTS : Reports::STOCK as $key => $label)
                <div class="col-6 col-md-4 col-xl"><div class="card card-body h-100 mb-0"><dt class="text-muted small">{{ $key === 'returned' && ($filters['period'] ?? '') === 'return' ? 'Unit kembali dalam periode' : $label }}</dt><dd class="h3 mb-0">{{ number_format($counts->$key,0,',','.') }}</dd></div></div>
            @endforeach
        </dl>
        @php($columns = Reports::columns($section, $filters))
        <section class="card"><div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h5 mb-0">{{ $section === 'inventory' ? 'Rekap unit' : 'Riwayat transaksi' }} ({{ $rows->total() }})</h2>
            @can('equipment.report.export')<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.equipment-reports.csv', ['section'=>$section]+$filters) }}"><i class="bi bi-download me-1" aria-hidden="true"></i>Unduh CSV</a>@endcan
        </div><div class="card-body">
            @if($errors->any())<p class="small text-muted">Tabel dan CSV masih memakai filter terakhir yang berhasil diterapkan.</p>@endif
            <div class="table-responsive"><table class="table table-striped align-middle"><thead><tr>
                @foreach($columns as $label)<th scope="col">{{ $label }}</th>@endforeach<th scope="col">Detail</th>
            </tr></thead><tbody>
                @forelse($rows as $row)
                    <tr>@foreach($columns as $key => $label)<td>{{ Reports::display($key,$row->$key) }}</td>@endforeach
                        <td>@if($section === 'inventory')
                            @can('equipment.inventory.view')<a href="{{ route('admin.inventory.equipment',['model'=>$row->model_id,'status'=>$row->operational_status,'condition'=>$row->physical_condition,'search'=>$filters['search'] ?? null]) }}">Lihat unit</a>@endcan
                        @else
                            @can('equipment.loan.view')<a href="{{ route('admin.equipment-loans.show',$row->id) }}">Transaksi dan bukti</a>@endcan
                        @endif</td>
                    </tr>
                @empty<tr><td colspan="{{ count($columns)+1 }}" class="text-center text-muted py-4">{{ $filters ? 'Tidak ada data yang cocok dengan filter.' : ($section === 'loans' ? 'Belum ada peminjaman.' : 'Belum ada unit untuk dilaporkan.') }}</td></tr>@endforelse
            </tbody></table></div>{{ $rows->links() }}
        </div></section>
    @endif
</div>
