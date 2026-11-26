<?php

namespace App\Livewire\Public\Agenda;

use App\Models\WpitvAgenda;
use App\Support\InputBounds;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;
use Livewire\Component;

class Index extends Component
{
    #[Url]
    public string $month = '';

    public function mount(): void
    {
        $this->month = InputBounds::month($this->month);
    }

    public function previousMonth(): void
    {
        $this->month = InputBounds::month($this->month);
        $this->month = CarbonImmutable::createFromFormat('Y-m', $this->month)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = InputBounds::month($this->month);
        $this->month = CarbonImmutable::createFromFormat('Y-m', $this->month)->addMonth()->format('Y-m');
    }

    public function currentMonth(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function render()
    {
        $this->month = InputBounds::month($this->month);
        $monthStart = CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth();
        $gridStart = $monthStart->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $monthStart->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $calendarAgendas = WpitvAgenda::publicVisible()->betweenDates($gridStart->toDateString(), $gridEnd->toDateString())
            ->with(['liveStream' => fn ($query) => $query->agendaPublicVisible()->select('id', 'agenda_id'), 'program' => fn ($query) => $query->where('status', true)->select('id', 'name', 'slug', 'status')])
            ->orderBy('agenda_date')->orderByRaw('start_time IS NULL')->orderBy('start_time')->orderBy('id')->get()
            ->groupBy(fn ($agenda) => $agenda->agenda_date->format('Y-m-d'));
        $days = collect(range(0, (int) $gridStart->diffInDays($gridEnd)))->map(fn ($day) => $gridStart->addDays($day));
        $upcoming = WpitvAgenda::publicVisible()->upcoming()
            ->with(['liveStream' => fn ($query) => $query->agendaPublicVisible()->select('id', 'agenda_id'), 'program' => fn ($query) => $query->where('status', true)->select('id', 'name', 'slug', 'status')])
            ->orderBy('agenda_date')->orderByRaw('start_time IS NULL')->orderBy('start_time')->orderBy('id')->take(30)->get();

        return view('livewire.public.agenda.index', compact('monthStart', 'days', 'calendarAgendas', 'upcoming'))
            ->layout('layouts.public', [
                'title' => 'Agenda | WPiTV — Televisi Digital Kampus',
                'description' => 'Kalender kegiatan publik, produksi, peliputan, dan jadwal siaran WPiTV Institut Widya Pratama dalam zona waktu WIB.',
            ]);
    }
}
