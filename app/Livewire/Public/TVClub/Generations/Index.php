<?php

namespace App\Livewire\Public\TVClub\Generations;

use App\Models\TVClubGeneration;
use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        $generations = TVClubGeneration::query()
            ->withCount(['members', 'groups'])
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->get();

        return view('livewire.public.tvclub.generations.index', [
            'generations' => $generations,
            'activeGenerations' => $generations->where('status', true)->values(),
            'historicalGenerations' => $generations->where('status', false)->values(),
        ])->layout('layouts.public', [
            'title' => 'Generasi TV Club | WPiTV',
            'description' => 'Perjalanan generasi mahasiswa TV Club yang menjadi bagian dari produksi WPiTV.',
        ]);
    }
}
