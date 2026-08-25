<?php

namespace App\Livewire\Public\TVClub\Members;

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
        $this->member->load(['generation', 'groups.program']);

        return view('livewire.public.tvclub.members.show')->layout('layouts.public', [
            'title' => $this->member->name.' | TV Club WPiTV',
            'description' => 'Profil anggota TV Club WPiTV: '.$this->member->name.($this->member->generation ? ' — '.$this->member->generation->name : ''),
        ]);
    }
}
