<?php

namespace App\Livewire\Admin\Organization;

use App\Models\User;
use App\Models\WpitvOrganizationPosition;
use App\Support\OrganizationTree;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Struktur Organisasi WPiTV - Admin WPiTV')]
class Index extends Component
{
    use AuthorizesRequests;

    public bool $showForm = false;

    public ?int $positionId = null;

    public string $name = '';

    public ?int $user_id = null;

    public ?int $parent_id = null;

    public int $sort_order = 0;

    public bool $status = true;

    public function createPosition(): void
    {
        $this->authorize('create', WpitvOrganizationPosition::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function editPosition(int $id): void
    {
        $position = WpitvOrganizationPosition::findOrFail($id);
        $this->authorize('update', $position);

        $this->positionId = $position->id;
        $this->name = $position->name;
        $this->user_id = $position->user_id;
        $this->parent_id = $position->parent_id;
        $this->sort_order = (int) $position->sort_order;
        $this->status = (bool) $position->status;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function savePosition(): void
    {
        $position = $this->positionId
            ? WpitvOrganizationPosition::findOrFail($this->positionId)
            : null;

        $this->authorize($position ? 'update' : 'create', $position ?? WpitvOrganizationPosition::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists('wpitv_organization_positions', 'id')],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'boolean'],
        ], [], [
            'name' => 'Nama Posisi',
            'user_id' => 'Pengisi Posisi',
            'parent_id' => 'Parent / Atasan',
            'sort_order' => 'Urutan',
            'status' => 'Status',
        ]);

        $validated['parent_id'] = $validated['parent_id'] ?: null;
        $validated['user_id'] = $validated['user_id'] ?: null;

        $positions = WpitvOrganizationPosition::orderBy('sort_order')->orderBy('id')->get();
        if ($error = OrganizationTree::parentError($position, $validated['parent_id'], $positions)) {
            $this->addError('parent_id', $error);

            return;
        }

        if ($position) {
            $position->update($validated);
            session()->flash('success', 'Data posisi berhasil diubah.');
        } else {
            WpitvOrganizationPosition::create($validated);
            session()->flash('success', 'Data posisi berhasil ditambahkan.');
        }

        $this->closeForm();
    }

    public function deletePosition(int $id): void
    {
        $position = WpitvOrganizationPosition::withCount('children')->findOrFail($id);
        $this->authorize('delete', $position);

        if ($position->children_count > 0) {
            session()->flash('error', 'Posisi ini masih memiliki posisi di bawahnya. Pindahkan atau hapus posisi tersebut terlebih dahulu.');

            return;
        }

        $position->delete();
        session()->flash('success', 'Data posisi berhasil dihapus.');
    }

    public function render()
    {
        $this->authorize('viewAny', WpitvOrganizationPosition::class);

        $positions = WpitvOrganizationPosition::query()
            ->with('user:id,name,username')
            ->withCount('children')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('livewire.admin.organization.index', [
            'positions' => $positions,
            'tree' => OrganizationTree::build($positions),
            'users' => User::query()->where('status', true)->orderBy('name')->get(['id', 'name', 'username']),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['positionId', 'name', 'user_id', 'parent_id', 'sort_order']);
        $this->status = true;
        $this->resetValidation();
    }
}
