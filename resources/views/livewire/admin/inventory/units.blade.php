@use('App\Services\Equipment\EquipmentReports', 'Reports')
<section class="p-3 border rounded mt-2" id="unit-list-{{ $row->id }}" aria-labelledby="unit-title-{{ $row->id }}">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h3 id="unit-title-{{ $row->id }}" class="h6 mb-0">Unit fisik · {{ $row->name }}</h3>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <select aria-label="Filter status unit" wire:model.live="unitStatus" class="form-select form-select-sm w-auto">
                <option value="">Semua status unit</option>
                @foreach(Reports::STATUS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
            <select aria-label="Filter kondisi unit" wire:model.live="unitCondition" class="form-select form-select-sm w-auto">
                <option value="">Semua kondisi</option>
                @foreach(Reports::CONDITIONS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
            @can('equipment.stock.add')<button type="button" class="btn btn-sm btn-outline-primary" wire:click="begin('stock.add', {{ $row->id }})"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Tambah stok</button>@endcan
        </div>
    </div>
    @forelse($units as $unit)
        @php
            $actions = [];
            if (!$unit->borrowed && $unit->operational_status !== 'retired') {
                if (in_array($unit->operational_status, ['active','maintenance'], true)) {
                    $actions['unit.condition'] = ['Edit unit', 'pencil'];
                    $actions['unit.lost'] = ['Tandai hilang', 'exclamation-circle'];
                }
                $actions += match ($unit->operational_status) {
                    'active' => ['unit.maintenance_start'=>['Mulai perawatan','tools']],
                    'maintenance' => $unit->physical_condition === 'good' ? ['unit.maintenance_finish'=>['Selesaikan perawatan','check-circle']] : [],
                    'lost' => ['unit.found'=>['Catat ditemukan','arrow-counterclockwise']],
                    default => [],
                };
                $actions['unit.photo_add'] = ['Unggah foto', 'camera'];
                $actions['unit.retire'] = ['Nonaktifkan permanen', 'archive'];
            }
            $available = !$unit->borrowed && $row->is_active && $unit->operational_status === 'active' && $unit->physical_condition === 'good';
            $state = $unit->borrowed ? 'Dipinjam' : ($available ? 'Tersedia' : ($unit->operational_status === 'active' ? 'Tidak tersedia' : Reports::STATUS[$unit->operational_status]));
        @endphp
        <div class="border-top py-2" wire:key="inventory-unit-{{ $unit->id }}">
            <div class="row g-2 align-items-start">
                <div class="col-8 col-sm-4"><strong>{{ $unit->asset_code }}</strong><div class="small text-muted">{{ $unit->serial_number ? 'Serial '.$unit->serial_number : 'Tanpa serial' }}</div></div>
                <div class="col-6 col-sm-3 order-2 order-sm-0">{{ Reports::CONDITIONS[$unit->physical_condition] }}</div>
                <div class="col-6 col-sm-3 order-2 order-sm-0"><span class="badge {{ $available ? 'bg-success' : ($unit->borrowed ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ $state }}</span></div>
                <div class="col-4 col-sm-2 d-flex justify-content-end">@include('livewire.admin.inventory.actions', ['actions'=>$actions,'name'=>$unit->asset_code,'id'=>$unit->id,'kind'=>'unit'])</div>
            </div>
            @can('equipment.photo.view')
                @include('livewire.admin.inventory.unit-photos', ['photos'=>$unitPhotos->get($unit->id, collect())])
            @endcan
        </div>
    @empty
        <p class="small text-muted mb-0">Tidak ada unit yang cocok.</p>
    @endforelse
    {{ $units->links() }}
</section>
