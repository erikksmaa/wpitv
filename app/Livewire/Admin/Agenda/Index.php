<?php

namespace App\Livewire\Admin\Agenda;

use App\Livewire\Concerns\HandlesLiveStreamErrors;
use App\Models\WpitvAgenda;
use App\Services\LiveStreamLifecycle;
use App\Support\AgendaLiveGuard;
use App\Support\InputBounds;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Agenda WPiTV - Admin')]
class Index extends Component
{
    use AuthorizesRequests, HandlesLiveStreamErrors, WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url]
    public string $viewMode = 'calendar';

    #[Url]
    public string $month = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $visibilityFilter = '';

    public int $perPage = 10;

    #[Locked]
    public ?WpitvAgenda $selectedAgenda = null;

    public bool $showDetailModal = false;

    #[Locked]
    public ?int $expectedLiveVersion = null;

    #[Locked]
    public ?string $selectedLiveStatus = null;

    public string $cancellationReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', WpitvAgenda::class);
        $this->month = InputBounds::month($this->month);
    }

    public function setView(string $view): void
    {
        if (in_array($view, ['calendar', 'list'], true)) {
            $this->viewMode = $view;
        }
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

    public function updating($name): void
    {
        if (in_array($name, ['search', 'typeFilter', 'statusFilter', 'visibilityFilter', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'statusFilter', 'visibilityFilter']);
        $this->resetPage();
    }

    public function showAgenda(int $id): void
    {
        $agenda = WpitvAgenda::with(['program', 'coverage', 'group.generation', 'creator'])->findOrFail($id);
        $this->authorize('view', $agenda);
        $stream = app(AgendaLiveGuard::class)->stream($agenda);
        if ($stream !== null) {
            $this->authorize('view', $stream);
        }
        $this->resetErrorBag();
        $this->expectedLiveVersion = $stream?->version;
        $this->selectedLiveStatus = $stream?->status;
        $this->cancellationReason = '';
        $this->selectedAgenda = $agenda;
        $this->showDetailModal = true;
    }

    public function closeDetail(): void
    {
        $this->showDetailModal = false;
        $this->selectedAgenda = null;
        $this->expectedLiveVersion = null;
        $this->selectedLiveStatus = null;
        $this->cancellationReason = '';
    }

    public function cancel(int $id): void
    {
        $agenda = WpitvAgenda::findOrFail($id);
        $this->authorize('update', $agenda);
        app(LiveStreamLifecycle::class)->cancelAgenda(auth()->user(), $id,
            $this->selectedAgenda?->id === $id ? $this->expectedLiveVersion : null,
            trim($this->cancellationReason) ?: null);
        session()->flash('success', 'Agenda berhasil dibatalkan.');
        $this->closeDetail();
    }

    public function delete(int $id): void
    {
        $agenda = WpitvAgenda::findOrFail($id);
        $this->authorize('delete', $agenda);
        app(LiveStreamLifecycle::class)->deleteAgenda(auth()->user(), $id);
        session()->flash('success', 'Data agenda berhasil dihapus.');
        $this->closeDetail();
    }

    private function filteredQuery()
    {
        $this->search = InputBounds::search($this->search);

        return WpitvAgenda::query()->with(['program:id,name', 'coverage:id,submission_code,event_name', 'group:id,name'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($s) => $s->where('title', 'like', '%'.$this->search.'%')->orWhere('location', 'like', '%'.$this->search.'%')->orWhereHas('program', fn ($p) => $p->where('name', 'like', '%'.$this->search.'%'))))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->visibilityFilter !== '', fn ($q) => $q->where('visibility', $this->visibilityFilter));
    }

    public function render()
    {
        $this->authorize('viewAny', WpitvAgenda::class);
        if ($this->selectedAgenda !== null) {
            $this->authorize('view', $this->selectedAgenda);
            if ($stream = app(AgendaLiveGuard::class)->stream($this->selectedAgenda)) {
                $this->authorize('view', $stream);
            }
        }
        $this->month = InputBounds::month($this->month);
        $this->perPage = InputBounds::perPage($this->perPage);

        $monthStart = CarbonImmutable::createFromFormat('Y-m', $this->month)->startOfMonth();
        $gridStart = $monthStart->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $monthStart->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $calendarAgendas = $this->filteredQuery()->betweenDates($gridStart->toDateString(), $gridEnd->toDateString())
            ->orderBy('agenda_date')->orderByRaw('start_time IS NULL')->orderBy('start_time')->get()->groupBy(fn ($agenda) => $agenda->agenda_date->format('Y-m-d'));
        $days = collect(range(0, (int) $gridStart->diffInDays($gridEnd)))->map(fn ($day) => $gridStart->addDays($day));
        $agendas = $this->filteredQuery()->orderByDesc('agenda_date')->orderByDesc('start_time')->paginate($this->perPage);

        return view('livewire.admin.agenda.index', compact('monthStart', 'days', 'calendarAgendas', 'agendas'));
    }
}
