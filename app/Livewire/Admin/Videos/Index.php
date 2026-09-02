<?php

namespace App\Livewire\Admin\Videos;

use App\Models\Program;
use App\Models\Video;
use App\Support\InputBounds;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Manajemen Tayangan - Admin WPiTV')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $programId = 'all';

    #[Url(history: true)]
    public string $year = 'all';

    #[Url(history: true)]
    public string $sortField = 'air_date';

    #[Url(history: true)]
    public string $sortDirection = 'desc';

    public int $perPage = 10;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingProgramId(): void
    {
        $this->resetPage();
    }

    public function updatingYear(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowedFields = ['air_date', 'title', 'created_at'];
        if (! in_array($field, $allowedFields, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = $field === 'title' ? 'asc' : 'desc';
        }
    }

    public function delete(int $id): void
    {
        $video = Video::findOrFail($id);

        $this->authorize('delete', $video);

        $videoTitle = $video->title;
        $video->delete();

        session()->flash('success', "Video \"{$videoTitle}\" berhasil dihapus.");
    }

    public function render()
    {
        $this->authorize('viewAny', Video::class);
        $this->search = InputBounds::search($this->search);
        $this->perPage = InputBounds::perPage($this->perPage);

        $allowedSortFields = ['air_date', 'title', 'created_at'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'air_date';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'desc';

        $videos = Video::query()
            ->with(['program:id,name,slug', 'user:id,name,username'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('youtube_id', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->programId !== 'all', function ($query) {
                if ($this->programId === 'none') {
                    $query->whereNull('program_id');
                } elseif (is_numeric($this->programId)) {
                    $query->where('program_id', (int) $this->programId);
                }
            })
            ->when($this->year !== 'all' && is_numeric($this->year), function ($query) {
                $query->whereYear('air_date', (int) $this->year);
            })
            ->orderBy($sortField, $sortDirection)
            ->paginate($this->perPage);

        $programs = Program::orderBy('name')->get(['id', 'name']);

        $years = Video::query()
            ->whereNotNull('air_date')
            ->selectRaw('YEAR(air_date) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        return view('livewire.admin.videos.index', [
            'videos' => $videos,
            'programs' => $programs,
            'years' => $years,
        ]);
    }
}
