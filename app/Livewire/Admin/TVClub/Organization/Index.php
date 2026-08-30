<?php

namespace App\Livewire\Admin\TVClub\Organization;

use App\Models\TVClubGeneration;
use App\Models\TVClubMember;
use App\Models\TvClubOrganizationMember;
use App\Models\TvClubOrganizationPosition;
use App\Support\OrganizationTree;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Index extends Component
{
    use AuthorizesRequests;

    public TVClubGeneration $generation;

    public bool $showPositionForm = false;

    public ?int $positionId = null;

    public string $name = '';

    public ?int $parent_id = null;

    public int $sort_order = 0;

    public bool $status = true;

    public bool $showMemberForm = false;

    public ?int $memberPositionId = null;

    public ?int $member_id = null;

    public int $member_sort_order = 0;

    public function mount(TVClubGeneration $generation): void
    {
        $this->authorize('viewAny', TvClubOrganizationPosition::class);
        $this->generation = $generation;
    }

    public function createPosition(): void
    {
        $this->authorize('create', TvClubOrganizationPosition::class);
        $this->resetPositionForm();
        $this->showPositionForm = true;
    }

    public function editPosition(int $id): void
    {
        $position = $this->generation->organizationPositions()->findOrFail($id);
        $this->authorize('update', $position);

        $this->positionId = $position->id;
        $this->name = $position->name;
        $this->parent_id = $position->parent_id;
        $this->sort_order = (int) $position->sort_order;
        $this->status = (bool) $position->status;
        $this->resetValidation();
        $this->showPositionForm = true;
    }

    public function closePositionForm(): void
    {
        $this->showPositionForm = false;
        $this->resetPositionForm();
    }

    public function savePosition(): void
    {
        $position = $this->positionId
            ? $this->generation->organizationPositions()->findOrFail($this->positionId)
            : null;

        $this->authorize($position ? 'update' : 'create', $position ?? TvClubOrganizationPosition::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', Rule::exists('tvclub_organization_positions', 'id')],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'boolean'],
        ], [], [
            'name' => 'Nama Posisi',
            'parent_id' => 'Parent / Atasan',
            'sort_order' => 'Urutan',
            'status' => 'Status',
        ]);

        $validated['parent_id'] = $validated['parent_id'] ?: null;
        $positions = $this->generation->organizationPositions()->get();
        if ($error = OrganizationTree::parentError($position, $validated['parent_id'], $positions, $this->generation->id)) {
            $this->addError('parent_id', $error);

            return;
        }

        $validated['generation_id'] = $this->generation->id;

        if ($position) {
            $position->update($validated);
            session()->flash('success', 'Data posisi berhasil diubah.');
        } else {
            TvClubOrganizationPosition::create($validated);
            session()->flash('success', 'Data posisi berhasil ditambahkan.');
        }

        $this->closePositionForm();
    }

    public function deletePosition(int $id): void
    {
        $position = $this->generation->organizationPositions()->withCount('children')->findOrFail($id);
        $this->authorize('delete', $position);

        if ($position->children_count > 0) {
            session()->flash('error', 'Posisi ini masih memiliki posisi di bawahnya. Pindahkan atau hapus posisi tersebut terlebih dahulu.');

            return;
        }

        $position->delete();
        session()->flash('success', 'Data posisi berhasil dihapus.');
    }

    public function openMemberForm(int $positionId): void
    {
        $position = $this->generation->organizationPositions()->findOrFail($positionId);
        $this->authorize('assignMember', $position);

        $this->resetMemberForm();
        $this->memberPositionId = $position->id;
        $this->member_sort_order = (int) ($position->assignments()->max('sort_order') ?? -1) + 1;
        $this->showMemberForm = true;
    }

    public function closeMemberForm(): void
    {
        $this->showMemberForm = false;
        $this->resetMemberForm();
    }

    public function addMember(): void
    {
        $position = $this->generation->organizationPositions()->findOrFail($this->memberPositionId);
        $this->authorize('assignMember', $position);

        $validated = $this->validate([
            'member_id' => ['required', 'integer', Rule::exists('tvclub_members', 'id')],
            'member_sort_order' => ['required', 'integer', 'min:0'],
        ], [], [
            'member_id' => 'Anggota',
            'member_sort_order' => 'Urutan Anggota',
        ]);

        $member = TVClubMember::findOrFail($validated['member_id']);
        if ((int) $member->generation_id !== (int) $this->generation->id) {
            $this->addError('member_id', 'Anggota harus berasal dari generasi yang sama.');

            return;
        }

        if ($position->assignments()->where('member_id', $member->id)->exists()) {
            $this->addError('member_id', 'Anggota ini sudah ditempatkan pada posisi tersebut.');

            return;
        }

        TvClubOrganizationMember::create([
            'position_id' => $position->id,
            'member_id' => $member->id,
            'sort_order' => $validated['member_sort_order'],
        ]);

        session()->flash('success', 'Anggota berhasil ditambahkan ke posisi.');
        $this->closeMemberForm();
    }

    public function removeMember(int $positionId, int $assignmentId): void
    {
        $position = $this->generation->organizationPositions()->findOrFail($positionId);
        $this->authorize('assignMember', $position);
        $assignment = $position->assignments()->findOrFail($assignmentId);
        $assignment->delete();
        session()->flash('success', 'Anggota berhasil dihapus dari posisi.');
    }

    public function render()
    {
        $positions = $this->generation->organizationPositions()
            ->with(['assignments.member:id,generation_id,name,student_number,photo,status'])
            ->withCount('children')
            ->get();

        $assignedIds = $this->memberPositionId
            ? TvClubOrganizationMember::where('position_id', $this->memberPositionId)->pluck('member_id')
            : collect();

        $invalidParentIds = $this->invalidParentIds($positions, $this->positionId);

        return view('livewire.admin.tvclub.organization.index', [
            'positions' => $positions,
            'tree' => OrganizationTree::build($positions),
            'invalidParentIds' => $invalidParentIds,
            'availableMembers' => $this->generation->members()
                ->whereNotIn('id', $assignedIds)
                ->orderByDesc('status')
                ->orderBy('name')
                ->get(['id', 'generation_id', 'name', 'student_number', 'photo', 'status']),
        ])->title('Struktur Organisasi '.$this->generation->name.' - Admin WPiTV');
    }

    private function resetPositionForm(): void
    {
        $this->reset(['positionId', 'name', 'parent_id', 'sort_order']);
        $this->status = true;
        $this->resetValidation();
    }

    private function resetMemberForm(): void
    {
        $this->reset(['memberPositionId', 'member_id', 'member_sort_order']);
        $this->resetValidation();
    }

    private function invalidParentIds($positions, ?int $positionId): array
    {
        if (! $positionId) {
            return [];
        }

        $childrenByParent = $positions->groupBy('parent_id');
        $invalid = [$positionId => true];
        $queue = [$positionId];

        while ($queue) {
            $parentId = array_shift($queue);
            foreach ($childrenByParent->get($parentId, collect()) as $child) {
                if (isset($invalid[$child->id])) {
                    continue;
                }

                $invalid[$child->id] = true;
                $queue[] = $child->id;
            }
        }

        return array_map('intval', array_keys($invalid));
    }
}
