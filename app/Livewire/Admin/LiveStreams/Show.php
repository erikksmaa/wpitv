<?php

namespace App\Livewire\Admin\LiveStreams;

use App\Exceptions\LiveStreamOperationException;
use App\Livewire\Concerns\InteractsWithLiveStreams;
use App\Models\LiveStream;
use App\Models\Video;
use App\Services\LiveStreamLifecycle;
use Closure;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class Show extends Component
{
    use InteractsWithLiveStreams, WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Locked]
    public int $streamId;

    #[Locked]
    public ?int $expectedVersion = null;

    public string $visibility = 'internal';

    public string $reason = '';

    public string $rescheduleDate = '';

    public string $rescheduleStart = '';

    public string $rescheduleEnd = '';

    public ?int $archiveVideoId = null;

    public function mount(int $streamId): void
    {
        $this->authorize('viewAny', LiveStream::class);
        $this->streamId = $streamId;
        $this->readLive(fn () => $this->capture($this->authorizedStream($streamId)));
    }

    private function capture(LiveStream $stream): void
    {
        $this->expectedVersion = $stream->version;
        $this->visibility = $stream->visibility ?? 'internal';
        if ($stream->isManual()) {
            $start = $stream->manual_starts_at?->setTimezone('Asia/Jakarta');
            $this->rescheduleDate = $start?->format('Y-m-d') ?? '';
            $this->rescheduleStart = $start?->format('H:i') ?? '';
            $this->rescheduleEnd = $stream->manual_ends_at?->setTimezone('Asia/Jakarta')->format('H:i') ?? '';
        } else {
            $this->rescheduleDate = $stream->agenda->agenda_date->format('Y-m-d');
            $this->rescheduleStart = substr($stream->agenda->start_time ?? '', 0, 5);
            $this->rescheduleEnd = substr($stream->agenda->end_time ?? '', 0, 5);
        }
        $this->archiveVideoId = $stream->archive_video_id;
        $this->reason = '';
    }

    private function mutate(string $ability, Closure $operation): void
    {
        $this->authorizedStream($this->streamId, $ability);
        if ($this->expectedVersion === null) {
            throw new LiveStreamOperationException('LIVE_STALE');
        }
        $stream = $operation(app(LiveStreamLifecycle::class));
        $this->capture($stream);
        $this->resetPage('historyPage');
        $this->resetErrorBag();
        session()->flash('success', 'Perubahan Live Streaming berhasil disimpan.');
    }

    public function markLive(): void
    {
        $this->mutate('changeStatus', fn ($service) => $service->transition(auth()->user(), $this->streamId, $this->expectedVersion, 'live'));
    }

    public function end(): void
    {
        $this->mutate('changeStatus', fn ($service) => $service->transition(auth()->user(), $this->streamId, $this->expectedVersion, 'ended', $this->reason));
    }

    public function cancel(): void
    {
        $this->mutate('changeStatus', fn ($service) => $service->transition(auth()->user(), $this->streamId, $this->expectedVersion, 'cancelled', $this->reason));
    }

    public function reschedule(): void
    {
        $this->mutate('changeStatus', fn ($service) => $service->transition(auth()->user(), $this->streamId, $this->expectedVersion, 'scheduled', $this->reason, [
            'agenda_date' => $this->rescheduleDate, 'start_time' => $this->rescheduleStart,
            'end_time' => $this->rescheduleEnd ?: null, 'visibility' => $this->visibility,
        ]));
    }

    public function saveVisibility(): void
    {
        $this->mutate('update', fn ($service) => $service->updateVisibility(auth()->user(), $this->streamId, $this->expectedVersion, $this->visibility));
    }

    public function saveArchive(): void
    {
        $this->mutate('manageArchive', fn ($service) => $service->linkArchive(auth()->user(), $this->streamId, $this->expectedVersion, $this->archiveVideoId));
    }

    public function unlinkArchive(): void
    {
        $this->mutate('manageArchive', fn ($service) => $service->linkArchive(auth()->user(), $this->streamId, $this->expectedVersion, null));
    }

    public function render()
    {
        $this->authorize('viewAny', LiveStream::class);
        $data = $this->readLive(function () {
            $stream = $this->authorizedStream($this->streamId);
            $videos = $stream->status === 'ended' && $stream->youtube_id !== null
                ? Video::whereRaw('BINARY youtube_id = ?', [$stream->youtube_id])->orderBy('id')->limit(50)->get(['id', 'title', 'youtube_id']) : collect();

            return ['stream' => $stream, 'videos' => $videos, 'events' => $stream->events()->orderByDesc('id')->paginate(10, ['*'], 'historyPage')];
        }, ['stream' => null, 'videos' => collect(), 'events' => null]);

        return view('livewire.admin.live-streams.show', [...$data, 'loadError' => $this->liveReadError])->title('Detail Live Streaming');
    }
}
