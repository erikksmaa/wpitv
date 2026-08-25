<?php

namespace App\Livewire\Admin\Programs;

use App\Models\Program;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Manajemen Program - Admin WPiTV')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = 'all';

    #[Url(history: true)]
    public string $sortField = 'created_at';

    #[Url(history: true)]
    public string $sortDirection = 'desc';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowedFields = ['name', 'created_at', 'videos_count'];
        if (! in_array($field, $allowedFields)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function delete(int $id): void
    {
        $program = Program::withCount(['videos', 'tvClubGroups'])->findOrFail($id);

        $this->authorize('delete', $program);

        // Delete Safety Check: Block deletion if dependencies exist
        if ($program->videos_count > 0 || $program->tv_club_groups_count > 0) {
            $details = [];
            if ($program->videos_count > 0) {
                $details[] = "{$program->videos_count} video tayangan";
            }
            if ($program->tv_club_groups_count > 0) {
                $details[] = "{$program->tv_club_groups_count} grup TV Club";
            }

            session()->flash('error', "Program \"{$program->name}\" tidak dapat dihapus karena masih memiliki data terkait (".implode(' dan ', $details).'). Silakan ubah status menjadi Nonaktif sebagai gantinya.');

            return;
        }

        // Safe physical media cleanup if stored in public storage
        if ($program->image && Storage::disk('public')->exists('programs/'.$program->image)) {
            Storage::disk('public')->delete('programs/'.$program->image);
        }

        $programName = $program->name;
        $program->delete();

        session()->flash('success', "Program \"{$programName}\" berhasil dihapus.");
    }

    public function render()
    {
        $this->authorize('viewAny', Program::class);

        $programs = Program::query()
            ->withCount(['videos', 'tvClubGroups'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('slug', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->status !== 'all', function ($query) {
                $query->where('status', (bool) $this->status);
            })
            ->when(in_array($this->sortField, ['name', 'created_at']), function ($query) {
                $query->orderBy($this->sortField, $this->sortDirection);
            })
            ->when($this->sortField === 'videos_count', function ($query) {
                $query->orderBy('videos_count', $this->sortDirection);
            })
            ->paginate($this->perPage);

        return view('livewire.admin.programs.index', [
            'programs' => $programs,
        ]);
    }
}
