@use('App\Services\Equipment\EquipmentReports', 'Reports')
@use('App\Services\Equipment\LoanRules')
<div>
    @php($pages = ['dashboard' => 'Dashboard Equipment', 'inventory' => 'Laporan Inventaris', 'loans' => 'Laporan Peminjaman dan Pengembalian'])
    <div class="page-heading"><h1 class="h3">{{ $pages[$section] }}</h1></div>
    <nav class="nav nav-pills gap-1 mb-3" aria-label="Laporan Equipment">
        @foreach($pages as $key => $label)
            <a class="nav-link {{ $section === $key ? 'active' : '' }}" href="{{ route('admin.equipment-reports.index', $key) }}" @if($section === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div wire:loading class="small text-muted mb-2" role="status">Memuat laporan…</div>
    @if(isset($failed))
        <div class="alert alert-danger" role="alert">Data peralatan belum dapat dimuat. <button class="btn btn-sm btn-outline-danger" wire:click="$refresh">Coba lagi</button></div>
    @else
        @if($section !== 'dashboard')
            <section class="card"><div class="card-body pt-3">
                <form wire:submit="apply" class="row g-2 align-items-end">
                    @if($section === 'inventory')
                        <div class="col-md-4"><label class="form-label" for="report-search">Cari kategori, model, kode aset, atau serial</label>
                            <input class="form-control" id="report-search" wire:model="draft.search" maxlength="100"></div>
                        @php($options = ['category'=>['Kategori',$categories->pluck('name','id')], 'model'=>['Model',$models->mapWithKeys(fn ($m) => [$m->id => $m->name.' · '.$m->model_code])], 'condition'=>['Kondisi',Reports::CONDITIONS], 'status'=>['Status operasional',Reports::STATUS]])
                    @else
                        <p class="small text-muted mb-0">Pilih tanggal checkout atau penerimaan retur. Pada periode retur, KPI unit kembali menghitung unit yang diterima dalam periode. KPI lainnya mencakup seluruh transaksi terpilih. Transaksi batal menyumbang nol unit dipinjam.</p>
                        <div class="col-md-3"><label for="report-period" class="form-label">Dasar periode</label><select class="form-select" id="report-period" wire:model="draft.period"><option value="checkout">Tanggal checkout</option><option value="return">Tanggal retur</option></select></div>
                        @foreach(['from'=>'Mulai','until'=>'Sampai'] as $key => $label)
                            <div class="col-md-3"><label for="report-{{ $key }}" class="form-label">{{ $label }} (WIB)</label><input type="date" class="form-control" id="report-{{ $key }}" wire:model="draft.{{ $key }}"></div>
                        @endforeach
                        @php($options = ['borrower'=>['Jenis peminjam',Reports::BORROWERS], 'progress'=>['Status transaksi',Reports::PROGRESS], 'late'=>['Keterlambatan',['overdue'=>'Masih terlambat','resolved_late'=>'Ada unit selesai terlambat','none'=>'Tanpa keterlambatan tercatat']]])
                    @endif
                    @foreach($options as $key => [$label, $choices])
                        <div class="col-sm-6 col-md-3">
                            <label class="form-label" for="report-{{ $key }}">{{ $label }}</label>
                            <select class="form-select" id="report-{{ $key }}" wire:model="draft.{{ $key }}">
                                <option value="">Semua</option>
                                @foreach($choices as $value => $name)<option value="{{ $value }}">{{ $name }}</option>@endforeach
                            </select>
                        </div>
                    @endforeach
                    <div class="col-12 d-flex gap-2"><button class="btn btn-primary" wire:loading.attr="disabled">Terapkan filter</button><button class="btn btn-outline-secondary" type="button" wire:click="clear">Reset filter</button></div>
                    @if($errors->any())<ul class="text-danger ms-3" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
                </form>
            </div></section>
        @endif
        <dl class="row g-2 mb-3">
            @foreach($section === 'loans' ? Reports::LOAN_COUNTS : Reports::STOCK as $key => $label)
                <div class="col-6 col-md-4 col-xl"><div class="card card-body h-100 mb-0"><dt class="text-muted small">{{ $label }}</dt><dd class="h3 mb-0">{{ number_format($counts->$key,0,',','.') }}</dd></div></div>
            @endforeach
        </dl>
        @if($section === 'dashboard')
            <div class="d-flex flex-wrap gap-2 mb-3">
                @can('equipment.inventory.view')<a class="btn btn-outline-primary" href="{{ route('admin.inventory.index') }}">Buka Inventory</a>@endcan
                @can('equipment.loan.checkout')<a class="btn btn-primary" href="{{ route('admin.equipment-loans.create') }}">Buat peminjaman</a>@endcan
                @can('equipment.loan.return')<a class="btn btn-outline-primary" href="{{ route('admin.equipment-loans.index',['status'=>'open']) }}">Proses pengembalian</a>@endcan
            </div>
            <div class="row">
                @foreach(['Kondisi unit'=>$conditions,'Distribusi kategori'=>$categories] as $title=>$items)
                    <div class="col-md-6"><section class="card"><div class="card-header"><h2 class="h5">{{ $title }}</h2></div><div class="card-body">
                        @forelse($items as $item)<div class="d-flex justify-content-between border-bottom py-1"><span>{{ $title === 'Kondisi unit' ? Reports::CONDITIONS[$item->label] : $item->label }}</span><strong>{{ $item->total }}</strong></div>
                        @empty<p class="text-muted">Belum ada unit.</p>@endforelse
                        @if($title === 'Distribusi kategori'){{ $categories->links() }}@endif
                    </div></section></div>
                @endforeach
            </div>
            <section class="card"><div class="card-header"><h2 class="h5">Terlambat atau jatuh tempo dalam 3 hari</h2></div><div class="card-body">
                @forelse($due as $loan)
                    <div class="d-flex flex-wrap justify-content-between border-bottom py-2 gap-2">
                        @can('equipment.loan.view')<a href="{{ route('admin.equipment-loans.show',$loan->id) }}">{{ $loan->loan_number }}</a>@else<span>{{ $loan->loan_number }}</span>@endcan
                        <span>{{ $loan->open_units }} unit · {{ $loan->due_date }} WIB</span>
                        <span class="badge {{ $loan->overdue ? 'bg-danger' : 'bg-warning text-dark' }}">{{ $loan->overdue ? 'Terlambat' : 'Mendekati jatuh tempo' }}</span>
                    </div>
                @empty<p class="text-muted">Tidak ada peminjaman yang perlu ditindaklanjuti.</p>@endforelse
                {{ $due->links() }}
            </div></section>
            @can('equipment.audit.view')
                <section class="card"><div class="card-header"><h2 class="h5">Aktivitas terbaru</h2></div><div class="card-body">
                    @forelse($activity as $event)
                        <div class="d-flex flex-wrap justify-content-between gap-2 border-bottom py-2"><span>{{ $event->event_name }} · {{ $event->subject_type }} #{{ $event->subject_id }}</span><time>{{ LoanRules::wib($event->occurred_at) }}</time></div>
                    @empty<p class="text-muted">Belum ada aktivitas Equipment.</p>@endforelse
                </div></section>
            @endcan
        @else
            @php($columns = Reports::columns($section, $filters))
            <section class="card"><div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h2 class="h5 mb-0">{{ $section === 'inventory' ? 'Rekap unit' : 'Riwayat transaksi' }} ({{ $rows->total() }})</h2>
                @can('equipment.report.export')<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.equipment-reports.csv', ['section'=>$section]+$filters) }}">Ekspor CSV</a>@endcan
            </div><div class="card-body">
                <div class="table-responsive"><table class="table table-striped align-middle"><thead><tr>
                    @foreach($columns as $label)<th scope="col">{{ $label }}</th>@endforeach<th scope="col">Detail</th>
                </tr></thead><tbody>
                    @forelse($rows as $row)
                        <tr>@foreach($columns as $key => $label)<td>{{ Reports::display($key,$row->$key) }}</td>@endforeach
                            <td>@if($section === 'inventory')
                                @can('equipment.inventory.view')<a href="{{ route('admin.inventory.index',['tab'=>'units','model'=>$row->model_id,'status'=>$row->operational_status,'condition'=>$row->physical_condition,'search'=>$filters['search'] ?? null]) }}">Lihat unit</a>@endcan
                            @else
                                @can('equipment.loan.view')<a href="{{ route('admin.equipment-loans.show',$row->id) }}">Transaksi dan bukti</a>@endcan
                            @endif</td>
                        </tr>
                    @empty<tr><td colspan="{{ count($columns)+1 }}" class="text-center text-muted py-4">Tidak ada data yang cocok dengan filter.</td></tr>@endforelse
                </tbody></table></div>{{ $rows->links() }}
            </div></section>
        @endif
    @endif
</div>
