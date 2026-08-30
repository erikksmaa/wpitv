<?php

namespace App\Livewire\Public\TVClub\Members;

use App\Models\TVClubGeneration;
use App\Models\TVClubMember;
use Livewire\Component;

class Show extends Component
{
    public TVClubMember $member;

    public function mount(TVClubMember $member): void
    {
        $this->member = $member;
    }

    public function render()
    {
        $this->member->load([
            'generation' => fn ($query) => $query->withCount(['members', 'groups']),
            'groups' => fn ($query) => $query
                ->withCount('members')
                ->with(['generation', 'program'])
                ->orderByDesc(
                    TVClubGeneration::query()
                        ->select('year')
                        ->whereColumn('tvclub_generations.id', 'tvclub_groups.generation_id')
                        ->limit(1)
                )
                ->orderBy('name'),
        ]);

        $programs = $this->member->groups
            ->pluck('program')
            ->filter()
            ->unique('id')
            ->values();

        $relatedCrew = TVClubMember::query()
            ->when(
                $this->member->generation_id,
                fn ($query, $generationId) => $query->where('generation_id', $generationId),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->whereKeyNot($this->member->id)
            ->withCount('groups')
            ->orderBy('name')
            ->limit(4)
            ->get();

        return view('livewire.public.tvclub.members.show', [
            'programs' => $programs,
            'relatedCrew' => $relatedCrew,
        ])->layout('layouts.public', [
            'title' => $this->member->name.' | TV Club WPiTV',
            'description' => 'Profil anggota TV Club WPiTV: '.$this->member->name.($this->member->generation ? ' — '.$this->member->generation->name : ''),
        ]);
    }
}
