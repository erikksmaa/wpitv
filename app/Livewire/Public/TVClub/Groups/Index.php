<?php

namespace App\Livewire\Public\TVClub\Groups;

use App\Models\TVClubGeneration;
use App\Models\TVClubGroup;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $groups = TVClubGroup::query()
            ->with([
                'generation:id,name,year,status',
                'program:id,name,slug,image,status',
                'members' => fn ($query) => $query
                    ->select('tvclub_members.id', 'name', 'photo', 'status')
                    ->orderBy('name'),
            ])
            ->withCount('members')
            ->orderByDesc('status')
            ->orderByDesc(
                TVClubGeneration::query()
                    ->select('year')
                    ->whereColumn('tvclub_generations.id', 'tvclub_groups.generation_id')
                    ->limit(1)
            )
            ->orderBy('name')
            ->get();

        return view('livewire.public.tvclub.groups.index', [
            'groups' => $groups,
        ])->layout('layouts.public', [
            'title' => 'Kelompok Produksi | TV Club WPiTV',
            'description' => 'Kelompok mahasiswa yang bekerja di balik program dan tayangan WPiTV.',
        ]);
    }
}
