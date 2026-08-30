<?php

namespace App\Livewire\Public\Agenda;

use App\Models\WpitvAgenda;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url]
    public string $month = '';

    public function mount(): void
    {
        $this->month = preg_match('/^\d{4}-\d{2}$/', $this->month) ? $this->month : now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = CarbonImmutable::createFromFormat('Y-m', $this->month)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = CarbonImmutable::createFromFormat('Y-m', $this->month)->addMonth()->format('Y-m');
    }

    public function currentMonth(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function render()
    {
        $monthStart = CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth();
        $gridStart = $monthStart->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $monthStart->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $calendarAgendas = WpitvAgenda::publicVisible()->betweenDates($gridStart->toDateString(), $gridEnd->toDateString())
            ->with(['program' => fn ($query) => $query->where('status', true)->select('id', 'name', 'slug', 'status')])
            ->orderBy('agenda_date')->orderByRaw('start_time IS NULL')->orderBy('start_time')->orderBy('id')->get()
            ->groupBy(fn ($agenda) => $agenda->agenda_date->format('Y-m-d'));
        $days = collect(range(0, (int) $gridStart->diffInDays($gridEnd)))->map(fn ($day) => $gridStart->addDays($day));
        $upcoming = WpitvAgenda::publicVisible()->upcoming()
            ->with(['program' => fn ($query) => $query->where('status', true)->select('id', 'name', 'slug', 'status')])
            ->orderBy('agenda_date')->orderByRaw('start_time IS NULL')->orderBy('start_time')->orderBy('id')->take(30)->get();

        return view('livewire.public.agenda.index', compact('monthStart', 'days', 'calendarAgendas', 'upcoming'))
            ->layout('layouts.public', ['title' => 'Agenda WPiTV — Jadwal Kegiatan Publik']);
    }
}
