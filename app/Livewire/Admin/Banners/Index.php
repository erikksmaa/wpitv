<?php

namespace App\Livewire\Admin\Banners;

use App\Models\Banner;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Manajemen Banner - Admin WPiTV')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $sortField = 'created_at';

    #[Url(history: true)]
    public string $sortDirection = 'desc';

    public int $perPage = 10;

    public function updatingSearch(): void
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

    public function delete(int $id): void
    {
        $banner = Banner::findOrFail($id);

        $this->authorize('delete', $banner);

        // Safe physical media cleanup if stored in public storage
        if ($banner->image && Storage::disk('public')->exists('banners/'.$banner->image)) {
            Storage::disk('public')->delete('banners/'.$banner->image);
        }

        $bannerTitle = $banner->title;
        $banner->delete();

        session()->flash('success', "Banner \"{$bannerTitle}\" berhasil dihapus.");
    }

    public function render()
    {
        $this->authorize('viewAny', Banner::class);

        $allowedSortFields = ['created_at', 'title'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'created_at';
        $sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'desc';

        $banners = Banner::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('title', 'like', '%'.$this->search.'%')
                        ->orWhere('content', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy($sortField, $sortDirection)
            ->paginate($this->perPage);

        return view('livewire.admin.banners.index', [
            'banners' => $banners,
        ]);
    }
}
