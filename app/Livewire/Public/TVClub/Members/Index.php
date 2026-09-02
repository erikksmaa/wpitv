<?php

namespace App\Livewire\Public\TVClub\Members;

use App\Models\TVClubGeneration;
use App\Models\TVClubMember;
use App\Support\InputBounds;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $generationId = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'generationId' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingGenerationId(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'generationId']);
        $this->resetPage();
    }

    public function render()
    {
        $this->search = InputBounds::search($this->search);
        $generations = TVClubGeneration::query()
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->get(['id', 'name', 'year']);

        $members = TVClubMember::query()
            ->with('generation')
            ->withCount('groups')
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);
                $query->where('name', 'like', "%{$term}%");
            })
            ->when($this->generationId !== '' && is_numeric($this->generationId), function ($query) {
                $query->where('generation_id', (int) $this->generationId);
            })
            ->orderByDesc('status')
            ->orderBy('name')
            ->paginate(12);

        return view('livewire.public.tvclub.members.index', [
            'generations' => $generations,
            'members' => $members,
        ])->layout('layouts.public', [
            'title' => 'Kru TV Club | WPiTV',
            'description' => 'Direktori mahasiswa dan kru produksi TV Club WPiTV.',
        ]);
    }
}
