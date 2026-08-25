<?php

namespace App\Livewire\Public\Peliputan;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('livewire.public.peliputan.index')->layout('layouts.public', [
            'title' => 'Peliputan | WPiTV — Televisi Digital Kampus',
            'description' => 'Layanan pengajuan peliputan kegiatan kampus Institut Widya Pratama dan kegiatan media oleh WPiTV.',
        ]);
    }
}
