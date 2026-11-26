<?php

namespace App\Livewire\Public\News;

use App\Models\News;
use App\Support\InputBounds;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $year = '';

    public string $sort = 'latest';

    protected $queryString = [
        'search' => ['except' => ''],
        'year' => ['except' => ''],
        'sort' => ['except' => 'latest'],
    ];

    public function updatingSearch(): void
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
        $this->reset(['search', 'year', 'sort']);
        $this->resetPage();
    }

    public function render()
    {
        $this->search = InputBounds::search($this->search);
        $allowedSorts = ['latest', 'oldest', 'title_asc', 'title_desc'];
        $currentSort = in_array($this->sort, $allowedSorts, true) ? $this->sort : 'latest';

        $news = News::query()
            ->with('user:id,name')
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);
                $query->where(function ($sub) use ($term) {
                    $sub->where('title', 'like', "%{$term}%")
                        ->orWhere('author_name', 'like', "%{$term}%");
                });
            })
            ->when($this->year !== '' && is_numeric($this->year), function ($query) {
                $query->whereYear('created_at', (int) $this->year);
            })
            ->when($currentSort === 'oldest', fn ($q) => $q->orderBy('created_at', 'asc'))
            ->when($currentSort === 'title_asc', fn ($q) => $q->orderBy('title', 'asc'))
            ->when($currentSort === 'title_desc', fn ($q) => $q->orderBy('title', 'desc'))
            ->when($currentSort === 'latest', fn ($q) => $q->latest('created_at'))
            ->paginate(12);

        $yearsList = News::selectRaw('DISTINCT YEAR(created_at) as yr')
            ->orderByDesc('yr')
            ->pluck('yr')
            ->filter();

        return view('livewire.public.news.index', [
            'newsList' => $news,
            'yearsList' => $yearsList,
        ])->layout('layouts.public', [
            'title' => 'Berita | WPiTV — Televisi Digital Kampus',
            'description' => 'Berita kampus, informasi kegiatan, dan artikel jurnalistik WPiTV Institut Widya Pratama.',
        ]);
    }
}
