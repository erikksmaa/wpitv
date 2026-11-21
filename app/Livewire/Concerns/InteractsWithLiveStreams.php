<?php

namespace App\Livewire\Concerns;

use App\Exceptions\LiveStreamOperationException;
use App\Models\LiveStream;
use App\Support\AgendaLiveGuard;
use App\Support\LiveStreamErrors;
use Closure;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

trait InteractsWithLiveStreams
{
    use AuthorizesRequests, HandlesLiveStreamErrors;

    protected ?string $liveReadError = null;

    protected function requireLiveSchema(): void
    {
        if (! app(AgendaLiveGuard::class)->available()) {
            throw new LiveStreamOperationException('LIVE_SCHEMA');
        }
    }

    protected function readLive(Closure $read, mixed $fallback = null): mixed
    {
        $this->liveReadError = null;
        try {
            return LiveStreamErrors::run(function () use ($read) {
                $this->requireLiveSchema();

                return $read();
            });
        } catch (LiveStreamOperationException $error) {
            $this->liveReadError = $error->getMessage();

            return $fallback;
        }
    }

    protected function authorizedStream(int $id, string $ability = 'view'): LiveStream
    {
        return LiveStreamErrors::run(function () use ($id, $ability) {
            $this->authorize('viewAny', LiveStream::class);
            $this->requireLiveSchema();
            $stream = LiveStream::with(['agenda.program', 'manualProgram', 'archiveVideo'])->findOrFail($id);
            $this->authorize($ability, $stream);

            return $stream;
        });
    }
}
