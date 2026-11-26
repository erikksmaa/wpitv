<?php

namespace App\Livewire\Public\Videos;

use App\Models\Program;
use App\Models\Video;
use App\Support\InputBounds;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $program = '';

    public string $year = '';

    public string $sort = 'latest';

    protected $queryString = [
        'search' => ['except' => ''],
        'program' => ['except' => ''],
        'year' => ['except' => ''],
        'sort' => ['except' => 'latest'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingProgram(): void
    {
        $this->resetPage();
    }

    public function updatingYear(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'program', 'year', 'sort']);
        $this->resetPage();
    }

    public function render()
    {
        $this->search = InputBounds::search($this->search);
        $allowedSorts = ['latest', 'oldest', 'title_asc', 'title_desc', 'newest_created'];
        $currentSort = in_array($this->sort, $allowedSorts, true) ? $this->sort : 'latest';

        $videos = Video::query()
            ->with('program:id,name,slug')
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);
                $query->where(function ($sub) use ($term) {
                    $sub->where('title', 'like', "%{$term}%")
                        ->orWhere('youtube_id', 'like', "%{$term}%");
                });
            })
            ->when($this->program !== '', function ($query) {
                if ($this->program === 'without_program') {
                    $query->whereNull('program_id');
                } else {
                    $query->where('program_id', $this->program);
                }
            })
            ->when($this->year !== '' && is_numeric($this->year), function ($query) {
                $query->whereYear('air_date', (int) $this->year);
            })
            ->when($currentSort === 'oldest', fn ($q) => $q->orderBy('air_date', 'asc')->orderBy('created_at', 'asc'))
            ->when($currentSort === 'title_asc', fn ($q) => $q->orderBy('title', 'asc'))
            ->when($currentSort === 'title_desc', fn ($q) => $q->orderBy('title', 'desc'))
            ->when($currentSort === 'newest_created', fn ($q) => $q->latest('created_at'))
            ->when($currentSort === 'latest', fn ($q) => $q->orderByDesc('air_date')->orderByDesc('created_at'))
            ->paginate(12);

        $programsList = Program::where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $yearsList = Video::whereNotNull('air_date')
            ->selectRaw('DISTINCT YEAR(air_date) as yr')
            ->orderByDesc('yr')
            ->pluck('yr')
            ->filter();

        return view('livewire.public.videos.index', [
            'videos' => $videos,
            'programsList' => $programsList,
            'yearsList' => $yearsList,
        ])->layout('layouts.public', [
            'title' => 'Tayangan | WPiTV — Televisi Digital Kampus',
            'description' => 'Katalog tayangan video, liputan kampus, dan karya produksi WPiTV Institut Widya Pratama.',
        ]);
    }
}
