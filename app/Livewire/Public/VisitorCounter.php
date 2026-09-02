<?php

namespace App\Livewire\Public;

use App\Services\VisitorStatisticsService;
use Livewire\Component;

class VisitorCounter extends Component
{
    public function render(VisitorStatisticsService $statistics)
    {
        return view('livewire.public.visitor-counter', [
            'metrics' => $statistics->publicMetrics(),
        ]);
    }
}
