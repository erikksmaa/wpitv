@props(['node', 'tvClub' => false, 'canManage' => false])

@php($position = $node['position'])
<li class="org-admin-tree__branch" wire:key="org-tree-{{ $tvClub ? 'tv' : 'wpi' }}-{{ $position->id }}">
    <article class="org-admin-node {{ $position->status ? '' : 'is-inactive' }}">
        <span class="org-admin-node__accent" aria-hidden="true"></span>
        <div class="org-admin-node__heading">
            <div>
                <small>Posisi</small>
                <strong>{{ $position->name }}</strong>
            </div>
            @unless($position->status)<span class="badge bg-light-warning text-warning">Nonaktif</span>@endunless
        </div>

        @if($tvClub)
            <div class="org-admin-node__people">
                @forelse($position->assignments as $assignment)
                    <span title="{{ $assignment->member->student_number }}">
                        <b>{{ $assignment->member->initials }}</b>
                        {{ $assignment->member->name }}
                    </span>
                @empty
                    <em>Belum ada anggota</em>
                @endforelse
            </div>
        @else
            <div class="org-admin-node__people">
                @if($position->user)
                    <span><b>{{ $position->user->initials }}</b>{{ $position->user->name }}</span>
                @else
                    <em>Belum ditetapkan</em>
                @endif
            </div>
        @endif

        @if($canManage)
            <div class="org-admin-node__actions">
                <button type="button" class="btn btn-sm btn-light-primary" wire:click="editPosition({{ $position->id }})" aria-label="Edit posisi {{ $position->name }}"><i class="bi bi-pencil"></i></button>
                @if($tvClub)
                    <button type="button" class="btn btn-sm btn-light-success" wire:click="openMemberForm({{ $position->id }})" aria-label="Tambah anggota ke {{ $position->name }}"><i class="bi bi-person-plus"></i></button>
                @endif
                <button type="button" class="btn btn-sm btn-light-danger" wire:click="deletePosition({{ $position->id }})" data-admin-confirm="Apakah Anda yakin ingin menghapus posisi ini?" aria-label="Hapus posisi {{ $position->name }}"><i class="bi bi-trash"></i></button>
            </div>
        @endif
    </article>

    @if($node['children'])
        <ul class="org-admin-tree__children">
            @foreach($node['children'] as $child)
                <x-admin.organization-tree-node :node="$child" :tv-club="$tvClub" :can-manage="$canManage" />
            @endforeach
        </ul>
    @endif
</li>
