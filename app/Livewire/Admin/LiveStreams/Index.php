<?php

namespace App\Livewire\Admin\LiveStreams;

use App\Livewire\Concerns\InteractsWithLiveStreams;
use App\Models\LiveStream;
use App\Models\Program;
use App\Support\InputBounds;
use App\Support\LiveStreamFilters;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Index extends Component
{
    use InteractsWithLiveStreams, WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $visibility = 'all';

    #[Url]
    public ?int $programId = null;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $sort = 'schedule';

    #[Url]
    public string $direction = 'asc';

    public int $perPage = 10;

    public function mount(): void
    {
        $this->authorize('viewAny', LiveStream::class);
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status', 'visibility', 'programId', 'from', 'to', 'sort', 'direction', 'perPage'], true)) {
            $this->resetPage();
            $this->resetErrorBag();
        }
    }

    public function resetFilters(): void
    {
        $this->authorize('viewAny', LiveStream::class);
        $this->reset(['search', 'status', 'visibility', 'programId', 'from', 'to', 'sort', 'direction', 'perPage']);
        $this->resetPage();
        $this->resetErrorBag();
    }

    public function render()
    {
        $this->authorize('viewAny', LiveStream::class);
        $data = $this->readLive(function () {
            try {
                $filter = LiveStreamFilters::normalize([
                    'search' => $this->search, 'status' => $this->status, 'visibility' => $this->visibility,
                    'program' => $this->programId, 'from' => $this->from, 'to' => $this->to,
                    'sort' => $this->sort, 'direction' => $this->direction, 'per_page' => $this->perPage,
                ]);
            } catch (ValidationException $error) {
                foreach ($error->errors() as $field => $messages) {
                    $this->addError($field, $messages[0]);
                }

                return ['streams' => new LengthAwarePaginator([], 0, InputBounds::perPage($this->perPage)), 'programs' => Program::orderBy('name')->get(['id', 'name'])];
            }
            $query = LiveStream::query()->select('live_streams.*')->leftJoin('wpitv_agendas as schedule', 'schedule.id', '=', 'live_streams.agenda_id')
                ->with(['agenda.program', 'manualProgram', 'archiveVideo'])
                ->when($filter['search'] !== '', fn ($q) => $q->where(fn ($where) => $where->whereRaw(LiveStream::TITLE_SQL.' LIKE ?', ['%'.$filter['search'].'%'])->orWhere('live_streams.youtube_id', 'like', '%'.$filter['search'].'%')))
                ->when($filter['status'], fn ($q, $status) => $q->where('live_streams.status', $status))
                ->when($filter['visibility'], fn ($q, $visibility) => $q->whereRaw(LiveStream::VISIBILITY_SQL.' = ?', [$visibility]))
                ->when($filter['program'], fn ($q, $program) => $q->whereRaw(LiveStream::PROGRAM_SQL.' = ?', [$program]))
                ->when($filter['from'], fn ($q, $date) => $q->whereRaw('DATE('.LiveStream::START_WIB.') >= ?', [$date]))
                ->when($filter['to'], fn ($q, $date) => $q->whereRaw('DATE('.LiveStream::START_WIB.') <= ?', [$date]))
                ->orderByRaw($filter['sort'].' '.$filter['direction']);

            return ['streams' => $query->orderBy('live_streams.id')->paginate($filter['per_page']), 'programs' => Program::orderBy('name')->get(['id', 'name'])];
        }, ['streams' => null, 'programs' => collect()]);

        return view('livewire.admin.live-streams.index', [...$data, 'loadError' => $this->liveReadError])->title('Live Streaming');
    }
}
