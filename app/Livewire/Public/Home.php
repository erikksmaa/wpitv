<?php

namespace App\Livewire\Public;

use Livewire\Component;

class Home extends Component
{
    public function render()
    {
        return view('livewire.public.home')
            ->layout('layouts.public', ['title' => 'WPiTV - Digital Media Platform STMIK Widya Pratama']);
    }
}
