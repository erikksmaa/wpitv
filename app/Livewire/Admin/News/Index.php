<?php

namespace App\Livewire\Admin\News;

use App\Models\News;
use App\Services\OptimizedImageStorage;
use App\Support\InputBounds;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Manajemen Berita - Admin WPiTV')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $year = 'all';

    #[Url(history: true)]
    public string $sortField = 'created_at';

    #[Url(history: true)]
    public string $sortDirection = 'desc';

    public int $perPage = 10;

    public function updatingSearch(): void
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
        $allowedFields = ['created_at', 'title'];
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

    public function delete(int $id, OptimizedImageStorage $imageStorage): void
    {
        $news = News::findOrFail($id);

        $this->authorize('delete', $news);

        $oldFilename = $news->image;
        $newsTitle = $news->title;
        $news->delete();
        $imageStorage->deleteOwned(OptimizedImageStorage::NEWS, $oldFilename, 'News', $news->id);

        session()->flash('success', "Artikel berita \"{$newsTitle}\" berhasil dihapus.");
    }

    public function render()
    {
        $this->authorize('viewAny', News::class);
        $this->search = InputBounds::search($this->search);
        $this->perPage = InputBounds::perPage($this->perPage);

        $allowedSortFields = ['created_at', 'title'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'created_at';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'desc';

        $newsList = News::query()
            ->with(['user:id,name,username'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('slug', 'like', '%'.$this->search.'%')
                        ->orWhere('author_name', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->year !== 'all' && is_numeric($this->year), function ($query) {
                $query->whereYear('created_at', (int) $this->year);
            })
            ->orderBy($sortField, $sortDirection)
            ->paginate($this->perPage);

        $years = News::query()
            ->whereNotNull('created_at')
            ->selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        return view('livewire.admin.news.index', [
            'newsList' => $newsList,
            'years' => $years,
        ]);
    }
}
