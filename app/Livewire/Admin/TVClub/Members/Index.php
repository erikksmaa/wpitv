<?php

namespace App\Livewire\Admin\TVClub\Members;

use App\Models\TVClubGeneration;
use App\Models\TVClubMember;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Anggota TV Club - Admin WPiTV')]
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
        $allowedFields = ['name', 'student_number', 'generation', 'status', 'created_at'];
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
        $member = TVClubMember::withCount('groups')->findOrFail($id);

        $this->authorize('delete', $member);

        // Delete Protection: Block deletion if assigned to any group
        if ($member->groups_count > 0) {
            session()->flash('error', 'Member cannot be deleted because this member is still assigned to one or more production groups.');

            return;
        }

        // Cleanup local photo file if present
        if ($member->photo && Storage::disk('public')->exists('tvclub/members/'.$member->photo)) {
            Storage::disk('public')->delete('tvclub/members/'.$member->photo);
        }

        $memberName = $member->name;
        $member->delete();

        session()->flash('success', "Anggota \"{$memberName}\" berhasil dihapus.");
    }

    public function render()
    {
        $this->authorize('viewAny', TVClubMember::class);

        $allowedSortFields = ['name', 'student_number', 'generation', 'status', 'created_at'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'name';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'asc';

        // Summary Aggregations
        $stats = [
            'total' => TVClubMember::count(),
            'active' => TVClubMember::where('status', 1)->count(),
            'inactive' => TVClubMember::where('status', 0)->count(),
            'generations_count' => TVClubMember::distinct('generation_id')->count('generation_id'),
        ];

        // Generation list for filter
        $generations = TVClubGeneration::orderBy('year', 'desc')->get();

        // Main Query
        $members = TVClubMember::query()
            ->with('generation')
            ->withCount('groups')
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('student_number', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->generationFilter !== '', function ($query) {
                $query->where('generation_id', (int) $this->generationFilter);
            })
            ->when($this->statusFilter !== '', function ($query) {
                $query->where('status', (int) $this->statusFilter);
            })
            ->when($sortField === 'generation', function ($query) use ($sortDirection) {
                $query->join('tvclub_generations', 'tvclub_members.generation_id', '=', 'tvclub_generations.id')
                    ->orderBy('tvclub_generations.year', $sortDirection)
                    ->select('tvclub_members.*');
            }, function ($query) use ($sortField, $sortDirection) {
                $query->orderBy($sortField, $sortDirection);
            })
            ->paginate($this->perPage);

        return view('livewire.admin.tvclub.members.index', [
            'members' => $members,
            'stats' => $stats,
            'generations' => $generations,
        ]);
    }
}
