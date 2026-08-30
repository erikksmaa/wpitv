<?php

namespace App\Livewire\Admin\TVClub\Generations;

use App\Models\TVClubGeneration;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Generasi TV Club - Admin WPiTV')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $sortField = 'year';

    #[Url(history: true)]
    public string $sortDirection = 'desc';

    #[Url(history: true)]
    public string $statusFilter = '';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowedFields = ['year', 'name', 'status', 'created_at'];
        if (! in_array($field, $allowedFields, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = in_array($field, ['year', 'created_at'], true) ? 'desc' : 'asc';
        }
    }

    public function delete(int $id): void
    {
        $generation = TVClubGeneration::withCount(['members', 'groups', 'organizationPositions'])->findOrFail($id);

        $this->authorize('delete', $generation);

        // Delete Protection: Block deletion if foreign keys exist
        if ($generation->members_count > 0 || $generation->groups_count > 0 || $generation->organization_positions_count > 0) {
            session()->flash('error', 'Generation cannot be deleted because it is still used by members or production groups.');

            return;
        }

        $generationName = $generation->name;
        $generation->delete();

        session()->flash('success', "Generasi \"{$generationName}\" berhasil dihapus.");
    }

    public function render()
    {
        $this->authorize('viewAny', TVClubGeneration::class);

        $allowedSortFields = ['year', 'name', 'status', 'created_at'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'year';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'desc';

        $generations = TVClubGeneration::query()
            ->withCount(['members', 'groups', 'organizationPositions'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('year', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== '', function ($query) {
                $query->where('status', (int) $this->statusFilter);
            })
            ->orderBy($sortField, $sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.tvclub.generations.index', [
            'generations' => $generations,
        ]);
    }
}
