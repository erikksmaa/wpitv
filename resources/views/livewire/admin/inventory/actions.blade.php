@php($allowed = array_filter($actions, fn ($label, $op) => $op === 'gallery' ? auth()->user()->can('equipment.photo.view') : auth()->user()->can(\App\Support\EquipmentPermissions::OPERATIONS[$op]), ARRAY_FILTER_USE_BOTH))
@if($allowed)
    <details class="inventory-actions" x-data x-on:click.outside="$el.open = false" x-on:keydown.escape="$el.open = false; $el.querySelector('summary').focus()">
        <summary class="btn btn-sm btn-outline-secondary" aria-label="Tindakan {{ $name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></summary>
        <div class="dropdown-menu dropdown-menu-end show shadow-sm mt-1">
            @foreach($allowed as $op => [$label, $icon])
                <button type="button" class="dropdown-item" x-on:click="$el.closest('details').open = false"
                    @if($op === 'gallery') wire:click="inspect('{{ $kind }}', {{ $id }})" @else wire:click="begin('{{ $op }}', {{ $id }})" @endif>
                    <i class="bi bi-{{ $icon }} me-2" aria-hidden="true"></i>{{ $label }}
                </button>
            @endforeach
        </div>
    </details>
@endif
