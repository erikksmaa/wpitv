<?php

namespace App\Livewire\Admin\TVClub\Groups;

use App\Models\Program;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubMember;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?TVClubGroup $group = null;

    public ?int $groupId = null;

    // Group Information
    public ?int $generation_id = null;

    public ?int $program_id = null;

    public string $name = '';

    public ?string $description = '';

    public bool $status = true;

    // Crew Member Assignment List
    // Format: [ ['member_id' => 1, 'name' => '...', 'student_number' => '...', 'role' => '...', 'photo_url' => '...', 'initials' => '...'], ... ]
    public array $assignedMembers = [];

    // Add Member Selection State
    public ?int $selectedMemberId = null;

    public string $selectedRole = 'Kameramen';

    public string $customRole = '';

    // Standard TV Production Roles Presets
    public array $rolePresets = [
        'Produser',
        'Floor Director',
        'Host / Presenter',
        'Kameramen',
        'Editor',
        'Script Writer',
        'Audio Operator',
        'Lighting Operator',
        'Technical Director',
        'Creative & Design',
    ];

    public function mount(?TVClubGroup $group = null): void
    {
        if ($group && $group->exists) {
            $this->authorize('update', $group);

            $this->group = $group;
            $this->groupId = $group->id;
            $this->generation_id = (int) $group->generation_id;
            $this->program_id = (int) $group->program_id;
            $this->name = $group->name;
            $this->description = $group->description ?? '';
            $this->status = (bool) $group->status;

            // Load assigned members with pivot role
            $this->assignedMembers = $group->members()->get()->map(function ($member) {
                return [
                    'member_id' => $member->id,
                    'name' => $member->name,
                    'student_number' => $member->student_number,
                    'role' => $member->pivot->role ?? 'Crew Member',
                    'photo_url' => $member->photo_url,
                    'initials' => $member->initials,
                ];
            })->toArray();
        } else {
            $this->authorize('create', TVClubGroup::class);

            $latestGen = TVClubGeneration::where('status', 1)->orderBy('year', 'desc')->first()
                ?? TVClubGeneration::orderBy('year', 'desc')->first();
            $this->generation_id = $latestGen ? $latestGen->id : null;

            $firstProgram = Program::where('status', 1)->first() ?? Program::first();
            $this->program_id = $firstProgram ? $firstProgram->id : null;

            $this->status = true;
            $this->assignedMembers = [];
        }
    }

    public function updatedGenerationId(): void
    {
        // If generation changes, filter out any members that do not belong to the new generation
        if (! empty($this->assignedMembers)) {
            $validMemberIds = TVClubMember::where('generation_id', $this->generation_id)
                ->whereIn('id', array_column($this->assignedMembers, 'member_id'))
                ->pluck('id')
                ->toArray();

            $this->assignedMembers = array_values(array_filter($this->assignedMembers, function ($item) use ($validMemberIds) {
                return in_array($item['member_id'], $validMemberIds, true);
            }));
        }

        $this->selectedMemberId = null;
    }

    public function addMember(): void
    {
        $this->authorize('assignMember', $this->group ?? TVClubGroup::class);

        if (empty($this->selectedMemberId)) {
            session()->flash('assignment_error', 'Silakan pilih anggota terlebih dahulu.');

            return;
        }

        // Prevent duplicate assignment in current list
        $existingIndex = array_search($this->selectedMemberId, array_column($this->assignedMembers, 'member_id'), true);
        if ($existingIndex !== false) {
            session()->flash('assignment_error', 'Anggota ini sudah terdaftar dalam penugasan kelompok.');

            return;
        }

        $member = TVClubMember::find($this->selectedMemberId);
        if (! $member) {
            session()->flash('assignment_error', 'Data anggota tidak ditemukan.');

            return;
        }

        // Generation consistency validation
        if ((int) $member->generation_id !== (int) $this->generation_id) {
            session()->flash('assignment_error', 'Anggota harus berasal dari generasi yang sama dengan kelompok produksi ini.');

            return;
        }

        $finalRole = trim($this->customRole) !== '' ? trim($this->customRole) : $this->selectedRole;

        $this->assignedMembers[] = [
            'member_id' => $member->id,
            'name' => $member->name,
            'student_number' => $member->student_number,
            'role' => $finalRole ?: 'Anggota Kru',
            'photo_url' => $member->photo_url,
            'initials' => $member->initials,
        ];

        // Reset inputs
        $this->selectedMemberId = null;
        $this->customRole = '';
        $this->selectedRole = 'Kameramen';
    }

    public function removeMember(int $memberId): void
    {
        $this->authorize('assignMember', $this->group ?? TVClubGroup::class);

        $this->assignedMembers = array_values(array_filter($this->assignedMembers, function ($item) use ($memberId) {
            return (int) $item['member_id'] !== $memberId;
        }));
    }

    public function updateMemberRole(int $memberId, string $newRole): void
    {
        $this->authorize('assignMember', $this->group ?? TVClubGroup::class);

        foreach ($this->assignedMembers as &$assigned) {
            if ((int) $assigned['member_id'] === $memberId) {
                $assigned['role'] = trim($newRole) ?: 'Anggota Kru';
                break;
            }
        }
    }

    protected function rules(): array
    {
        return [
            'generation_id' => ['required', 'integer', 'exists:tvclub_generations,id'],
            'program_id' => ['required', 'integer', 'exists:ms_program,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'generation_id' => 'Generasi Angkatan',
            'program_id' => 'Program Siaran',
            'name' => 'Nama Kelompok',
            'description' => 'Deskripsi',
            'status' => 'Status Aktif',
        ];
    }

    public function save()
    {
        $isEdit = (bool) $this->groupId;

        if ($isEdit) {
            $this->authorize('update', $this->group);
        } else {
            $this->authorize('create', TVClubGroup::class);
        }

        $validated = $this->validate();

        // Extra verification: Ensure all assigned members belong to this generation
        foreach ($this->assignedMembers as $item) {
            $member = TVClubMember::find($item['member_id']);
            if (! $member || (int) $member->generation_id !== (int) $this->generation_id) {
                $this->addError('generation_id', 'Anggota harus berasal dari generasi yang sama dengan kelompok produksi ini.');

                return null;
            }
        }

        // Database Transaction for group and pivot sync
        DB::transaction(function () use ($validated) {
            if ($this->groupId) {
                $this->group->update($validated);
                $targetGroup = $this->group;
            } else {
                $targetGroup = TVClubGroup::create($validated);
                $this->group = $targetGroup;
                $this->groupId = $targetGroup->id;
            }

            // Sync pivot table tvclub_group_members
            $syncData = [];
            foreach ($this->assignedMembers as $assigned) {
                $syncData[$assigned['member_id']] = [
                    'role' => mb_substr($assigned['role'] ?? 'Crew Member', 0, 50),
                ];
            }

            $targetGroup->members()->sync($syncData);
        });

        session()->flash('success', $isEdit
            ? "Kelompok produksi \"{$this->name}\" berhasil diperbarui."
            : "Kelompok produksi \"{$this->name}\" berhasil ditambahkan.");

        return redirect()->route('admin.tvclub.groups.index');
    }

    public function render()
    {
        $this->authorize($this->group?->exists ? 'update' : 'create', $this->group?->exists ? $this->group : TVClubGroup::class);

        $isEdit = (bool) $this->groupId;
        $generations = TVClubGeneration::orderBy('year', 'desc')->get();
        $programs = Program::orderBy('name', 'asc')->get();

        // Available members for assignment from currently selected generation, excluding already assigned
        $alreadyAssignedIds = array_column($this->assignedMembers, 'member_id');
        $availableMembers = TVClubMember::query()
            ->when($this->generation_id, function ($query) {
                $query->where('generation_id', $this->generation_id);
            })
            ->whereNotIn('id', $alreadyAssignedIds)
            ->orderBy('name', 'asc')
            ->get();

        return view('livewire.admin.tvclub.groups.form', [
            'isEdit' => $isEdit,
            'generations' => $generations,
            'programs' => $programs,
            'availableMembers' => $availableMembers,
        ])->title(($isEdit ? 'Edit Kelompok: '.$this->name : 'Tambah Kelompok TV Club').' - WPiTV Admin');
    }
}
