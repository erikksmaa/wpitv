<?php

namespace App\Livewire\Admin\TVClub\Groups;

use App\Models\Program;
use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Support\InputBounds;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Kelompok TV Club - Admin WPiTV')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $generationFilter = '';

    #[Url(history: true)]
    public string $programFilter = '';

    #[Url(history: true)]
    public string $statusFilter = '';

    #[Url(history: true)]
    public string $sortField = 'name';

    #[Url(history: true)]
    public string $sortDirection = 'asc';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingGenerationFilter(): void
    {
        $this->resetPage();
    }

    public function updatingProgramFilter(): void
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
        $allowedFields = ['name', 'generation', 'program', 'status', 'created_at'];
        if (! in_array($field, $allowedFields, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = in_array($field, ['created_at'], true) ? 'desc' : 'asc';
        }
    }

    public function delete(int $id): void
    {
        $group = TVClubGroup::withCount('members')->findOrFail($id);

        $this->authorize('delete', $group);

        // Delete Protection: Block deletion if members are assigned
        if ($group->members_count > 0) {
            session()->flash('error', 'Kelompok ini masih memiliki anggota terdaftar. Hapus seluruh penugasan anggota sebelum menghapus kelompok.');

            return;
        }

        $groupName = $group->name;
        $group->delete();

        session()->flash('success', "Kelompok produksi \"{$groupName}\" berhasil dihapus.");
    }

    public function render()
    {
        $this->authorize('viewAny', TVClubGroup::class);
        $this->search = InputBounds::search($this->search);
        $this->perPage = InputBounds::perPage($this->perPage);

        $allowedSortFields = ['name', 'generation', 'program', 'status', 'created_at'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'name';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'asc';

        // Summary Aggregations
        $stats = [
            'total' => TVClubGroup::count(),
            'active' => TVClubGroup::where('status', 1)->count(),
            'inactive' => TVClubGroup::where('status', 0)->count(),
            'with_members' => TVClubGroup::has('members')->count(),
        ];

        // Dropdown data for filters
        $generations = TVClubGeneration::orderBy('year', 'desc')->get();
        $programs = Program::orderBy('name', 'asc')->get();

        // Main Query
        $groups = TVClubGroup::query()
            ->with(['generation', 'program'])
            ->withCount('members')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('tvclub_groups.name', 'like', '%'.$this->search.'%')
                        ->orWhere('tvclub_groups.description', 'like', '%'.$this->search.'%')
                        ->orWhereHas('program', function ($pq) {
                            $pq->where('name', 'like', '%'.$this->search.'%');
                        })
                        ->orWhereHas('generation', function ($gq) {
                            $gq->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('year', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->generationFilter !== '', function ($query) {
                $query->where('generation_id', (int) $this->generationFilter);
            })
            ->when($this->programFilter !== '', function ($query) {
                $query->where('program_id', (int) $this->programFilter);
            })
            ->when($this->statusFilter !== '', function ($query) {
                $query->where('status', (int) $this->statusFilter);
            })
            ->when($sortField === 'generation', function ($query) use ($sortDirection) {
                $query->join('tvclub_generations', 'tvclub_groups.generation_id', '=', 'tvclub_generations.id')
                    ->orderBy('tvclub_generations.year', $sortDirection)
                    ->select('tvclub_groups.*');
            })
            ->when($sortField === 'program', function ($query) use ($sortDirection) {
                $query->join('ms_program', 'tvclub_groups.program_id', '=', 'ms_program.id')
                    ->orderBy('ms_program.name', $sortDirection)
                    ->select('tvclub_groups.*');
            })
            ->when(! in_array($sortField, ['generation', 'program'], true), function ($query) use ($sortField, $sortDirection) {
                $query->orderBy('tvclub_groups.'.$sortField, $sortDirection);
            })
            ->paginate($this->perPage);

        return view('livewire.admin.tvclub.groups.index', [
            'groups' => $groups,
            'stats' => $stats,
            'generations' => $generations,
            'programs' => $programs,
        ]);
    }
}
