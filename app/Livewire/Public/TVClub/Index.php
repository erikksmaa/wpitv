<?php

namespace App\Livewire\Public\TVClub;

use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use App\Models\TVClubGroupMember;
use App\Models\TVClubMember;
use Livewire\Component;

class Index extends Component
{
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
            ->orderByDesc('id')
            ->get();

        $crewPreview = TVClubMember::query()
            ->where('status', true)
            ->whereHas('generation', fn ($query) => $query->where('status', true))
            ->with('generation:id,name,year')
            ->withCount('groups')
            ->orderByDesc(
                TVClubGeneration::select('year')
                    ->whereColumn('tvclub_generations.id', 'tvclub_members.generation_id')
                    ->limit(1)
            )
            ->orderBy('name')
            ->limit(6)
            ->get();

        return view('livewire.public.tvclub.index', [
            'stats' => $stats,
            'activeGenerations' => $activeGenerations,
            'crewPreview' => $crewPreview,
        ])->layout('layouts.public', [
            'title' => 'TV Club | Orang-orang di Balik WPiTV',
            'description' => 'Kenali generasi, kru, dan kelompok produksi mahasiswa yang berkarya di balik program dan tayangan WPiTV.',
        ]);
    }
}
