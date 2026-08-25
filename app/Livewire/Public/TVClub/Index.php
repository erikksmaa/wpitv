<?php

namespace App\Livewire\Public\TVClub;

use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubGroupMember;
use App\Models\TVClubMember;
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
        $stats = [
            'generations' => TVClubGeneration::count(),
            'members' => TVClubMember::count(),
            'groups' => TVClubGroup::count(),
            'assignments' => TVClubGroupMember::count(),
        ];

        $activeGenerations = TVClubGeneration::where('status', true)
            ->withCount(['members', 'groups'])
            ->orderByDesc('year')
            ->get();

        $allGenerations = TVClubGeneration::withCount(['members', 'groups'])
            ->orderByDesc('year')
            ->get();

        $members = TVClubMember::query()
            ->with(['generation', 'groups.program'])
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);
                $query->where('name', 'like', "%{$term}%");
            })
            ->when($this->generationId !== '' && is_numeric($this->generationId), function ($query) {
                $query->where('generation_id', (int) $this->generationId);
            })
            ->orderByDesc('status')
            ->orderBy('name', 'asc')
            ->paginate(12);

        $productionGroups = TVClubGroup::with(['generation', 'program'])
            ->withCount('members')
            ->orderByDesc('status')
            ->get();

        return view('livewire.public.tvclub.index', [
            'stats' => $stats,
            'activeGenerations' => $activeGenerations,
            'allGenerations' => $allGenerations,
            'members' => $members,
            'productionGroups' => $productionGroups,
        ])->layout('layouts.public', [
            'title' => 'TV Club | WPiTV — Televisi Digital Kampus',
        ]);
    }
}
