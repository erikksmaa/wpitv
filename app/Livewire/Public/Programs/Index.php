<?php

namespace App\Livewire\Public\Programs;

use App\Models\Program;
use App\Support\InputBounds;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sort = 'latest';

    protected $queryString = [
        'search' => ['except' => ''],
        'sort' => ['except' => 'latest'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->search = InputBounds::search($this->search);
        $allowedSorts = ['latest', 'name_asc', 'name_desc', 'most_videos'];
        $currentSort = in_array($this->sort, $allowedSorts, true) ? $this->sort : 'latest';

        $programs = Program::query()
            ->where('status', true)
            ->withCount('videos')
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);
                $query->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->when($currentSort === 'name_asc', fn ($q) => $q->orderBy('name', 'asc'))
            ->when($currentSort === 'name_desc', fn ($q) => $q->orderBy('name', 'desc'))
            ->when($currentSort === 'most_videos', fn ($q) => $q->orderByDesc('videos_count')->orderBy('name', 'asc'))
            ->when($currentSort === 'latest', fn ($q) => $q->latest('created_at'))
            ->paginate(12);

        return view('livewire.public.programs.index', [
            'programs' => $programs,
        ])->layout('layouts.public', [
            'title' => 'Program Acara | WPiTV — Televisi Digital Kampus',
            'description' => 'Jelajahi berbagai program acara dan katalog tayangan produksi WPiTV Institut Widya Pratama.',
        ]);
    }
}
