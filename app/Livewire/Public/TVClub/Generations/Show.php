<?php

namespace App\Livewire\Public\TVClub\Generations;

use App\Models\TVClubGeneration;
use Livewire\Component;

class Show extends Component
{
    public TVClubGeneration $generation;

    public function mount(TVClubGeneration $generation): void
    {
        $this->generation = $generation;
    }

    public function render()
    {
        $this->generation->loadCount(['members', 'groups']);

        $members = $this->generation->members()
            ->with(['groups.program'])
            ->orderByDesc('status')
            ->orderBy('name', 'asc')
            ->get();

        $groups = $this->generation->groups()
            ->with('program')
            ->withCount('members')
            ->orderByDesc('status')
            ->get();

        return view('livewire.public.tvclub.generations.show', [
            'members' => $members,
            'groups' => $groups,
        ])->layout('layouts.public', [
            'title' => $this->generation->name.' | TV Club WPiTV',
        ]);
    }
}
