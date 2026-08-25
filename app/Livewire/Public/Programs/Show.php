<?php

namespace App\Livewire\Public\Programs;

use App\Models\Program;
use App\Models\TVClubGeneration;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use WithPagination;

    public Program $program;

    public function mount(Program $program): void
    {
        if (! $program->status) {
            abort(404);
        }

        $this->program = $program;
    }

    public function render()
    {
        $videos = $this->program->videos()
            ->orderByDesc('air_date')
            ->orderByDesc('created_at')
            ->paginate(12);

        $otherPrograms = Program::where('status', true)
            ->where('id', '!=', $this->program->id)
            ->withCount('videos')
            ->latest('created_at')
            ->take(4)
            ->get();

        $productionGroups = $this->program->tvClubGroups()
            ->whereHas('members')
            ->with([
                'generation',
                'members' => fn ($query) => $query
                    ->select('tvclub_members.id', 'generation_id', 'name', 'photo', 'status')
                    ->with('generation'),
            ])
            ->orderByDesc('status')
            ->orderByDesc(
                TVClubGeneration::query()
                    ->select('year')
                    ->whereColumn('tvclub_generations.id', 'tvclub_groups.generation_id')
                    ->limit(1)
            )
            ->orderByDesc('id')
            ->get();

        return view('livewire.public.programs.show', [
            'videos' => $videos,
            'otherPrograms' => $otherPrograms,
            'productionGroups' => $productionGroups,
            'defaultProductionGroup' => $productionGroups->first(),
        ])->layout('layouts.public', [
            'title' => "{$this->program->name} | WPiTV — Televisi Digital Kampus",
        ]);
    }
}
