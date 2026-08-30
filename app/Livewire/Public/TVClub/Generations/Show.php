<?php

namespace App\Livewire\Public\TVClub\Generations;

use App\Models\TVClubGeneration;
use App\Models\TVClubGroupMember;
use App\Models\TvClubOrganizationPosition;
use App\Support\OrganizationTree;
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
            ->withCount('groups')
            ->orderByDesc('status')
            ->orderBy('name', 'asc')
            ->get();

        $groups = $this->generation->groups()
            ->with('program')
            ->withCount('members')
            ->orderByDesc('status')
            ->get();

        $organizationPositions = TvClubOrganizationPosition::query()
            ->where('generation_id', $this->generation->id)
            ->where('status', true)
            ->with(['assignments' => fn ($query) => $query
                ->whereHas('member', fn ($memberQuery) => $memberQuery->where('status', true))
                ->with('member:id,generation_id,name,photo,status')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $assignmentCount = TVClubGroupMember::query()
            ->whereIn('group_id', $groups->pluck('id'))
            ->count();

        $linkedProgramCount = $groups
            ->whereNotNull('program_id')
            ->pluck('program_id')
            ->unique()
            ->count();

        $previousGeneration = TVClubGeneration::query()
            ->where('year', '<', $this->generation->year)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->first(['id', 'name', 'year']);

        $nextGeneration = TVClubGeneration::query()
            ->where('year', '>', $this->generation->year)
            ->orderBy('year')
            ->orderBy('id')
            ->first(['id', 'name', 'year']);

        return view('livewire.public.tvclub.generations.show', [
            'members' => $members,
            'groups' => $groups,
            'assignmentCount' => $assignmentCount,
            'linkedProgramCount' => $linkedProgramCount,
            'previousGeneration' => $previousGeneration,
            'nextGeneration' => $nextGeneration,
            'organizationTree' => OrganizationTree::build($organizationPositions),
        ])->layout('layouts.public', [
            'title' => $this->generation->name.' | TV Club WPiTV',
            'description' => 'Profil '.$this->generation->name.', kru, kelompok produksi, dan program yang menjadi bagian dari perjalanan TV Club WPiTV.',
        ]);
    }
}
