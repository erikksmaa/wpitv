@use('App\Services\Equipment\EquipmentReports', 'Reports')
<section aria-labelledby="equipment-summary-title" class="mb-3" x-data="{ showBreakdown: false }">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <h2 class="h6 text-muted text-uppercase mb-0" id="equipment-summary-title">Ringkasan peralatan</h2>
        <div class="d-flex flex-wrap gap-2">
            @can('equipment.loan.checkout')<a class="btn btn-sm btn-primary" href="{{ route('admin.equipment-loans.create') }}"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Buat peminjaman</a>@endcan
            @can('equipment.loan.return')<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.equipment-loans.index',['status'=>'open']) }}"><i class="bi bi-arrow-return-left me-1" aria-hidden="true"></i>Proses pengembalian</a>@endcan
            <button type="button" class="btn btn-sm btn-outline-secondary" x-on:click="showBreakdown = !showBreakdown" x-bind:aria-expanded="showBreakdown.toString()">
                <i class="bi bi-pie-chart me-1" aria-hidden="true"></i><span x-text="showBreakdown ? 'Tutup rincian' : 'Rincian kondisi & kategori'"></span>
            </button>
        </div>
    </div>
    <dl class="row g-2 mb-2">
        @foreach(Reports::STOCK as $key => $label)
            @php($accent = match($key) {
                'available' => 'text-success',
                'borrowed' => 'text-warning',
                'maintenance' => 'text-info',
                'lost' => 'text-danger',
                default => '',
            })
            <div class="col-6 col-md-4 col-xl"><div class="card card-body py-2 px-3 h-100 mb-0"><dt class="text-muted small">{{ $label }}</dt><dd class="h4 mb-0 {{ $accent }}">{{ number_format($counts->$key,0,',','.') }}</dd></div></div>
        @endforeach
    </dl>
    <div class="row g-3 mt-1" x-show="showBreakdown" x-cloak>
        @foreach(['Kondisi unit'=>$conditions,'Distribusi kategori'=>$categories] as $title=>$items)
            <div class="col-md-6"><section class="card mb-0 h-100"><div class="card-header py-2"><h3 class="h6 mb-0">{{ $title }}</h3></div><div class="card-body py-2">
                @forelse($items as $item)<div class="d-flex justify-content-between border-bottom py-1 small"><span>{{ $title === 'Kondisi unit' ? (Reports::CONDITIONS[$item->label] ?? $item->label) : $item->label }}</span><strong>{{ number_format($item->total,0,',','.') }}</strong></div>
                @empty<p class="text-muted small mb-0">Belum ada unit.</p>@endforelse
                @if($title === 'Distribusi kategori')<div class="mt-2">{{ $categories->links() }}</div>@endif
            </div></section></div>
        @endforeach
    </div>
</section>
