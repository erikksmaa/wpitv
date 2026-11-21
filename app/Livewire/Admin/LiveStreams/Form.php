<?php

namespace App\Livewire\Admin\LiveStreams;

use App\Exceptions\LiveStreamOperationException;
use App\Livewire\Concerns\InteractsWithLiveStreams;
use App\Models\LiveStream;
use App\Models\Program;
use App\Models\WpitvAgenda;
use App\Services\LiveStreamLifecycle;
use App\Support\InputBounds;
use App\Support\YouTubeVideoId;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.admin')]
class Form extends Component
{
    use InteractsWithLiveStreams;

    #[Locked]
    public ?int $streamId = null;

    #[Locked]
    public ?int $expectedVersion = null;

    public ?int $agendaId = null;

    public string $mode = 'manual';

    public string $title = '';

    public ?int $program_id = null;

    public string $agenda_date = '';

    public string $start_time = '';

    public string $end_time = '';

    public string $visibility = 'internal';

    public string $agendaSearch = '';

    public string $youtubeInput = '';

    public function mount(?int $streamId = null): void
    {
        $this->authorize('viewAny', LiveStream::class);
        $this->streamId = $streamId;
        if ($streamId === null) {
            $this->authorize('create', LiveStream::class);
        } else {
            $this->readLive(function () use ($streamId) {
                $stream = $this->authorizedStream($streamId, 'update');
                $this->expectedVersion = $stream->version;
                $this->agendaId = $stream->agenda_id;
                $this->youtubeInput = $stream->youtube_id ?? '';
                if ($stream->isManual()) {
                    $this->title = $stream->manual_title ?? '';
                    $this->program_id = $stream->manual_program_id;
                    $start = $stream->manual_starts_at?->setTimezone('Asia/Jakarta');
                    $this->agenda_date = $start?->format('Y-m-d') ?? '';
                    $this->start_time = $start?->format('H:i') ?? '';
                    $this->end_time = $stream->manual_ends_at?->setTimezone('Asia/Jakarta')->format('H:i') ?? '';
                    $this->visibility = $stream->manual_visibility ?? 'internal';
                }
            });
        }
    }

    public function save()
    {
        $this->authorize('viewAny', LiveStream::class);
        $this->requireLiveSchema();
        $this->validate(['youtubeInput' => ['nullable', 'string', 'max:2048']]);
        if ($this->streamId === null) {
            $this->authorize('create', LiveStream::class);
            $stream = app(LiveStreamLifecycle::class)->create(auth()->user(), $this->mode === 'agenda' ? $this->agendaId : null, $this->youtubeInput, $this->mode,
                $this->mode === 'manual' ? $this->manualInput() : []);
        } else {
            $stream = $this->authorizedStream($this->streamId, 'update');
            if ($this->expectedVersion === null) {
                throw new LiveStreamOperationException('LIVE_STALE');
            }
            $stream = app(LiveStreamLifecycle::class)->updateConfiguration(auth()->user(), $stream->id, $this->expectedVersion, $this->youtubeInput,
                $stream->isManual() && $stream->status === 'scheduled' ? $this->manualInput() : null);
        }
        session()->flash('success', 'Konfigurasi Live Streaming berhasil disimpan.');

        return redirect()->route('admin.live-streams.show', $stream->id);
    }

    private function manualInput(): array
    {
        return [
            'title' => $this->title, 'program_id' => $this->program_id, 'agenda_date' => $this->agenda_date,
            'start_time' => $this->start_time, 'end_time' => $this->end_time === '' ? null : $this->end_time, 'visibility' => $this->visibility,
        ];
    }

    public function render()
    {
        $this->authorize('viewAny', LiveStream::class);
        $data = $this->readLive(function () {
            if ($this->streamId !== null) {
                $stream = $this->authorizedStream($this->streamId, 'update');

                return ['stream' => $stream, 'agendas' => collect(), 'selectedAgenda' => null,
                    'programs' => $stream->isManual() && $stream->status === 'scheduled' ? $this->programs() : collect()];
            }
            $this->authorize('create', LiveStream::class);
            if ($this->mode !== 'agenda') {
                return ['stream' => null, 'agendas' => collect(), 'selectedAgenda' => null, 'programs' => $this->programs()];
            }
            $search = InputBounds::search($this->agendaSearch);
            $eligible = WpitvAgenda::with('program:id,name')->where('type', 'live')->whereIn('status', ['draft', 'scheduled'])->whereDoesntHave('liveStream');
            $selected = $this->agendaId === null ? null : (clone $eligible)->find($this->agendaId);
            $agendas = $eligible->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
                ->orderByDesc('agenda_date')->orderByDesc('id')->limit(50)->get();
            if ($selected !== null && ! $agendas->contains($selected)) {
                $agendas->prepend($selected);
            }

            return ['stream' => null, 'agendas' => $agendas, 'selectedAgenda' => $selected, 'programs' => collect()];
        }, ['stream' => null, 'agendas' => collect(), 'selectedAgenda' => null, 'programs' => collect()]);
        $id = YouTubeVideoId::parse($this->youtubeInput);

        return view('livewire.admin.live-streams.form', [...$data, 'loadError' => $this->liveReadError, 'previewUrl' => YouTubeVideoId::embedUrl($id), 'watchUrl' => YouTubeVideoId::watchUrl($id)])->title($this->streamId ? 'Konfigurasi Live Streaming' : 'Tambah Live Streaming');
    }

    private function programs()
    {
        $this->authorize('viewAny', Program::class);

        return Program::orderBy('name')->get(['id', 'name']);
    }
}
